<?php

namespace App\Libraries;

use Throwable;

/**
 * Gemini — 자연어 검색 의도/키워드 추출
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?: (string)env('GEMINI_API_KEY', '');
        $this->model = $model ?: (string)env('GEMINI_MODEL', 'gemini-flash-lite-latest');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractIntent(string $query): ?array
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return null;
        }

        $prompt = <<<PROMPT
너는 전남·광주 청년·복지·일자리 지원사업 검색 도우미다.
사용자 문장을 분석해 지원사업 검색에 쓸 JSON만 출력하라. 설명 문장 금지.

규칙:
- topics는 다음 중만: 주거, 창업, 취업, 금융, 재직, 기업 (해당 없으면 [])
- categories는 다음 코드만: A(기업), B(청년), C(신중년), D(여성), E(시니어), F(장애인)
- keywords는 공고 제목/본문 검색에 바로 쓸 한국어 핵심어 2~5개 (불용어·조사 제외)
- area는 시군명만 (목포,순천,여수,나주,광양,담양,곡성,구례,고흥,보성,화순,장흥,강진,해남,영암,무안,함평,영광,장성,완도,진도,신안,광주). 문장에 없으면 ""
- ageMin/ageMax는 숫자 또는 null
- "독립하고 싶어"처럼 간접 표현이면 주거·자립·전세·월세 등으로 확장

출력 스키마:
{"keywords":["독립","주거"],"topics":["주거"],"categories":["B"],"ageMin":null,"ageMax":null,"area":"","summary":"한 줄 요약"}

사용자 문장: {$query}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 512,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = sprintf(self::ENDPOINT, $this->model) . '?key=' . rawurlencode($this->apiKey);
        $body = $this->postJson($url, $payload);
        if ($body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        $text = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            log_message('error', 'Gemini empty text: ' . mb_substr($body, 0, 300));

            return null;
        }

        if (preg_match('/\{.*\}/su', $text, $m)) {
            $text = $m[0];
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            log_message('error', 'Gemini invalid JSON: ' . mb_substr($text, 0, 300));

            return null;
        }

        return $parsed;
    }

    private function postJson(string $url, array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        try {
            $client = service('curlrequest');
            $timeout = max(3, (int)env('GEMINI_TIMEOUT', 5));
            $response = $client->post($url, [
                'timeout' => $timeout,
                'http_errors' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'jn-support-ci4/1.0',
                ],
                'json' => $payload,

            ]);
            $body = (string)$response->getBody();
            if ($body !== '') {
                return $body;
            }
        } catch (Throwable $e) {
            log_message('error', 'Gemini curl: ' . $e->getMessage());
        }

        $timeout = max(3, (int)env('GEMINI_TIMEOUT', 5));
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nUser-Agent: jn-support-ci4/1.0\r\n",
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body === false ? '' : $body;
    }
}
