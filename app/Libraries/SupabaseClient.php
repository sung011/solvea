<?php

namespace App\Libraries;

use Throwable;

/**
 * Supabase REST (PostgREST) 클라이언트
 */
class SupabaseClient
{
    private string $url;
    private string $serviceKey;
    private string $anonKey;

    public function __construct(?string $url = null, ?string $serviceKey = null, ?string $anonKey = null)
    {
        $this->url = rtrim($url ?: (string)env('SUPABASE_URL', ''), '/');
        $this->serviceKey = $serviceKey ?: (string)env('SUPABASE_SERVICE_ROLE_KEY', '');
        $this->anonKey = $anonKey ?: (string)env('SUPABASE_ANON_KEY', '');
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->serviceKey !== '';
    }

    /**
     * @return list<array<string, mixed>>|array{_error: true, _status?: int, _body?: mixed}
     */
    public function select(string $table, array $query = [], ?string $prefer = null): array
    {
        $response = $this->request('GET', '/rest/v1/' . ltrim($table, '/'), null, $query, $prefer);
        if (!is_array($response)) {
            return ['_error' => true, '_status' => 0, '_body' => ['message' => 'empty response']];
        }
        if (($response['_error'] ?? false) === true) {
            return $response;
        }

        return array_is_list($response) ? $response : [$response];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function insert(string $table, array $row): ?array
    {
        $response = $this->request(
            'POST',
            '/rest/v1/' . ltrim($table, '/'),
            $row,
            [],
            'return=representation'
        );

        if (!is_array($response)) {
            return null;
        }
        if (($response['_error'] ?? false) === true) {
            return $response;
        }
        if (array_is_list($response)) {
            return $response[0] ?? null;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $patch
     * @param array<string, string> $filters e.g. ['user_id' => 'eq.kim']
     */
    public function update(string $table, array $patch, array $filters): bool
    {
        $response = $this->request(
            'PATCH',
            '/rest/v1/' . ltrim($table, '/'),
            $patch,
            $filters,
            'return=minimal'
        );

        if (!is_array($response)) {
            return $response === [];
        }

        return ($response['_error'] ?? false) !== true;
    }

    /**
     * @param array<string, string> $filters
     */
    public function delete(string $table, array $filters): bool
    {
        $response = $this->request(
            'DELETE',
            '/rest/v1/' . ltrim($table, '/'),
            null,
            $filters,
            'return=minimal'
        );

        if (!is_array($response)) {
            return $response === [];
        }

        return ($response['_error'] ?? false) !== true;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string|int|float|bool|null> $query
     * @return array<string, mixed>|list<mixed>|null
     */
    private function request(string $method, string $path, ?array $body = null, array $query = [], ?string $prefer = null)
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Supabase 설정이 없습니다.');
        }

        $url = $this->url . $path;
        if ($query !== []) {
            $parts = [];
            foreach ($query as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
            }
            if ($parts !== []) {
                $url .= '?' . implode('&', $parts);
            }
        }

        $headerLines = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($prefer !== null && $prefer !== '') {
            $headerLines[] = 'Prefer: ' . $prefer;
        }

        try {
            $client = service('curlrequest');
            $options = [
                'timeout' => 20,
                'http_errors' => false,
                'headers' => [
                    'apikey' => $this->serviceKey,
                    'Authorization' => 'Bearer ' . $this->serviceKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];
            if ($prefer !== null && $prefer !== '') {
                $options['headers']['Prefer'] = $prefer;
            }
            if ($body !== null) {
                $options['json'] = $body;
            }

            $methodUpper = strtoupper($method);
            if ($methodUpper === 'GET') {
                $response = $client->get($url, $options);
            } elseif ($methodUpper === 'POST') {
                $response = $client->post($url, $options);
            } elseif ($methodUpper === 'PATCH') {
                $response = $client->patch($url, $options);
            } elseif ($methodUpper === 'DELETE') {
                $response = $client->delete($url, $options);
            } else {
                throw new \InvalidArgumentException('Unsupported method');
            }

            $status = $response->getStatusCode();
            $raw = (string)$response->getBody();

            return $this->decodeResponse($method, $path, $status, $raw);
        } catch (Throwable $e) {
            log_message('error', 'Supabase request: ' . $e->getMessage());

            $opts = [
                'http' => [
                    'method' => strtoupper($method),
                    'header' => implode("\r\n", $headerLines) . "\r\n",
                    'ignore_errors' => true,
                    'timeout' => 20,
                ],
            ];
            if ($body !== null) {
                $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
            }

            $raw = @file_get_contents($url, false, stream_context_create($opts));
            if ($raw === false) {
                return ['_error' => true, '_status' => 0, '_body' => ['message' => $e->getMessage()]];
            }

            $status = 200;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int)$m[1];
            }

            return $this->decodeResponse($method, $path, $status, (string)$raw);
        }
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function decodeResponse(string $method, string $path, int $status, string $raw)
    {
        if ($status >= 400) {
            log_message('error', "Supabase {$method} {$path} => {$status} {$raw}");
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? ['_error' => true, '_status' => $status, '_body' => $decoded]
                : ['_error' => true, '_status' => $status, '_body' => ['message' => $raw]];
        }

        if ($raw === '' || $raw === 'null') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
