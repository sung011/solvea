<?php

namespace App\Libraries;

use PDO;
use SimpleXMLElement;
use Throwable;

/**
 * 지원사업 검색 — 공공 API + 로컬 SQLite 캐시
 */
class SearchService
{
    private const SUPPORT_BASE = 'https://apis.data.go.kr/6460000/jnSupportInfo';
    private const GOVJOB_BASE = 'https://apis.data.go.kr/6460000/jnGovjobInfo';
    private const PROGRAM_BASE = 'https://apis.data.go.kr/6460000/supportPgm';
    private const YOUTH_URL = 'https://www.youthcenter.go.kr/go/ythip/getPlcy';
    private const GOV24_BASE = 'https://api.odcloud.kr/api/gov24/v3';
    private const LCGV_BASE = 'https://apis.data.go.kr/B554287/LocalGovernmentWelfareInformations';
    private const NWLF_BASE = 'https://apis.data.go.kr/B554287/NationalWelfareInformationsV001';
    private const SUBSIDY_URL = 'https://apis.data.go.kr/1741000/Subsidy24/getSubsidy24';

    /** 전남 지원프로그램 (희망버스·잡매칭·만남의날·상담센터) */
    private const PROGRAMS = [
        ['hopebus', '청년희망버스', '/getHopeBusList'],
        ['jobmatch', '잡매칭데이', '/getJobMatchList'],
        ['meetday', '구인구직 만남의 날', '/getMeetDayList'],
        ['center', '일자리상담센터', '/getGoingCenterList'],
    ];

    /** 검색어 토픽 → 정부24 서비스분야 */
    private const GOV24_FIELD = [
        '주거' => '주거',
        '창업' => '창업',
        '취업' => '일자리',
        '금융' => '금융',
        '재직' => '고용',
        '기업' => '기업',
    ];

    private const WELFARE_TOPIC_WORDS = [
        '주거' => ['주거', '주택'],
        '창업' => ['창업'],
        '취업' => ['일자리', '취업'],
        '금융' => ['금융', '대출'],
        '재직' => ['고용'],
        '기업' => ['기업'],
    ];

    private const CATEGORY = [
        'A' => '기업',
        'B' => '청년',
        'C' => '신중년',
        'D' => '여성',
        'E' => '시니어',
        'F' => '장애인',
    ];

    private const DEPART = [
        'A' => '취업지원',
        'B' => '재직지원',
        'C' => '생활/금융',
        'D' => '교육/주거',
        'E' => '기업지원',
        'F' => '창업지원',
    ];

    private const APPLY = [
        'A' => '방문접수',
        'B' => '우편접수',
        'C' => '전화접수',
        'D' => '이메일',
        'E' => '홈페이지 접수',
    ];

    private const AREAS = [
        '목포', '순천', '여수', '나주', '광양', '담양', '곡성', '구례', '고흥', '보성',
        '화순', '장흥', '강진', '해남', '영암', '무안', '함평', '영광', '장성', '완도',
        '진도', '신안', '광주',
    ];

    /** 광주·전남으로 인정하는 지역 키워드 */
    private const JN_HINTS = [
        '전남', '광주', '전라남도', '전남광주', '광산',
        '목포', '순천', '여수', '나주', '광양', '담양', '곡성', '구례', '고흥', '보성',
        '화순', '장흥', '강진', '해남', '영암', '무안', '함평', '영광', '장성', '완도',
        '진도', '신안',
    ];

    /** 타 지역 — 결과에 넣지 않음 */
    private const OTHER_REGIONS = [
        '서울', '부산', '대구', '인천', '대전', '울산', '세종', '경기', '강원',
        '충북', '충남', '전북', '제주', '경북', '경남', '창원', '수원', '안양',
        '파주', '서산', '청주', '천안', '고양', '용인', '성남', '부천', '화성',
        '남양주', '평택', '의정부', '시흥', '김포', '광명', '하남', '오산',
    ];

    private const STOPWORDS = [
        '지원사업', '지원', '사업', '추천해줘', '추천해주세요', '추천', '알려줘', '찾아줘',
        '검색', '해줘', '해주세요', '좀', '있는', '있나요', '있어', '있을까', '관련',
        '프로그램', '정책', '일자리', '전남', '광주', '전라남도',
        '하고', '싶어', '싶어요', '싶어여', '원해', '원해요', '하고싶어', '하고싶어요',
        '해주세요', '부탁', '부탁해', '제발', '그냥', '뭔가', '어떤', '뭐가', '뭐',
        '나는', '제가', '우리', '지금', '요즘', '너무', '정말',
    ];

    /** Gemini 없이도 자연어 → 주제 매핑 */
    private const INTENT_HINTS = [
        '주거' => ['독립', '자립', '자취', '원룸', '전세', '월세', '임대', '주거', '집구', '방구', '기숙사', '주택'],
        '창업' => ['창업', '스타트업', '사업하고', '가게'],
        '취업' => ['취업', '취준', '구직', '알바', '면접', '채용'],
        '금융' => ['대출', '생활비', '금융', '이자', '빚'],
        '재직' => ['재직', '이직', '근속'],
        '기업' => ['중소기업', '기업지원', '보조금'],
    ];

    private const TOPIC_GROUPS = [
        '주거' => ['주거', '주거비', '전세', '월세', '임대주택', '기숙사', '주택', '임차', '독립', '자립', '자취'],
        '창업' => ['창업', '스타트업', '창업자', '예비창업'],
        '취업' => ['취업', '구직', '채용', '구직활동', '면접', '취준'],
        '금융' => ['금융', '대출', '생활비', '이자', '전세자금'],
        '재직' => ['재직', '근속', '이직'],
        '기업' => ['기업지원', '중소기업', '보조금'],
    ];

    private const DEPART_WORDS = [
        'F' => ['창업', '스타트업', '창업자'],
        'D' => ['주거', '전세', '월세', '주택', '부동산', '임대', '기숙사'],
        'B' => ['재직', '근속', '이직'],
        'C' => ['금융', '대출', '생활비', '이자'],
        'E' => ['기업지원', '중소기업', '보조금'],
        'A' => ['취업', '구직', '채용', '일자리'],
    ];

    private const TOPIC_NEGATIVE = [
        '주거' => [
            '인구주택', '가구주택', '개별주택', '총조사', '조사요원', '조사원',
            '자활근로', '기간제근로', '채용 공고', '모집공고', '모집 공고',
        ],
        '취업' => [
            // 구직 검색에 이미 취업한 사람 대상 주거비·근속 지원이 섞이지 않게
        ],
    ];

    private string $serviceKey;
    private string $youthKey;
    private string $dbPath;
    private $gemini = null;

    public function __construct(?string $serviceKey = null, ?string $dbPath = null, ?string $youthKey = null, $gemini = null)
    {
        $this->serviceKey = $serviceKey
            ?: (string)env('DATA_GO_KR_KEY', 'W0dlVSMzb/3Goq3tjjPjFiKzWvtgOcuagSX1992mmCvID76qIHwen0+63lA/yLEYB6kC2USG1Ce6RROg3CH0WA==');
        $this->youthKey = $youthKey
            ?: (string)env('YOUTHCENTER_API_KEY', '');
        $this->dbPath = $dbPath ?: WRITEPATH . 'jn_support.db';
        if (is_object($gemini) && method_exists($gemini, 'extractIntent')) {
            $this->gemini = $gemini;
        } elseif (is_file(APPPATH . 'Libraries/GeminiClient.php')) {
            try {
                if (!class_exists(\App\Libraries\GeminiClient::class, false)) {
                    require_once APPPATH . 'Libraries/GeminiClient.php';
                }
                $this->gemini = new \App\Libraries\GeminiClient();
            } catch (Throwable $e) {
                log_message('error', 'Gemini init: ' . $e->getMessage());
                $this->gemini = null;
            }
        }
    }

    /** 전남 getSupportList 연결 상태 점검 */
    public function probeSupportApi(): array
    {
        $list = $this->fetchXml(self::SUPPORT_BASE . '/getSupportList', [
            'startPage' => '1',
            'pageSize' => '3',
            'jobCategory' => 'B',
            'jobContent' => '주거',
        ], 'list');

        $firstKey = $list['items'][0]['jobKey'] ?? '';
        $info = ['resultCode' => 'skip', 'items' => []];
        $files = ['resultCode' => 'skip', 'items' => []];
        if ($firstKey !== '') {
            $info = $this->fetchSupportDetail($firstKey);
            $files = $this->fetchXml(self::SUPPORT_BASE . '/getSupportInfoFile', [
                'jobKey' => $firstKey,
                'startPage' => '1',
                'pageSize' => '5',
            ], 'file');
        }

        return [
            'serviceKeySet' => $this->serviceKey !== '',
            'getSupportList' => [
                'resultCode' => $list['resultCode'] ?? '',
                'resultMsg' => $list['resultMsg'] ?? '',
                'totalCount' => $list['totalCount'] ?? 0,
                'sample' => array_map(static fn($i) => [
                    'jobKey' => $i['jobKey'] ?? '',
                    'jobTitle' => $i['jobTitle'] ?? '',
                    'jobArea' => $i['jobArea'] ?? '',
                ], array_slice($list['items'] ?? [], 0, 3)),
            ],
            'getSupportInfo' => [
                'resultCode' => $info['resultCode'] ?? '',
                'resultMsg' => $info['resultMsg'] ?? '',
                'hasContent' => !empty(($info['items'][0]['jobContent'] ?? '')),
            ],
            'getSupportInfoFile' => [
                'resultCode' => $files['resultCode'] ?? '',
                'resultMsg' => $files['resultMsg'] ?? '',
                'fileCount' => count($files['items'] ?? []),
            ],
        ];
    }

    public function recommend(string $query, int $startPage = 1, int $pageSize = 8): array
    {
        try {
            $parsed = $this->parseQuery($query);
            $items = $this->collectItems($parsed);

            // 0건이면 DB만이라도 주제/시드로 재검색 (외부 API 재호출 없음)
            if ($items === []) {
                $relaxed = $parsed;
                $relaxed['keywords'] = [];
                foreach ($this->dbSearch($relaxed) as $item) {
                    $item['_score'] = $this->score($item, $parsed) + 5;
                    $items[] = $item;
                }
            }
            if ($items === []) {
                $seeds = array_values(array_unique(array_filter(array_merge(
                    $parsed['searchTerms'] ?? [],
                    $parsed['topics'] ?? [],
                    $parsed['keywords'] ?? []
                ))));
                $soft = $parsed;
                $soft['keywords'] = [];
                $soft['topics'] = [];
                foreach ($this->dbSearch($soft) as $item) {
                    if ($seeds === []) {
                        $items[] = $item;
                        continue;
                    }
                    $blob = ($item['jobTitle'] ?? '') . ' ' . ($item['jobTarget'] ?? '')
                        . ' ' . ($item['jobDepartLabel'] ?? '') . ' ' . ($item['jobContent'] ?? '');
                    foreach ($seeds as $seed) {
                        if ($seed !== '' && mb_strpos($blob, (string)$seed) !== false) {
                            $item['_score'] = $this->score($item, $parsed) + 3;
                            $items[] = $item;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'recommend failed: ' . $e->getMessage());

            return [
                'resultCode' => '99',
                'resultMsg' => '검색 중 오류가 발생했습니다.',
                'totalCount' => 0,
                'startPage' => (string)$startPage,
                'pageSize' => (string)$pageSize,
                'items' => [],
                'parsed' => ['query' => $query, 'summary' => '', 'intentSource' => 'error'],
                'sources' => [],
                'filters' => [],
            ];
        }

        usort($items, static function (array $a, array $b) use ($parsed): int {
            return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
        });

        // 같은 제목 중복 제거 (출처만 다른 동일 공고)
        $deduped = [];
        $seenTitles = [];
        foreach ($items as $item) {
            $titleKey = preg_replace('/\s+/u', '', (string)($item['jobTitle'] ?? ''));
            if ($titleKey !== '' && isset($seenTitles[$titleKey])) {
                continue;
            }
            if ($titleKey !== '') {
                $seenTitles[$titleKey] = true;
            }
            $deduped[] = $item;
        }
        $items = $deduped;

        $total = count($items);
        $start = max($startPage - 1, 0) * $pageSize;
        $pageItems = array_slice($items, $start, $pageSize);

        foreach ($pageItems as &$item) {
            unset($item['_score']);
        }
        unset($item);

        return [
            'resultCode' => '00',
            'resultMsg' => 'success',
            'totalCount' => $total,
            'startPage' => (string)$startPage,
            'pageSize' => (string)$pageSize,
            'items' => array_values($pageItems),
            'parsed' => $parsed,
            'sources' => [
                'getSupportList',
                'getSupportInfo',
                'getSupportInfoFile',
                'getGovjobList',
                'getGovjobInfo',
                'getGovjobFile',
                'getHopeBusList',
                'getJobMatchList',
                'getMeetDayList',
                'getGoingCenterList',
                'getPlcy',
                'gov24/serviceList',
                'gov24/serviceDetail',
                'gov24/supportConditions',
                'LcgvWelfarelist',
                'NationalWelfarelistV001',
                'local-db',
            ],
            'filters' => [
                'jobCategory' => $parsed['categories'][0] ?? 'B',
                'jobArea' => $parsed['area'] !== '' ? $parsed['area'] : '전체',
                'jobDepart' => $parsed['depart'],
                'jobApply' => '',
                'jobContent' => implode(' ', $parsed['searchTerms'] ?? $parsed['keywords'] ?? []),
            ],
        ];
    }

    public function detail(string $kind, string $jobKey): array
    {
        $kind = $kind !== '' ? $kind : 'support';
        $jobKey = trim($jobKey);

        if ($jobKey === '') {
            return [
                'resultCode' => '99',
                'resultMsg' => 'jobKey가 필요합니다.',
                'items' => [],
                'files' => [],
            ];
        }

        if ($kind === 'support') {
            $live = $this->fetchSupportDetail($jobKey);
            if (($live['resultCode'] ?? '') === '00' && !empty($live['items'])) {
                $live['files'] = $this->fetchSupportFiles($jobKey);
                $live['source'] = [
                    'list' => 'getSupportList',
                    'detail' => 'getSupportInfo',
                    'file' => 'getSupportInfoFile',
                ];

                return $live;
            }
        }

        if ($kind === 'govjob') {
            $live = $this->fetchGovjobDetail($jobKey);
            if (($live['resultCode'] ?? '') === '00' && !empty($live['items'])) {
                $live['files'] = $this->fetchGovjobFiles($jobKey);
                $live['source'] = [
                    'list' => 'getGovjobList',
                    'detail' => 'getGovjobInfo',
                    'file' => 'getGovjobFile',
                ];

                return $live;
            }
        }

        if ($kind === 'youth') {
            $live = $this->fetchYouthDetail($jobKey);
            if ($live !== null) {
                return [
                    'resultCode' => '00',
                    'resultMsg' => 'success',
                    'items' => [$live],
                    'files' => [],
                    'source' => [
                        'list' => 'getPlcy',
                        'detail' => 'getPlcy',
                        'file' => '',
                    ],
                ];
            }
        }

        if ($kind === 'gov24') {
            $live = $this->fetchGov24Detail($jobKey);
            if ($live !== null) {
                return [
                    'resultCode' => '00',
                    'resultMsg' => 'success',
                    'items' => [$live],
                    'files' => [],
                    'source' => [
                        'list' => 'gov24/serviceList',
                        'detail' => 'gov24/serviceDetail',
                        'file' => 'gov24/supportConditions',
                    ],
                ];
            }
        }

        if ($kind === 'lcgv' || $kind === 'nwlf') {
            $live = $this->fetchWelfareDetail($kind, $jobKey);
            if ($live !== null) {
                return [
                    'resultCode' => '00',
                    'resultMsg' => 'success',
                    'items' => [$live],
                    'files' => [],
                    'source' => [
                        'list' => $kind === 'lcgv' ? 'LcgvWelfarelist' : 'NationalWelfarelistV001',
                        'detail' => $kind === 'lcgv' ? 'LcgvWelfaredetailed' : 'NationalWelfaredetailedV001',
                        'file' => '',
                    ],
                ];
            }
        }

        if (in_array($kind, ['hopebus', 'jobmatch', 'meetday', 'center'], true)) {
            $live = $this->fetchProgramDetail($kind, $jobKey);
            if ($live !== null) {
                return [
                    'resultCode' => '00',
                    'resultMsg' => 'success',
                    'items' => [$live],
                    'files' => [],
                    'source' => [
                        'list' => $this->programPath($kind),
                        'detail' => $this->programPath($kind),
                        'file' => '',
                    ],
                ];
            }
        }

        $item = $this->dbFind($kind, $jobKey);
        if ($item === null) {
            return [
                'resultCode' => '99',
                'resultMsg' => '항목을 찾지 못했습니다.',
                'items' => [],
                'files' => [],
            ];
        }

        $item = $this->enrichDetail($item);

        return [
            'resultCode' => '00',
            'resultMsg' => 'success',
            'items' => [$item],
            'files' => $this->dbFiles($kind, $jobKey),
            'source' => ['list' => 'local-db', 'detail' => 'local-db', 'file' => ''],
        ];
    }

    public function parseQuery(string $text): array
    {
        $raw = trim($text);
        $ageMin = null;
        $ageMax = null;

        if (preg_match('/(\d{2})대/u', $raw, $m)) {
            $start = (int)$m[1];
            $ageMin = $start;
            $ageMax = $start + 9;
        } elseif (preg_match('/(?:만\s*)?(\d{1,2})\s*(?:세|살)/u', $raw, $m)) {
            $age = (int)$m[1];
            $ageMin = $age;
            $ageMax = $age;
        }

        $categories = [];
        if (mb_strpos($raw, '기업') !== false && mb_strpos($raw, '기업지원') === false) {
            $categories[] = 'A';
        }
        if ($this->containsAny($raw, ['청년', '대학생', '취준']) || ($ageMin !== null && $ageMin <= 39)) {
            $categories[] = 'B';
        }
        if (mb_strpos($raw, '신중년') !== false || ($ageMin !== null && $ageMin >= 40 && $ageMin <= 64)) {
            $categories[] = 'C';
        }
        if ($this->containsAny($raw, ['여성', '여자'])) {
            $categories[] = 'D';
        }
        if ($this->containsAny($raw, ['시니어', '노인', '어르신']) || ($ageMin !== null && $ageMin >= 65)) {
            $categories[] = 'E';
        }
        if (mb_strpos($raw, '장애') !== false) {
            $categories[] = 'F';
        }
        if ($categories === []) {
            $categories = ['B'];
        }
        $categories = array_values(array_unique($categories));

        $area = '';
        foreach (self::AREAS as $name) {
            if (mb_strpos($raw, $name) !== false) {
                $area = $name;
                break;
            }
        }

        $depart = '';
        foreach (self::DEPART_WORDS as $code => $words) {
            if ($this->containsAny($raw, $words)) {
                $depart = $code;
                break;
            }
        }

        $topics = [];
        foreach (self::TOPIC_GROUPS as $name => $words) {
            if ($this->containsAny($raw, $words)) {
                $topics[] = $name;
            }
        }
        // 간접 표현 힌트 (독립→주거 등). Gemini 없이도 동작.
        foreach (self::INTENT_HINTS as $topic => $hints) {
            if (in_array($topic, $topics, true)) {
                continue;
            }
            if ($this->containsAny($raw, $hints)) {
                $topics[] = $topic;
            }
        }

        $leftover = $raw;
        $remove = array_merge(self::STOPWORDS, self::AREAS, array_values(self::CATEGORY), [
            '공공일자리', '공공근로', '청년희망버스', '희망버스', '잡매칭데이', '잡매칭',
            '만남의날', '만남의 날', '일자리상담센터', '상담센터',
        ]);
        foreach ($remove as $token) {
            $leftover = str_replace($token, ' ', $leftover);
        }
        foreach (self::DEPART_WORDS as $words) {
            foreach ($words as $word) {
                $leftover = str_replace($word, ' ', $leftover);
            }
        }
        foreach (self::TOPIC_GROUPS as $words) {
            foreach ($words as $word) {
                // 주제 단어는 topics로 이미 반영 → 키워드 AND에서 중복 제외
                $leftover = str_replace($word, ' ', $leftover);
            }
        }
        $leftover = preg_replace('/(?:만\s*)?\d{1,2}\s*(?:세|살|대)/u', ' ', $leftover) ?? $leftover;
        $leftover = preg_replace('/[^0-9A-Za-z가-힣]+/u', ' ', $leftover) ?? $leftover;
        $keywords = [];
        foreach (preg_split('/\s+/u', trim($leftover)) ?: [] as $word) {
            if (mb_strlen($word) >= 2) {
                $keywords[] = $word;
            }
        }

        // 검색 시드: 주제 + 남은 키워드 + 힌트 단어
        $searchTerms = $keywords;
        foreach ($topics as $topic) {
            $searchTerms[] = $topic;
            foreach (array_slice(self::TOPIC_GROUPS[$topic] ?? [], 0, 3) as $term) {
                $searchTerms[] = $term;
            }
        }
        $searchTerms = array_values(array_unique(array_filter($searchTerms)));

        if ($depart === '' && $topics !== []) {
            $topicDepart = ['주거' => 'D', '창업' => 'F', '취업' => 'A', '금융' => 'C', '재직' => 'B', '기업' => 'E'];
            $depart = $topicDepart[$topics[0]] ?? '';
        }

        $parsed = [
            'query' => $raw,
            'ageMin' => $ageMin,
            'ageMax' => $ageMax,
            'categories' => $categories,
            'area' => $area,
            'depart' => $depart,
            'topics' => $topics,
            'keywords' => $keywords,
            'searchTerms' => $searchTerms,
            'intentSource' => 'rules',
            'summary' => '',
        ];
        if ($this->shouldEnrichWithGemini($parsed)) {
            try {
                $parsed = $this->enrichWithGemini($parsed);
            } catch (Throwable $e) {
                log_message('error', 'Gemini enrich: ' . $e->getMessage());
            }
        }
        $parsed['summary'] = $this->summarize($parsed);

        return $parsed;
    }

    /** 검색 시 실시간 공공 API 호출 여부 (auto는 최근 느린 상태를 잠시 우회) */
    private function liveApisEnabled(): bool
    {
        $v = strtolower(trim((string) env('SEARCH_LIVE_APIS', 'auto')));
        if ($v === 'auto') {
            $pdo = $this->db();
            if ($pdo !== null) {
                $stmt = $pdo->prepare(
                    "SELECT synced_at, note FROM sync_meta WHERE source = 'search:live_api' LIMIT 1"
                );
                $stmt->execute();
                $health = $stmt->fetch();
                if ($health && strpos((string) ($health['note'] ?? ''), 'slow') === 0
                    && (microtime(true) - (float) $health['synced_at']) < 300) {
                    return false;
                }
            }

            return true;
        }

        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /** 실시간 API가 느리면 auto 모드에서 5분간 DB 캐시만 사용 */
    private function recordLiveApiHealth(float $elapsed): void
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return;
        }
        $limit = max(3, (float) env('LIVE_API_SLOW_SECONDS', 6));
        $note = $elapsed >= $limit ? 'slow ' . round($elapsed, 2) . 's' : 'healthy ' . round($elapsed, 2) . 's';
        $stmt = $pdo->prepare(
            "INSERT INTO sync_meta(source,synced_at,note) VALUES('search:live_api',:time,:note)
             ON CONFLICT(source) DO UPDATE SET synced_at=excluded.synced_at,note=excluded.note"
        );
        $stmt->execute(['time' => microtime(true), 'note' => $note]);
    }

    /** Gemini 자연어 보강 사용 여부 */
    private function geminiSearchEnabled(): bool
    {
        $v = env('GEMINI_SEARCH', 'true');

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 규칙 파서만으로 의도가 충분하면 Gemini 호출 생략 (~1초 절약)
     */
    private function shouldEnrichWithGemini(array $parsed): bool
    {
        if (!$this->geminiSearchEnabled() || $this->gemini === null || !$this->gemini->isConfigured()) {
            return false;
        }
        if (($parsed['topics'] ?? []) !== []) {
            return false;
        }
        $keywords = array_values(array_filter($parsed['keywords'] ?? [], static fn($w) => mb_strlen((string) $w) >= 2));
        if ($keywords !== [] && (($parsed['ageMin'] ?? null) !== null || ($parsed['area'] ?? '') !== '')) {
            return false;
        }

        return true;
    }

    /**
     * Gemini로 자연어 의도·키워드 보강 (예: "독립하고 싶어" → 주거/자립)
     */
    private function enrichWithGemini(array $parsed): array
    {
        if ($this->gemini === null || !$this->gemini->isConfigured()) {
            return $parsed;
        }

        $intent = $this->gemini->extractIntent((string)($parsed['query'] ?? ''));
        if ($intent === null) {
            return $parsed;
        }

        $topicAllow = array_keys(self::TOPIC_GROUPS);
        $geminiTopics = [];
        foreach ($intent['topics'] ?? [] as $topic) {
            $topic = trim((string)$topic);
            if (in_array($topic, $topicAllow, true)) {
                $geminiTopics[] = $topic;
            }
        }

        $geminiKeywords = [];
        foreach ($intent['keywords'] ?? [] as $word) {
            $word = trim((string)$word);
            if (mb_strlen($word) >= 2 && !in_array($word, self::STOPWORDS, true)) {
                $geminiKeywords[] = $word;
            }
        }
        $geminiKeywords = array_values(array_unique($geminiKeywords));

        $geminiCategories = [];
        foreach ($intent['categories'] ?? [] as $code) {
            $code = strtoupper(trim((string)$code));
            if (isset(self::CATEGORY[$code])) {
                $geminiCategories[] = $code;
            }
        }

        $geminiArea = trim((string)($intent['area'] ?? ''));
        if ($geminiArea !== '' && !in_array($geminiArea, self::AREAS, true) && $geminiArea !== '광주') {
            // "전남·광주" 같은 값은 무시
            $geminiArea = '';
            foreach (self::AREAS as $name) {
                if (mb_strpos((string)($intent['area'] ?? ''), $name) !== false) {
                    $geminiArea = $name;
                    break;
                }
            }
            if ($geminiArea === '' && mb_strpos((string)($intent['area'] ?? ''), '광주') !== false) {
                $geminiArea = '광주';
            }
        }

        $ageMin = $intent['ageMin'] ?? null;
        $ageMax = $intent['ageMax'] ?? null;
        if ($ageMin !== null && $ageMin !== '') {
            $ageMin = (int)$ageMin;
        } else {
            $ageMin = null;
        }
        if ($ageMax !== null && $ageMax !== '') {
            $ageMax = (int)$ageMax;
        } else {
            $ageMax = null;
        }

        if ($parsed['topics'] === [] && $geminiTopics !== []) {
            $parsed['topics'] = $geminiTopics;
        } elseif ($geminiTopics !== []) {
            $parsed['topics'] = array_values(array_unique(array_merge($parsed['topics'], $geminiTopics)));
        }

        if ($parsed['area'] === '' && $geminiArea !== '') {
            // 사용자가 말한 지역만 적용 (모델이 임의로 광주 등을 넣지 않게)
            if (mb_strpos((string)($parsed['query'] ?? ''), $geminiArea) !== false) {
                $parsed['area'] = $geminiArea;
            }
        }

        if ($parsed['ageMin'] === null && $ageMin !== null) {
            $parsed['ageMin'] = $ageMin;
            $parsed['ageMax'] = $ageMax ?? $ageMin;
        }

        if ($geminiCategories !== []) {
            // 규칙이 기본값 B만 넣은 경우 Gemini 결과를 우선
            if ($parsed['categories'] === ['B'] || $parsed['categories'] === []) {
                $parsed['categories'] = $geminiCategories;
            } else {
                $parsed['categories'] = array_values(array_unique(array_merge($parsed['categories'], $geminiCategories)));
            }
        }

        if ($geminiKeywords !== []) {
            $parsed['searchTerms'] = array_values(array_unique(array_merge(
                $parsed['searchTerms'] ?? [],
                $geminiKeywords,
                $parsed['keywords'] ?? []
            )));
            // 필터용 keywords는 Gemini 확장어 (OR 매칭)
            $parsed['keywords'] = $geminiKeywords;
        }

        if ($parsed['depart'] === '' && ($parsed['topics'][0] ?? '') !== '') {
            foreach (self::DEPART_WORDS as $code => $words) {
                if ($this->containsAny($parsed['topics'][0], $words) || in_array($parsed['topics'][0], $words, true)) {
                    $parsed['depart'] = $code;
                    break;
                }
            }
            // topics 이름으로 매핑
            $topicDepart = ['주거' => 'D', '창업' => 'F', '취업' => 'A', '금융' => 'C', '재직' => 'B', '기업' => 'E'];
            if ($parsed['depart'] === '' && isset($topicDepart[$parsed['topics'][0]])) {
                $parsed['depart'] = $topicDepart[$parsed['topics'][0]];
            }
        }

        $summary = trim((string)($intent['summary'] ?? ''));
        if ($summary !== '') {
            $parsed['summary'] = $summary;
        }
        $parsed['intentSource'] = 'gemini';

        return $parsed;
    }

    private function collectItems(array $parsed): array
    {
        $items = [];
        $seen = [];

        foreach ($this->dbSearch($parsed) as $item) {
            $key = ($item['kind'] ?? '') . ':' . ($item['jobKey'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $bonus = 0;
            if (($item['kind'] ?? '') === 'support') {
                $bonus = 8;
            } elseif (($item['kind'] ?? '') === 'youth') {
                $bonus = 6;
            } elseif (($item['kind'] ?? '') === 'gov24') {
                $bonus = 7;
            }
            $item['_score'] = $this->score($item, $parsed) + $bonus;
            $items[] = $item;
        }

        $liveStartedAt = microtime(true);
        if (!$this->liveApisEnabled()) {
            return $items;
        }

        // 전남 일자리 정책/지원 API (getSupportList)
        foreach ($this->fetchSupportPolicies($parsed) as $item) {
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = ($item['kind'] ?? 'support') . ':' . ($item['jobKey'] ?? '');
            if ($key === 'support:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed) + 20;
            $items[] = $item;
        }

        // 온통청년 실시간 API
        foreach ($this->fetchYouthPolicies($parsed) as $item) {
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = 'youth:' . ($item['jobKey'] ?? '');
            if ($key === 'youth:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed) + 12;
            $items[] = $item;
        }

        // 전남 공공일자리 API (getGovjobList)
        foreach ($this->fetchGovjobPolicies($parsed) as $item) {
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = 'govjob:' . ($item['jobKey'] ?? '');
            if ($key === 'govjob:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed) + 10;
            $items[] = $item;
        }

        // 정부24 공공서비스 API (serviceList + supportConditions)
        foreach ($this->fetchGov24Policies($parsed) as $item) {
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = 'gov24:' . ($item['jobKey'] ?? '');
            if ($key === 'gov24:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed) + 14;
            $items[] = $item;
        }

        // 전남 지원프로그램 (희망버스·잡매칭·만남의날·상담센터)
        foreach ($this->fetchProgramPolicies($parsed) as $item) {
            if (($item['kind'] ?? '') === 'hopebus'
                && $parsed['ageMin'] !== null
                && $parsed['ageMin'] > 39) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = ($item['kind'] ?? 'hopebus') . ':' . ($item['jobKey'] ?? '');
            if (substr($key, -1) === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed) + 8;
            $items[] = $item;
        }

        // 지자체복지 · 중앙부처복지
        foreach ($this->fetchWelfarePolicies($parsed) as $item) {
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $key = ($item['kind'] ?? 'lcgv') . ':' . ($item['jobKey'] ?? '');
            if (substr($key, -1) === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $bonus = ($item['kind'] ?? '') === 'lcgv' ? 13 : 9;
            $item['_score'] = $this->score($item, $parsed) + $bonus;
            $items[] = $item;
        }

        $this->recordLiveApiHealth(microtime(true) - $liveStartedAt);
        return $items;
    }

    private function db(): ?PDO
    {
        if (!is_file($this->dbPath)) {
            return null;
        }

        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $pdo = new PDO('sqlite:' . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }

    private function dbSearch(array $parsed): array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return [];
        }

        $sql = 'SELECT * FROM program';
        $rows = $pdo->query($sql)->fetchAll();

        $youthAges = [];
        foreach ($pdo->query('SELECT plcy_no, age_min, age_max, age_lmt_yn FROM youth_policy') as $row) {
            $youthAges[$row['plcy_no']] = $row;
        }
        $govAges = [];
        foreach ($pdo->query('SELECT service_id, age_start, age_end FROM gov24_condition') as $row) {
            $govAges[$row['service_id']] = $row;
        }

        $out = [];
        foreach ($rows as $row) {
            $item = $this->rowToItem($row);

            if ($item['kind'] === 'youth' && isset($youthAges[$item['jobKey']])) {
                $extra = $youthAges[$item['jobKey']];
                $item['ageMin'] = $extra['age_min'] !== null ? (int)$extra['age_min'] : null;
                $item['ageMax'] = $extra['age_max'] !== null ? (int)$extra['age_max'] : null;
                $item['ageLmtYn'] = $extra['age_lmt_yn'];
            }
            if ($item['kind'] === 'gov24' && isset($govAges[$item['jobKey']])) {
                $cond = $govAges[$item['jobKey']];
                if ($cond['age_start'] !== null || $cond['age_end'] !== null) {
                    $item['ageMin'] = $cond['age_start'] !== null ? (int)$cond['age_start'] : null;
                    $item['ageMax'] = $cond['age_end'] !== null ? (int)$cond['age_end'] : null;
                }
            }

            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $out[] = $item;
        }

        // 별도 크롤러가 저장한 공고도 API 캐시와 동일하게 검색
        $crawlRows = [];
        $hasCrawlTable = $pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'crawl_notice' LIMIT 1"
        )->fetchColumn();
        if ($hasCrawlTable) {
            $crawlRows = $pdo->query('SELECT * FROM crawl_notice ORDER BY collected_at DESC')->fetchAll();
        }
        foreach ($crawlRows as $row) {
            $item = $this->crawlRowToItem($row);
            if (!$this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (!$this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (!$this->periodOk($item)) {
                continue;
            }
            if (!$this->itemAllowed($item, $parsed)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    private function dbFind(string $kind, string $jobKey): ?array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return null;
        }
        if ($kind === 'crawl') {
            $id = (int) $jobKey;
            $stmt = $pdo->prepare('SELECT * FROM crawl_notice WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            return $row ? $this->crawlRowToItem($row) : null;
        }
        $stmt = $pdo->prepare('SELECT * FROM program WHERE kind = ? AND job_key = ? LIMIT 1');
        $stmt->execute([$kind, $jobKey]);
        $row = $stmt->fetch();

        return $row ? $this->rowToItem($row) : null;
    }

    private function dbFiles(string $kind, string $jobKey): array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return [];
        }
        if ($kind === 'crawl') {
            $stmt = $pdo->prepare('SELECT file_name, file_url FROM crawl_attachment WHERE notice_id = ?');
            $stmt->execute([(int) $jobKey]);
            $files = [];
            foreach ($stmt->fetchAll() as $row) {
                $files[] = [
                    'jobFileNm' => $row['file_name'] ?: '첨부파일',
                    'jobFileUrl' => $row['file_url'],
                ];
            }

            return $files;
        }
        $stmt = $pdo->prepare('SELECT file_nm, file_url FROM program_file WHERE kind = ? AND job_key = ?');
        $stmt->execute([$kind, $jobKey]);
        $files = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!empty($row['file_url'])) {
                $files[] = [
                    'jobFileNm' => $row['file_nm'] ?: '첨부파일',
                    'jobFileUrl' => $row['file_url'],
                ];
            }
        }

        return $files;
    }

    private function enrichDetail(array $item): array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return $item;
        }

        $kind = $item['kind'] ?? '';
        $key = $item['jobKey'] ?? '';

        if ($kind === 'youth') {
            $stmt = $pdo->prepare('SELECT * FROM youth_policy WHERE plcy_no = ?');
            $stmt->execute([$key]);
            $extra = $stmt->fetch();
            if ($extra) {
                $chunks = array_filter([
                    $extra['support_cn'] ?? '',
                    $extra['apply_mthd_cn'] ?? '',
                    $extra['documents'] ?? '',
                ]);
                if ($chunks !== []) {
                    $item['jobContent'] = implode("\n\n", $chunks);
                }
                if (!empty($extra['apply_url'])) {
                    $item['jobLink'] = $extra['apply_url'];
                }
            }
        } elseif ($kind === 'gov24') {
            $stmt = $pdo->prepare('SELECT * FROM gov24_detail WHERE service_id = ?');
            $stmt->execute([$key]);
            $extra = $stmt->fetch();
            if ($extra) {
                $chunks = array_filter([
                    $extra['purpose'] ?? '',
                    $extra['support_cn'] ?? '',
                    $extra['apply_cn'] ?? '',
                    $extra['documents'] ?? '',
                    $extra['law_nm'] ?? '',
                ]);
                if ($chunks !== []) {
                    $item['jobContent'] = implode("\n\n", $chunks);
                }
                if (!empty($extra['apply_url'])) {
                    $item['jobLink'] = $extra['apply_url'];
                }
            }
        } elseif (in_array($kind, ['lcgv', 'nwlf'], true)) {
            $stmt = $pdo->prepare('SELECT * FROM welfare_detail WHERE kind = ? AND serv_id = ?');
            $stmt->execute([$kind, $key]);
            $extra = $stmt->fetch();
            if ($extra) {
                $chunks = array_filter([
                    $extra['target_cn'] ?? '',
                    $extra['select_cn'] ?? '',
                    $extra['support_cn'] ?? '',
                    $extra['apply_cn'] ?? '',
                ]);
                if ($chunks !== []) {
                    $item['jobContent'] = implode("\n\n", $chunks);
                }
                if (!empty($extra['phone'])) {
                    $item['jobManagerTel'] = $extra['phone'];
                }
                if (!empty($extra['ministry'])) {
                    $item['jobManager'] = $extra['ministry'];
                }
            }
        }

        return $item;
    }

    private function rowToItem(array $row): array
    {
        $extra = [];
        if (!empty($row['extra_json'])) {
            $decoded = json_decode($row['extra_json'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $item = [
            'kind' => $row['kind'],
            'jobKey' => $row['job_key'],
            'jobTitle' => $row['title'],
            'jobArea' => $row['area'],
            'jobCategoryLabel' => $row['category_label'],
            'jobDepartLabel' => $row['depart_label'],
            'jobApplyLabel' => $row['apply_label'],
            'jobTarget' => $row['target'],
            'jobContent' => $row['content'],
            'jobStartDt' => $row['start_dt'],
            'jobEndDt' => $row['end_dt'],
            'jobManager' => $row['manager'],
            'jobManagerTel' => $row['manager_tel'],
            'jobLink' => $row['link'],
            'jobReadCnt' => $row['read_cnt'],
            'jobWriter' => $row['manager'],
        ];

        return array_merge($item, $extra);
    }

    private function crawlRowToItem(array $row): array
    {
        return [
            'kind' => 'crawl',
            'jobKey' => (string) $row['id'],
            'jobTitle' => $row['title'] ?? '',
            'jobArea' => $row['region'] ?? '',
            'jobCategoryLabel' => $row['category'] ?? '',
            'jobDepartLabel' => '크롤링 공고',
            'jobApplyLabel' => '',
            'jobTarget' => $row['residence_condition'] ?? '',
            'jobContent' => $row['body_text'] ?? '',
            'jobStartDt' => $row['apply_start'] ?? '',
            'jobEndDt' => $row['apply_end'] ?? '',
            'jobManager' => $row['organizer'] ?? ($row['operator'] ?? ''),
            'jobManagerTel' => $row['contact'] ?? '',
            'jobLink' => $row['original_url'] ?? '',
            'jobWriter' => $row['operator'] ?? '',
            'sourceName' => $row['source_name'] ?? '',
            'sourceId' => $row['source_id'] ?? '',
            'aiStatus' => $row['ai_status'] ?? 'pending',
        ];
    }

    /**
     * 온통청년 getPlcy 실시간 검색
     */
    private function fetchYouthPolicies(array $parsed): array
    {
        if ($this->youthKey === '') {
            return [];
        }

        $queries = $this->youthQueryPlans($parsed);
        $out = [];
        $seen = [];

        foreach ($queries as $params) {
            $rows = $this->youthList($params);
            foreach ($rows as $raw) {
                $item = $this->youthToItem($raw);
                $key = $item['jobKey'] ?? '';
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return $out;
    }

    private function youthQueryPlans(array $parsed): array
    {
        $topics = $parsed['topics'] ?? [];
        $keywords = $parsed['searchTerms'] ?? $parsed['keywords'] ?? [];
        $plans = [];

        if (in_array('주거', $topics, true)) {
            $plans[] = ['lclsfNm' => '주거', 'pageSize' => 50];
        }
        if (in_array('취업', $topics, true) || ($topics === [] && $keywords === [])) {
            $plans[] = ['lclsfNm' => '일자리', 'pageSize' => 50];
        }
        if (in_array('금융', $topics, true)) {
            $plans[] = ['lclsfNm' => '금융', 'pageSize' => 30];
        }
        if (in_array('창업', $topics, true)) {
            $plans[] = ['plcyNm' => '창업', 'pageSize' => 30];
        }

        foreach (array_slice($keywords, 0, 3) as $nameSeed) {
            $nameSeed = trim((string)$nameSeed);
            if ($nameSeed !== '') {
                $plans[] = ['plcyNm' => $nameSeed, 'pageSize' => 30];
            }
        }

        if ($plans === []) {
            $plans[] = ['lclsfNm' => '일자리', 'pageSize' => 40];
            $plans[] = ['lclsfNm' => '주거', 'pageSize' => 40];
        }

        // 중복 제거
        $unique = [];
        $seen = [];
        foreach ($plans as $plan) {
            $sig = json_encode($plan, JSON_UNESCAPED_UNICODE);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $unique[] = $plan;
        }

        return array_slice($unique, 0, 5);
    }

    private function youthList(array $params): array
    {
        $query = array_merge([
            'apiKeyNm' => $this->youthKey,
            'rtnType' => 'json',
            'pageNum' => 1,
            'pageSize' => 50,
        ], $params);

        $url = self::YOUTH_URL . '?' . http_build_query($query);

        try {
            $client = service('curlrequest');
            $response = $client->get($url, [
                'timeout' => 20,
                'http_errors' => false,
                'headers' => ['User-Agent' => 'jn-support-ci4/1.0'],
            ]);
            $data = json_decode((string)$response->getBody(), true);
        } catch (Throwable $e) {
            log_message('error', 'youth API: ' . $e->getMessage());

            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $list = $data['result']['youthPolicyList'] ?? null;

        return is_array($list) ? $list : [];
    }

    private function fetchYouthDetail(string $plcyNo): ?array
    {
        if ($this->youthKey === '' || $plcyNo === '') {
            return null;
        }
        $rows = $this->youthList([
            'plcyNo' => $plcyNo,
            'pageSize' => 1,
        ]);
        if ($rows === []) {
            return null;
        }

        return $this->youthToItem($rows[0]);
    }

    private function youthToItem(array $raw): array
    {
        $ageMin = isset($raw['sprtTrgtMinAge']) && $raw['sprtTrgtMinAge'] !== ''
            ? (int)$raw['sprtTrgtMinAge'] : null;
        $ageMax = isset($raw['sprtTrgtMaxAge']) && $raw['sprtTrgtMaxAge'] !== ''
            ? (int)$raw['sprtTrgtMaxAge'] : null;
        $ageBit = ($ageMin !== null && $ageMax !== null) ? "{$ageMin}~{$ageMax}세" : '';
        $cls = (string)($raw['lclsfNm'] ?? '');
        $keyword = (string)($raw['plcyKywdNm'] ?? '');

        return [
            'kind' => 'youth',
            'jobKey' => (string)($raw['plcyNo'] ?? ''),
            'jobTitle' => (string)($raw['plcyNm'] ?? ''),
            'jobArea' => (string)($raw['sprvsnInstCdNm'] ?? ''),
            'jobCategoryLabel' => '온통청년',
            'jobDepartLabel' => $cls !== '' ? $cls : (string)($raw['mclsfNm'] ?? ''),
            'jobApplyLabel' => trim((string)($raw['aplyYmd'] ?? '')),
            'jobTarget' => implode(' · ', array_filter([$cls, $ageBit, $keyword])),
            'jobContent' => (string)($raw['plcyExplnCn'] ?? $raw['plcySprtCn'] ?? ''),
            'jobStartDt' => trim((string)($raw['aplyYmd'] ?? '')),
            'jobEndDt' => trim((string)($raw['bizPrdEtcCn'] ?? '')),
            'jobManager' => (string)($raw['operInstPicNm'] ?? $raw['sprvsnInstPicNm'] ?? ''),
            'jobLink' => (string)($raw['aplyUrlAddr'] ?? $raw['refUrlAddr1'] ?? ''),
            'jobReadCnt' => isset($raw['inqCnt']) ? (int)$raw['inqCnt'] : 0,
            'jobWriter' => (string)($raw['sprvsnInstCdNm'] ?? ''),
            'ageMin' => $ageMin,
            'ageMax' => $ageMax,
            'ageLmtYn' => (string)($raw['sprtTrgtAgeLmtYn'] ?? ''),
        ];
    }

    /**
     * 정부24 공공서비스 — serviceList (+ 필요 시 supportConditions 나이)
     */
    private function fetchGov24Policies(array $parsed): array
    {
        if ($this->serviceKey === '') {
            return [];
        }

        $plans = $this->gov24QueryPlans($parsed);
        $out = [];
        $seen = [];

        foreach ($plans as $params) {
            $rows = $this->gov24List($params);
            foreach ($rows as $raw) {
                $item = $this->gov24ListToItem($raw);
                $key = $item['jobKey'] ?? '';
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return $out;
    }

    private function gov24QueryPlans(array $parsed): array
    {
        $orgSeeds = [];
        $area = (string)($parsed['area'] ?? '');
        if ($area !== '') {
            $orgSeeds[] = $area;
        }
        $orgSeeds[] = '전남';
        $orgSeeds[] = '광주';

        $fieldSeeds = [];
        foreach ($parsed['topics'] ?? [] as $topic) {
            if (isset(self::GOV24_FIELD[$topic])) {
                $fieldSeeds[] = self::GOV24_FIELD[$topic];
            }
        }

        $nameSeeds = [];
        foreach ($parsed['searchTerms'] ?? $parsed['keywords'] ?? [] as $word) {
            $nameSeeds[] = $word;
        }
        foreach ($parsed['topics'] ?? [] as $topic) {
            $nameSeeds[] = $topic;
        }
        if (in_array('B', $parsed['categories'] ?? [], true)) {
            $nameSeeds[] = '청년';
        }

        $plans = [];
        // 기관(전남/광주) + 분야
        foreach (array_slice(array_values(array_unique($orgSeeds)), 0, 2) as $org) {
            if ($fieldSeeds !== []) {
                foreach (array_slice($fieldSeeds, 0, 2) as $field) {
                    $plans[] = [
                        'cond[소관기관명::LIKE]' => $org,
                        'cond[서비스분야::LIKE]' => $field,
                        'perPage' => 40,
                    ];
                }
            } else {
                $plans[] = [
                    'cond[소관기관명::LIKE]' => $org,
                    'perPage' => 30,
                ];
            }
        }

        // 서비스명 키워드 (전남 한정)
        foreach (array_slice(array_values(array_unique($nameSeeds)), 0, 2) as $name) {
            $plans[] = [
                'cond[소관기관명::LIKE]' => '전남',
                'cond[서비스명::LIKE]' => $name,
                'perPage' => 30,
            ];
        }

        if ($plans === []) {
            $plans[] = [
                'cond[소관기관명::LIKE]' => '전남',
                'perPage' => 30,
            ];
        }

        $unique = [];
        $seen = [];
        foreach ($plans as $plan) {
            $sig = json_encode($plan, JSON_UNESCAPED_UNICODE);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $unique[] = $plan;
        }

        return array_slice($unique, 0, 4);
    }

    private function gov24List(array $params): array
    {
        $query = array_merge([
            'page' => 1,
            'perPage' => 30,
            'returnType' => 'JSON',
        ], $params);

        $data = $this->fetchGov24Json(self::GOV24_BASE . '/serviceList', $query);
        if ($data === null) {
            return [];
        }
        $list = $data['data'] ?? null;

        return is_array($list) ? $list : [];
    }

    private function fetchGov24Detail(string $serviceId): ?array
    {
        if ($this->serviceKey === '' || $serviceId === '') {
            return null;
        }

        $data = $this->fetchGov24Json(self::GOV24_BASE . '/serviceDetail', [
            'page' => 1,
            'perPage' => 1,
            'returnType' => 'JSON',
            'cond[서비스ID::EQ]' => $serviceId,
        ]);
        $rows = is_array($data['data'] ?? null) ? $data['data'] : [];
        if ($rows === []) {
            return null;
        }
        $item = $this->gov24DetailToItem($rows[0]);

        $cond = $this->fetchGov24Conditions($serviceId);
        if ($cond !== null) {
            if ($cond['ageMin'] !== null || $cond['ageMax'] !== null) {
                $item['ageMin'] = $cond['ageMin'];
                $item['ageMax'] = $cond['ageMax'];
            }
        }

        return $item;
    }

    private function fetchGov24Conditions(string $serviceId): ?array
    {
        if ($this->serviceKey === '' || $serviceId === '') {
            return null;
        }

        $data = $this->fetchGov24Json(self::GOV24_BASE . '/supportConditions', [
            'page' => 1,
            'perPage' => 1,
            'returnType' => 'JSON',
            'cond[서비스ID::EQ]' => $serviceId,
        ]);
        $rows = is_array($data['data'] ?? null) ? $data['data'] : [];
        if ($rows === []) {
            return null;
        }
        $raw = $rows[0];
        $ageMin = isset($raw['JA0110']) && $raw['JA0110'] !== '' && $raw['JA0110'] !== null
            ? (int)$raw['JA0110'] : null;
        $ageMax = isset($raw['JA0111']) && $raw['JA0111'] !== '' && $raw['JA0111'] !== null
            ? (int)$raw['JA0111'] : null;

        return [
            'ageMin' => $ageMin,
            'ageMax' => $ageMax,
            'raw' => $raw,
        ];
    }

    private function gov24ListToItem(array $raw): array
    {
        $field = (string)($raw['서비스분야'] ?? '');
        $target = (string)($raw['지원대상'] ?? '');
        $user = (string)($raw['사용자구분'] ?? '');

        return [
            'kind' => 'gov24',
            'jobKey' => (string)($raw['서비스ID'] ?? ''),
            'jobTitle' => (string)($raw['서비스명'] ?? ''),
            'jobArea' => (string)($raw['소관기관명'] ?? ''),
            'jobCategoryLabel' => '정부24',
            'jobDepartLabel' => $field,
            'jobApplyLabel' => (string)($raw['신청방법'] ?? ''),
            'jobTarget' => implode(' · ', array_filter([$user, $field, $target])),
            'jobContent' => (string)($raw['지원내용'] ?? $raw['서비스목적요약'] ?? ''),
            'jobStartDt' => (string)($raw['신청기한'] ?? ''),
            'jobEndDt' => '',
            'jobManager' => (string)($raw['부서명'] ?? $raw['소관기관명'] ?? ''),
            'jobManagerTel' => (string)($raw['전화문의'] ?? ''),
            'jobLink' => (string)($raw['상세조회URL'] ?? ''),
            'jobReadCnt' => isset($raw['조회수']) ? (int)$raw['조회수'] : 0,
            'jobWriter' => (string)($raw['접수기관'] ?? $raw['소관기관명'] ?? ''),
        ];
    }

    private function gov24DetailToItem(array $raw): array
    {
        $chunks = array_filter([
            (string)($raw['서비스목적'] ?? ''),
            (string)($raw['지원내용'] ?? ''),
            (string)($raw['신청방법'] ?? ''),
            (string)($raw['구비서류'] ?? ''),
            (string)($raw['법령'] ?? ''),
        ]);
        $link = (string)($raw['온라인신청사이트URL'] ?? '');
        if ($link === '') {
            $id = (string)($raw['서비스ID'] ?? '');
            if ($id !== '') {
                $link = 'https://www.gov.kr/portal/rcvfvrSvc/dtlEx/' . rawurlencode($id);
            }
        }

        return [
            'kind' => 'gov24',
            'jobKey' => (string)($raw['서비스ID'] ?? ''),
            'jobTitle' => (string)($raw['서비스명'] ?? ''),
            'jobArea' => (string)($raw['소관기관명'] ?? ''),
            'jobCategoryLabel' => '정부24',
            'jobDepartLabel' => (string)($raw['지원유형'] ?? ''),
            'jobApplyLabel' => (string)($raw['신청방법'] ?? ''),
            'jobTarget' => (string)($raw['지원대상'] ?? ''),
            'jobContent' => implode("\n\n", $chunks),
            'jobStartDt' => (string)($raw['신청기한'] ?? ''),
            'jobEndDt' => '',
            'jobManager' => (string)($raw['접수기관명'] ?? $raw['소관기관명'] ?? ''),
            'jobManagerTel' => (string)($raw['문의처'] ?? ''),
            'jobLink' => $link,
            'jobReadCnt' => 0,
            'jobWriter' => (string)($raw['소관기관명'] ?? ''),
        ];
    }

    private function fetchGov24Json(string $url, array $params): ?array
    {
        $query = ['serviceKey' => $this->serviceKey] + $params;
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        $full = $url . '?' . implode('&', $parts);

        try {
            $client = service('curlrequest');
            $response = $client->get($full, [
                'timeout' => 25,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'jn-support-ci4/1.0',
                    'Accept' => 'application/json, */*',
                ],
            ]);
            $body = (string)$response->getBody();
        } catch (Throwable $e) {
            log_message('error', 'gov24 API: ' . $e->getMessage());

            return null;
        }

        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            log_message('error', 'gov24 API invalid JSON: ' . mb_substr($body, 0, 200));

            return null;
        }
        if (isset($data['code']) && (int)$data['code'] < 0) {
            log_message('error', 'gov24 API auth/error: ' . ($data['msg'] ?? json_encode($data)));

            return null;
        }

        return $data;
    }

    private function programPath(string $kind): string
    {
        foreach (self::PROGRAMS as [$progKind, , $path]) {
            if ($progKind === $kind) {
                return ltrim($path, '/');
            }
        }

        return 'getHopeBusList';
    }

    /**
     * 전남 지원프로그램 — hopebus / jobmatch / meetday / center
     */
    private function fetchProgramPolicies(array $parsed): array
    {
        // 주거 검색에는 채용·행사성 프로그램이 덜 맞아서 제외
        if (in_array('주거', $parsed['topics'] ?? [], true)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach (self::PROGRAMS as [$kind, $label, $path]) {
            $result = $this->fetchXml(self::PROGRAM_BASE . $path, [
                'startPage' => '1',
                'pageSize' => '50',
                'numOfRows' => '50',
            ], 'program:' . $kind . '|' . $label);
            if (($result['resultCode'] ?? '') !== '00') {
                continue;
            }
            foreach ($result['items'] ?? [] as $item) {
                $key = $kind . ':' . ($item['jobKey'] ?? '');
                if (substr($key, -1) === ':' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return $out;
    }

    private function fetchProgramDetail(string $kind, string $jobKey): ?array
    {
        foreach (self::PROGRAMS as [$progKind, $label, $path]) {
            if ($progKind !== $kind) {
                continue;
            }
            $result = $this->fetchXml(self::PROGRAM_BASE . $path, [
                'startPage' => '1',
                'pageSize' => '50',
                'numOfRows' => '50',
            ], 'program:' . $kind . '|' . $label);
            foreach ($result['items'] ?? [] as $item) {
                if (($item['jobKey'] ?? '') === $jobKey) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * 지자체복지(Lcgv) + 중앙부처복지(Nwlf) 실시간 목록
     */
    private function fetchWelfarePolicies(array $parsed): array
    {
        if ($this->serviceKey === '') {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($this->fetchLcgvList($parsed) as $item) {
            $key = 'lcgv:' . ($item['jobKey'] ?? '');
            if ($key === 'lcgv:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }

        foreach ($this->fetchNwlfList($parsed) as $item) {
            $key = 'nwlf:' . ($item['jobKey'] ?? '');
            if ($key === 'nwlf:' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }

        return $out;
    }

    private function welfareSearchWords(array $parsed): array
    {
        $words = [];
        foreach ($parsed['topics'] ?? [] as $topic) {
            foreach (self::WELFARE_TOPIC_WORDS[$topic] ?? [$topic] as $word) {
                $words[] = $word;
            }
        }
        foreach ($parsed['searchTerms'] ?? $parsed['keywords'] ?? [] as $word) {
            $words[] = $word;
        }
        $words = array_values(array_unique(array_filter($words)));

        return $words !== [] ? array_slice($words, 0, 4) : [''];
    }

    private function fetchLcgvList(array $parsed): array
    {
        $base = [
            'pageNo' => '1',
            'numOfRows' => '50',
            'ctpvNm' => '전남',
        ];
        $ageMin = $parsed['ageMin'] ?? null;
        if ($ageMin !== null && $ageMin <= 39) {
            $base['lifeArray'] = '004'; // 청년
        } elseif ($ageMin !== null && $ageMin >= 40) {
            $base['lifeArray'] = '005'; // 중장년
        }

        $out = [];
        $seen = [];
        foreach ($this->welfareSearchWords($parsed) as $word) {
            $params = $base;
            if ($word !== '') {
                $params['searchWrd'] = $word;
            }
            $rows = $this->fetchWelfareXml(self::LCGV_BASE . '/LcgvWelfarelist', $params, 'lcgv');
            foreach ($rows as $item) {
                $key = $item['jobKey'] ?? '';
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return $out;
    }

    private function fetchNwlfList(array $parsed): array
    {
        $out = [];
        $seen = [];
        $words = $this->welfareSearchWords($parsed);
        foreach ($words as $word) {
            $params = [
                'pageNo' => '1',
                'numOfRows' => '40',
                'callTp' => 'L',
                'srchKeyCode' => $word !== '' ? '003' : '001',
            ];
            if ($word !== '') {
                $params['searchWrd'] = $word;
            }
            $rows = $this->fetchWelfareXml(self::NWLF_BASE . '/NationalWelfarelistV001', $params, 'nwlf');
            foreach ($rows as $item) {
                $key = $item['jobKey'] ?? '';
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return array_slice($out, 0, 80);
    }

    private function fetchWelfareDetail(string $kind, string $servId): ?array
    {
        if ($this->serviceKey === '' || $servId === '') {
            return null;
        }

        if ($kind === 'lcgv') {
            $url = self::LCGV_BASE . '/LcgvWelfaredetailed';
            $xmlText = $this->welfareHttpGet($url, ['servId' => $servId]);
            if ($xmlText === '') {
                return null;
            }
            try {
                $root = new SimpleXMLElement($xmlText);
            } catch (Throwable $e) {
                return null;
            }
            $area = trim(implode(' ', array_filter([
                $this->xmlText($root, 'ctpvNm'),
                $this->xmlText($root, 'sggNm'),
            ])));
            $chunks = array_filter([
                $this->xmlText($root, 'servDgst'),
                $this->xmlText($root, 'sprtTrgtCn'),
                $this->xmlText($root, 'slctCritCn'),
                $this->xmlText($root, 'alwServCn'),
                $this->xmlText($root, 'aplyMtdCn'),
            ]);
            $id = $this->xmlText($root, 'servId') ?: $servId;

            return [
                'kind' => 'lcgv',
                'jobKey' => $id,
                'jobTitle' => $this->xmlText($root, 'servNm'),
                'jobArea' => $area,
                'jobCategoryLabel' => '지자체복지',
                'jobDepartLabel' => $this->xmlText($root, 'intrsThemaNmArray') ?: $this->xmlText($root, 'lifeNmArray'),
                'jobApplyLabel' => $this->xmlText($root, 'aplyMtdNm'),
                'jobTarget' => implode(' · ', array_filter([
                    $this->xmlText($root, 'lifeNmArray'),
                    $this->xmlText($root, 'trgterIndvdlNmArray'),
                    $this->xmlText($root, 'intrsThemaNmArray'),
                ])),
                'jobContent' => implode("\n\n", $chunks),
                'jobStartDt' => $this->xmlText($root, 'enfcBgngYmd') ?: $this->xmlText($root, 'lastModYmd'),
                'jobEndDt' => $this->xmlText($root, 'enfcEndYmd'),
                'jobManager' => $this->xmlText($root, 'bizChrDeptNm'),
                'jobLink' => html_entity_decode(
                    'https://www.bokjiro.go.kr/ssis-tbu/twataa/wlfareInfo/moveTWAT52011M.do?wlfareInfoId='
                    . rawurlencode($id) . '&wlfareInfoReldBztpCd=02',
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                ),
                'jobReadCnt' => (int)$this->xmlText($root, 'inqNum'),
            ];
        }

        $url = self::NWLF_BASE . '/NationalWelfaredetailedV001';
        $xmlText = $this->welfareHttpGet($url, ['servId' => $servId]);
        if ($xmlText === '') {
            return null;
        }
        try {
            $root = new SimpleXMLElement($xmlText);
        } catch (Throwable $e) {
            return null;
        }

        $applyParts = [];
        if (isset($root->applmetList)) {
            foreach ($root->applmetList as $node) {
                $nm = $this->xmlText($node, 'servSeDetailNm');
                $link = html_entity_decode($this->xmlText($node, 'servSeDetailLink'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($nm !== '' || $link !== '') {
                    $applyParts[] = trim($nm . ($link !== '' ? ': ' . $link : ''));
                }
            }
        }
        $chunks = array_filter([
            $this->xmlText($root, 'wlfareInfoOutlCn') ?: $this->xmlText($root, 'servDgst'),
            $this->xmlText($root, 'tgtrDtlCn'),
            $this->xmlText($root, 'slctCritCn'),
            $this->xmlText($root, 'alwServCn'),
            implode(' / ', $applyParts),
        ]);
        $id = $this->xmlText($root, 'servId') ?: $servId;

        return [
            'kind' => 'nwlf',
            'jobKey' => $id,
            'jobTitle' => $this->xmlText($root, 'servNm'),
            'jobArea' => $this->xmlText($root, 'jurMnofNm'),
            'jobCategoryLabel' => '중앙복지',
            'jobDepartLabel' => $this->xmlText($root, 'intrsThemaArray') ?: $this->xmlText($root, 'lifeArray'),
            'jobApplyLabel' => $this->xmlText($root, 'onapPsbltYn') === 'Y' ? '온라인' : $this->xmlText($root, 'sprtCycNm'),
            'jobTarget' => implode(' · ', array_filter([
                $this->xmlText($root, 'lifeArray'),
                $this->xmlText($root, 'trgterIndvdlArray'),
                $this->xmlText($root, 'intrsThemaArray'),
            ])),
            'jobContent' => implode("\n\n", $chunks),
            'jobStartDt' => $this->xmlText($root, 'svcfrstRegTs'),
            'jobEndDt' => '',
            'jobManager' => $this->xmlText($root, 'jurOrgNm') ?: $this->xmlText($root, 'jurMnofNm'),
            'jobManagerTel' => $this->xmlText($root, 'rprsCtadr'),
            'jobLink' => html_entity_decode(
                'https://www.bokjiro.go.kr/ssis-tbu/twataa/wlfareInfo/moveTWAT52011M.do?wlfareInfoId='
                . rawurlencode($id) . '&wlfareInfoReldBztpCd=01',
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ),
            'jobReadCnt' => (int)$this->xmlText($root, 'inqNum'),
        ];
    }

    private function fetchWelfareXml(string $url, array $params, string $kind): array
    {
        $xmlText = $this->welfareHttpGet($url, $params);
        if ($xmlText === '') {
            return [];
        }
        try {
            $root = new SimpleXMLElement($xmlText);
        } catch (Throwable $e) {
            return [];
        }

        $code = trim((string)($root->resultCode ?? ''));
        if ($code !== '' && $code !== '0' && $code !== '00') {
            return [];
        }

        $items = [];
        foreach ($root->servList ?? [] as $node) {
            $items[] = $kind === 'nwlf'
                ? $this->nwlfNodeToItem($node)
                : $this->lcgvNodeToItem($node);
        }

        return $items;
    }

    private function lcgvNodeToItem(SimpleXMLElement $node): array
    {
        $life = $this->xmlText($node, 'lifeNmArray');
        $thema = $this->xmlText($node, 'intrsThemaNmArray');
        $area = trim(implode(' ', array_filter([
            $this->xmlText($node, 'ctpvNm'),
            $this->xmlText($node, 'sggNm'),
        ])));

        return [
            'kind' => 'lcgv',
            'jobKey' => $this->xmlText($node, 'servId'),
            'jobTitle' => $this->xmlText($node, 'servNm'),
            'jobArea' => $area,
            'jobCategoryLabel' => '지자체복지',
            'jobDepartLabel' => $thema !== '' ? $thema : $life,
            'jobApplyLabel' => $this->xmlText($node, 'aplyMtdNm'),
            'jobTarget' => implode(' · ', array_filter([
                $life,
                $this->xmlText($node, 'trgterIndvdlNmArray'),
                $thema,
            ])),
            'jobContent' => $this->xmlText($node, 'servDgst'),
            'jobStartDt' => $this->xmlText($node, 'lastModYmd'),
            'jobEndDt' => '',
            'jobManager' => $this->xmlText($node, 'bizChrDeptNm'),
            'jobLink' => html_entity_decode($this->xmlText($node, 'servDtlLink'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'jobReadCnt' => (int)$this->xmlText($node, 'inqNum'),
        ];
    }

    private function nwlfNodeToItem(SimpleXMLElement $node): array
    {
        $thema = $this->xmlText($node, 'intrsThemaArray');
        $life = $this->xmlText($node, 'lifeArray');

        return [
            'kind' => 'nwlf',
            'jobKey' => $this->xmlText($node, 'servId'),
            'jobTitle' => $this->xmlText($node, 'servNm'),
            'jobArea' => $this->xmlText($node, 'jurMnofNm'),
            'jobCategoryLabel' => '중앙복지',
            'jobDepartLabel' => $thema !== '' ? $thema : $life,
            'jobApplyLabel' => $this->xmlText($node, 'onapPsbltYn') === 'Y' ? '온라인' : $this->xmlText($node, 'sprtCycNm'),
            'jobTarget' => implode(' · ', array_filter([
                $life,
                $this->xmlText($node, 'trgterIndvdlArray'),
                $thema,
            ])),
            'jobContent' => $this->xmlText($node, 'servDgst'),
            'jobStartDt' => $this->xmlText($node, 'svcfrstRegTs'),
            'jobEndDt' => '',
            'jobManager' => $this->xmlText($node, 'jurOrgNm'),
            'jobManagerTel' => $this->xmlText($node, 'rprsCtadr'),
            'jobLink' => html_entity_decode($this->xmlText($node, 'servDtlLink'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'jobReadCnt' => (int)$this->xmlText($node, 'inqNum'),
        ];
    }

    private function welfareHttpGet(string $url, array $params): string
    {
        $query = ['serviceKey' => $this->serviceKey] + $params;
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }

        return $this->httpGet($url . '?' . implode('&', $parts));
    }

    /**
     * 전남 일자리 정책/지원 — getSupportList
     */
    private function fetchSupportPolicies(array $parsed): array
    {
        $categories = $parsed['categories'] !== [] ? $parsed['categories'] : ['B'];
        $area = $parsed['area'] ?? '';
        $apiArea = $this->supportApiArea($area);
        $depart = ($parsed['topics'] ?? []) !== [] ? '' : ($parsed['depart'] ?? '');
        $contents = $this->supportContentSeeds($parsed);

        $out = [];
        $seen = [];

        foreach ($categories as $category) {
            foreach ($contents as $content) {
                for ($page = 1; $page <= 3; $page++) {
                    $params = [
                        'startPage' => (string)$page,
                        'pageSize' => '50',
                        'jobCategory' => $category,
                    ];
                    if ($apiArea !== '') {
                        $params['jobArea'] = $apiArea;
                    }
                    if ($depart !== '') {
                        $params['jobDepart'] = $depart;
                    }
                    if ($content !== '') {
                        $params['jobContent'] = $content;
                    }

                    $result = $this->fetchXml(self::SUPPORT_BASE . '/getSupportList', $params, 'list');
                    if (($result['resultCode'] ?? '') !== '00') {
                        break;
                    }
                    $batch = $result['items'] ?? [];
                    if ($batch === []) {
                        break;
                    }
                    foreach ($batch as $item) {
                        $key = $item['jobKey'] ?? '';
                        if ($key === '' || isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $out[] = $item;
                    }
                    $total = (int)($result['totalCount'] ?? 0);
                    if ($page * 50 >= $total) {
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** API jobArea: 광주는 0건이라 빼고 클라이언트에서 필터 */
    private function supportApiArea(string $area): string
    {
        if ($area === '' || $area === '광주') {
            return '';
        }

        return $area;
    }

    private function supportContentSeeds(array $parsed): array
    {
        $seeds = [''];
        foreach ($parsed['topics'] ?? [] as $topic) {
            $seeds[] = $topic;
        }
        foreach ($parsed['searchTerms'] ?? $parsed['keywords'] ?? [] as $word) {
            $seeds[] = $word;
        }
        $unique = [];
        foreach ($seeds as $seed) {
            if (!in_array($seed, $unique, true)) {
                $unique[] = $seed;
            }
        }

        return array_slice($unique, 0, 5);
    }

    /** @deprecated use fetchSupportPolicies */
    private function fetchSupportList(string $category, string $area = '', string $depart = ''): array
    {
        return $this->fetchSupportPolicies([
            'categories' => [$category],
            'area' => $area,
            'depart' => $depart,
            'topics' => [],
            'keywords' => [],
        ]);
    }

    private function fetchSupportDetail(string $jobKey): array
    {
        return $this->fetchXml(self::SUPPORT_BASE . '/getSupportInfo', [
            'jobKey' => $jobKey,
            'startPage' => '1',
            'pageSize' => '1',
        ], 'detail');
    }

    private function fetchSupportFiles(string $jobKey): array
    {
        $result = $this->fetchXml(self::SUPPORT_BASE . '/getSupportInfoFile', [
            'jobKey' => $jobKey,
            'startPage' => '1',
            'pageSize' => '20',
        ], 'file');

        if (($result['resultCode'] ?? '') !== '00') {
            return [];
        }

        return array_values(array_filter($result['items'] ?? [], static fn($f) => !empty($f['jobFileUrl'])));
    }

    private function fetchGovjobDetail(string $jobKey): array
    {
        return $this->fetchXml(self::GOVJOB_BASE . '/getGovjobInfo', [
            'jobKey' => $jobKey,
            'startPage' => '1',
            'pageSize' => '1',
        ], 'govjob-detail');
    }

    private function fetchGovjobFiles(string $jobKey): array
    {
        $result = $this->fetchXml(self::GOVJOB_BASE . '/getGovjobFile', [
            'jobKey' => $jobKey,
            'startPage' => '1',
            'pageSize' => '20',
        ], 'file');

        if (($result['resultCode'] ?? '') !== '00') {
            return [];
        }

        return array_values(array_filter($result['items'] ?? [], static fn($f) => !empty($f['jobFileUrl'])));
    }

    /**
     * 전남 공공일자리 — getGovjobList
     * 주거 검색에는 채용공고가 섞이지 않게 제외한다.
     */
    private function fetchGovjobPolicies(array $parsed): array
    {
        $topics = $parsed['topics'] ?? [];
        if (in_array('주거', $topics, true)) {
            return [];
        }

        $titles = $this->govjobTitleSeeds($parsed);
        $area = $parsed['area'] ?? '';
        $apiArea = ($area !== '' && $area !== '광주') ? $area : '';

        $out = [];
        $seen = [];

        foreach ($titles as $title) {
            for ($page = 1; $page <= 2; $page++) {
                $params = [
                    'startPage' => (string)$page,
                    'pageSize' => '40',
                ];
                if ($apiArea !== '') {
                    $params['jobArea'] = $apiArea;
                }
                if ($title !== '') {
                    $params['jobTitle'] = $title;
                }

                $result = $this->fetchXml(self::GOVJOB_BASE . '/getGovjobList', $params, 'govjob-list');
                if (($result['resultCode'] ?? '') !== '00') {
                    break;
                }
                $batch = $result['items'] ?? [];
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $item) {
                    $key = $item['jobKey'] ?? '';
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[] = $item;
                }
                $total = (int)($result['totalCount'] ?? 0);
                if ($page * 40 >= $total) {
                    break;
                }
            }
        }

        return $out;
    }

    private function govjobTitleSeeds(array $parsed): array
    {
        $seeds = [];
        foreach ($parsed['keywords'] ?? [] as $word) {
            $seeds[] = $word;
        }
        foreach ($parsed['topics'] ?? [] as $topic) {
            if ($topic === '취업') {
                $seeds[] = '청년';
            } elseif ($topic === '창업') {
                $seeds[] = '창업';
            }
        }
        $q = trim((string)($parsed['query'] ?? ''));
        if ($this->containsAny($q, ['공공일자리', '공공근로', '채용', '구인'])) {
            $seeds[] = '청년';
        }
        if ($seeds === []) {
            // 기본: 청년 관련 공공일자리
            $seeds[] = '청년';
        }

        $unique = [];
        foreach ($seeds as $seed) {
            $seed = trim((string)$seed);
            if ($seed === '' || in_array($seed, $unique, true)) {
                continue;
            }
            $unique[] = $seed;
        }

        return array_slice($unique, 0, 3);
    }

    private function fetchXml(string $url, array $params, string $kind): array
    {
        $query = ['serviceKey' => $this->serviceKey] + $params;
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        $full = $url . '?' . implode('&', $parts);

        $xmlText = $this->httpGet($full);
        if ($xmlText === '') {
            return [
                'resultCode' => '99',
                'resultMsg' => 'empty response',
                'totalCount' => 0,
                'items' => [],
            ];
        }

        return $this->parseXml($xmlText, $kind);
    }

    private function httpGet(string $url): string
    {
        $timeout = max(3, (int) env('HTTP_API_TIMEOUT', 8));
        try {
            $client = service('curlrequest');
            $response = $client->get($url, [
                'timeout' => $timeout,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'jn-support-ci4/1.0',
                    'Accept' => 'application/xml, text/xml, */*',
                ],
            ]);
            $body = (string)$response->getBody();
            if ($body !== '') {
                return $body;
            }
        } catch (Throwable $e) {
            log_message('error', 'httpGet curl: ' . $e->getMessage());
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "User-Agent: jn-support-ci4/1.0\r\nAccept: application/xml,text/xml,*/*\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body === false ? '' : $body;
    }

    private function parseXml(string $xmlText, string $kind): array
    {
        if (trim($xmlText) === '') {
            return ['resultCode' => '99', 'resultMsg' => 'empty response', 'totalCount' => 0, 'items' => []];
        }

        try {
            $root = new SimpleXMLElement($xmlText);
        } catch (Throwable $e) {
            return ['resultCode' => '99', 'resultMsg' => $e->getMessage(), 'totalCount' => 0, 'items' => []];
        }

        if (isset($root->cmmMsgHeader) || strpos($root->getName(), 'OpenAPI_ServiceResponse') !== false) {
            return [
                'resultCode' => trim((string)($root->cmmMsgHeader->returnReasonCode ?? '04')),
                'resultMsg' => trim((string)($root->cmmMsgHeader->returnAuthMsg ?? $root->cmmMsgHeader->errMsg ?? 'gateway error')),
                'totalCount' => 0,
                'items' => [],
                'gateway' => true,
            ];
        }

        $resultCode = trim((string)($root->header->resultCode ?? ''));
        $resultMsg = trim((string)($root->header->resultMsg ?? ''));
        $totalCount = (int)((string)($root->body->totalCount ?? 0));
        $nodes = [];
        if (isset($root->body->items->item)) {
            foreach ($root->body->items->item as $node) {
                $nodes[] = $node;
            }
        } elseif (isset($root->body->items->jobKey) || isset($root->body->items->jobFileNm)) {
            $nodes[] = $root->body->items;
        }

        $items = [];
        foreach ($nodes as $node) {
            if ($kind === 'file') {
                $nm = $this->xmlText($node, 'jobFileNm');
                $url = $this->xmlText($node, 'jobFileUrl');
                if ($nm !== '' || $url !== '') {
                    $items[] = ['jobFileNm' => $nm !== '' ? $nm : '첨부파일', 'jobFileUrl' => $url];
                }
                continue;
            }

            if (strpos($kind, 'govjob') === 0) {
                $items[] = $this->govjobToDict($node, substr($kind, -6) === 'detail');
                continue;
            }

            if (strpos($kind, 'program:') === 0) {
                $meta = substr($kind, strlen('program:'));
                [$progKind, $label] = array_pad(explode('|', $meta, 2), 2, '');
                $items[] = $this->programToDict($node, $progKind, $label);
                continue;
            }

            $items[] = $this->supportToDict($node, $kind === 'detail');
        }

        return [
            'resultCode' => $resultCode,
            'resultMsg' => $resultMsg,
            'totalCount' => $totalCount,
            'startPage' => (string)($root->body->startPage ?? '1'),
            'pageSize' => (string)($root->body->pageSize ?? '10'),
            'items' => $items,

        ];
    }

    private function supportToDict(SimpleXMLElement $node, bool $detail = false): array
    {
        $category = $this->xmlText($node, 'jobCategory');
        $depart = $this->xmlText($node, 'jobDepart');
        $apply = $this->xmlText($node, 'jobApply');
        $content = html_entity_decode($this->xmlText($node, 'jobContent'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $data = [
            'kind' => 'support',
            'jobKey' => $this->xmlText($node, 'jobKey'),
            'jobCategory' => $category,
            'jobCategoryLabel' => $this->decodeCodes($category, self::CATEGORY),
            'jobTitle' => $this->xmlText($node, 'jobTitle'),
            'jobArea' => $this->xmlText($node, 'jobArea'),
            'jobDepart' => $depart,
            'jobDepartLabel' => $this->decodeCodes($depart, self::DEPART),
            'jobApply' => $apply,
            'jobApplyLabel' => $this->decodeCodes($apply, self::APPLY),
            'jobTarget' => $this->xmlText($node, 'jobTarget'),
            'jobStartDt' => $this->xmlText($node, 'jobStartDt'),
            'jobEndDt' => $this->xmlText($node, 'jobEndDt'),
        ];

        if ($detail) {
            $data['jobContent'] = $content;
            $data['jobManager'] = $this->xmlText($node, 'jobManager');
        }

        return $data;
    }

    /**
     * 보조금24 통계 — 혜택 목록이 아닌 연도×지역 이용량.
     * 검색 결과와 섞지 않고 별도 API로 제공한다.
     */
    public function subsidyLatest(string $region = '전남'): array
    {
        $region = trim($region) !== '' ? trim($region) : '전남';
        $live = $this->fetchSubsidy24($region);
        if ($live !== null) {
            return [
                'resultCode' => '00',
                'resultMsg' => 'success',
                'item' => $live,
                'source' => 'getSubsidy24',
            ];
        }

        $db = $this->dbSubsidyLatest($region);
        if ($db !== null) {
            return [
                'resultCode' => '00',
                'resultMsg' => 'success',
                'item' => $db,
                'source' => 'local-db',
            ];
        }

        return [
            'resultCode' => '99',
            'resultMsg' => '통계를 찾지 못했습니다.',
            'item' => null,
        ];
    }

    private function dbSubsidyLatest(string $region): ?array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM subsidy24_stat WHERE region = ? ORDER BY year DESC LIMIT 1');
            $stmt->execute([$region]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }

        return [
            'year' => $row['year'],
            'region' => $row['region'],
            'totalCnt' => isset($row['total_cnt']) ? (int)$row['total_cnt'] : 0,
            'onlineCnt' => isset($row['online_cnt']) ? (int)$row['online_cnt'] : 0,
            'onlineRate' => isset($row['online_rate']) ? (float)$row['online_rate'] : 0.0,
            'visitCnt' => isset($row['visit_cnt']) ? (int)$row['visit_cnt'] : 0,
            'visitRate' => isset($row['visit_rate']) ? (float)$row['visit_rate'] : 0.0,
        ];
    }

    private function fetchSubsidy24(string $region): ?array
    {
        if ($this->serviceKey === '') {
            return null;
        }
        $query = [
            'serviceKey' => $this->serviceKey,
            'pageNo' => '1',
            'numOfRows' => '80',
            'type' => 'xml',
        ];
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        $xmlText = $this->httpGet(self::SUBSIDY_URL . '?' . implode('&', $parts));
        if ($xmlText === '') {
            return null;
        }
        try {
            $root = new SimpleXMLElement($xmlText);
        } catch (Throwable $e) {
            return null;
        }

        $best = null;
        $bestYear = -1;
        foreach ($root->row ?? [] as $row) {
            $fields = [];
            foreach ($row->children() as $child) {
                $fields[$child->getName()] = trim((string)$child);
            }
            $cls = $fields['cls'] ?? '';
            $year = (int)($fields['wrttimeid'] ?? 0);
            if ($cls !== $region || $year < $bestYear) {
                continue;
            }
            $bestYear = $year;
            $best = [
                'year' => (string)$year,
                'region' => $cls,
                'totalCnt' => (int)($fields['tot'] ?? 0),
                'onlineCnt' => (int)($fields['online_use_cnt'] ?? 0),
                'onlineRate' => (float)($fields['online_rate'] ?? 0),
                'visitCnt' => (int)($fields['visit_use_cnt'] ?? 0),
                'visitRate' => (float)($fields['visit_rate'] ?? 0),
            ];
        }

        return $best;
    }

    private function programToDict(SimpleXMLElement $node, string $kind, string $label): array
    {
        $content = html_entity_decode(
            $this->xmlText($node, 'jobCont') ?: $this->xmlText($node, 'jobContent'),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $title = $this->xmlText($node, 'jobTitle');
        $area = '';
        foreach (self::AREAS as $name) {
            if (mb_strpos($title, $name) !== false) {
                $area = $name;
                break;
            }
        }

        return [
            'kind' => $kind,
            'jobKey' => $this->xmlText($node, 'jobKey'),
            'jobTitle' => $title,
            'jobArea' => $area !== '' ? $area : '전남',
            'jobCategoryLabel' => $label,
            'jobDepartLabel' => $label,
            'jobTarget' => $content !== '' ? mb_substr($content, 0, 120) : $label,
            'jobStartDt' => $this->xmlText($node, 'jobApplyStartDt') ?: $this->xmlText($node, 'jobStartDt'),
            'jobEndDt' => $this->xmlText($node, 'jobApplyEndDt') ?: $this->xmlText($node, 'jobEndDt'),
            'jobApplyLabel' => '',
            'jobReadCnt' => (int)$this->xmlText($node, 'jobReadCnt'),
            'jobContent' => $content,
            'jobManager' => '',
        ];
    }

    private function govjobToDict(SimpleXMLElement $node, bool $detail = false): array
    {
        $content = html_entity_decode($this->xmlText($node, 'jobContent'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $area = $this->xmlText($node, 'jobCategoryNm');
        $status = $this->xmlText($node, 'jobStatus');
        $data = [
            'kind' => 'govjob',
            'jobKey' => $this->xmlText($node, 'jobKey'),
            'jobTitle' => $this->xmlText($node, 'jobTitle'),
            'jobArea' => $area,
            'jobCategoryLabel' => '공공일자리',
            'jobDepartLabel' => $area,
            'jobWriter' => $this->xmlText($node, 'jobWriter') ?: $this->xmlText($node, 'jobManager'),
            'jobStartDt' => $this->xmlText($node, 'jobStartDt'),
            'jobEndDt' => $this->xmlText($node, 'jobEndDt'),
            'jobTarget' => $status ?: $this->xmlText($node, 'jobWriter'),
            'jobApplyLabel' => $status,
        ];
        if ($detail) {
            $data['jobContent'] = $content;
            $data['jobManager'] = $this->xmlText($node, 'jobManager');
            $data['jobManagerTel'] = $this->xmlText($node, 'jobManagerTel');
            $data['jobLink'] = $this->xmlText($node, 'jobLink');
            $data['jobStatus'] = $status;
        }

        return $data;
    }

    private function xmlText(SimpleXMLElement $node, string $tag): string
    {
        return trim((string)($node->{$tag} ?? ''));
    }

    private function decodeCodes(?string $value, array $table): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $labels = [];
        foreach (explode(',', $value) as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $labels[] = $table[$code] ?? $code;
        }

        return implode(', ', $labels);
    }

    private function itemAllowed(array $item, array $parsed): bool
    {
        return $this->topicMatches($item, $parsed['topics'] ?? [])
            && $this->keywordMatches($item, $parsed['keywords'] ?? [], $parsed);
    }

    private function topicMatches(array $item, array $topics): bool
    {
        if ($topics === []) {
            return true;
        }
        $title = (string)($item['jobTitle'] ?? '');
        $blob = $title . ' ' . ($item['jobTarget'] ?? '') . ' ' . ($item['jobContent'] ?? '')
            . ' ' . ($item['jobDepartLabel'] ?? '');
        foreach ($topics as $topic) {
            foreach (self::TOPIC_NEGATIVE[$topic] ?? [] as $neg) {
                if (mb_strpos($title, $neg) !== false || mb_strpos($blob, $neg) !== false) {
                    return false;
                }
            }
            $terms = self::TOPIC_GROUPS[$topic] ?? [];
            $hit = false;
            foreach ($terms as $term) {
                if (mb_strpos($blob, $term) !== false) {
                    $hit = true;
                    break;
                }
            }
            if ($terms !== [] && !$hit) {
                return false;
            }
        }

        return true;
    }

    private function keywordMatches(array $item, array $keywords, array $parsed = []): bool
    {
        if ($keywords === []) {
            return true;
        }

        // 주제가 있으면 주제 필터로 충분. keywords는 API 시드/점수용.
        if (($parsed['topics'] ?? []) !== []) {
            return true;
        }

        $blob = implode(' ', [
            $item['jobTitle'] ?? '',
            $item['jobTarget'] ?? '',
            $item['jobArea'] ?? '',
            $item['jobDepartLabel'] ?? '',
            $item['jobCategoryLabel'] ?? '',
            $item['jobContent'] ?? '',
            $item['jobWriter'] ?? '',
        ]);

        // 기본 OR: 하나라도 본문에 있으면 통과
        foreach ($keywords as $word) {
            if ($word !== '' && mb_strpos($blob, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function ageMatches(array $item, ?int $ageMin, ?int $ageMax): bool
    {
        if ($ageMin === null) {
            return true;
        }
        $hi = $ageMax ?? $ageMin;
        $ymin = $item['ageMin'] ?? null;
        $ymax = $item['ageMax'] ?? null;
        if ($ymin !== null && $ymax !== null) {
            if (($item['ageLmtYn'] ?? '') === 'N') {
                return true;
            }

            return !($ymax < $ageMin || $ymin > $hi);
        }

        $blob = ($item['jobTarget'] ?? '') . ' ' . ($item['jobTitle'] ?? '');
        if (preg_match('/(?:만)?(\d{1,2})세(?:이상)?[~\-～부터]+(?:만)?(\d{1,2})세/u', str_replace(' ', '', $blob), $m)) {
            return !($hi < (int)$m[1] || $ageMin > (int)$m[2]);
        }
        if (mb_strpos($blob, '청년') !== false) {
            return !($hi < 19 || $ageMin > 39);
        }

        return true;
    }

    /**
     * 신청/사업 기간이 끝났으면 제외.
     * 종료일을 파싱할 수 없거나 상시·연중이면 통과.
     */
    private function periodOk(array $item): bool
    {
        $endRaw = trim((string)($item['jobEndDt'] ?? ''));
        $startRaw = trim((string)($item['jobStartDt'] ?? ''));
        $applyRaw = trim((string)($item['jobApplyLabel'] ?? ''));
        $blob = trim($endRaw . ' ' . $startRaw . ' ' . $applyRaw);

        if ($blob === '') {
            return true;
        }

        if ($this->periodIsOngoing($blob)) {
            return true;
        }

        $end = $this->parsePeriodEnd($endRaw !== '' ? $endRaw : $blob);
        if ($end === null) {
            return true;
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Seoul'));

        return $end >= $today;
    }

    private function periodIsOngoing(string $text): bool
    {
        $ongoing = [
            '연중', '상시', '계속', '수시', '상시모집', '수시모집', '상시 접수',
            '예산소진', '예산 소진', '종료시까지', '종료 시까지', '별도 공지',
            '매년', '연례', '당해 연도', '공고 참고', '공고문 참고', '담당자 문의',
            '기간 상이', '일정에 따라', '순차',
        ];

        return $this->containsAny($text, $ongoing);
    }

    /**
     * 기간 문자열에서 종료일(포함)을 뽑는다. 못 찾으면 null.
     */
    private function parsePeriodEnd(string $raw): ?\DateTimeImmutable
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        // 명확한 단일 일자: 2024.09.30 / 2024-09-30 / 20240930
        if (preg_match('/^(\d{4})[.\-\/](\d{1,2})[.\-\/](\d{1,2})$/u', $text, $m)
            || preg_match('/^(\d{4})(\d{2})(\d{2})$/u', $text, $m)) {
            return $this->makeDate((int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // 본문 안 마지막 YYYY.M.D / YYYY-M-D
        if (preg_match_all('/(\d{4})[.\-\/년]\s*(\d{1,2})[.\-\/월]\s*(\d{1,2})/u', $text, $matches, PREG_SET_ORDER)) {
            $last = $matches[array_key_last($matches)];

            return $this->makeDate((int)$last[1], (int)$last[2], (int)$last[3]);
        }

        // 2026-01-01~2026-12-31 / 2025.1.~2025.12. / 2026. 1. ~ 12.
        if (preg_match(
            '/(\d{4})\s*[.\-\/년]?\s*(\d{1,2})?\s*[.\-\/월]?\s*(\d{1,2})?\s*[~\-～至到부터]+(?:\s*(\d{4})\s*[.\-\/년]?)?\s*(\d{1,2})?\s*[.\-\/월]?\s*(\d{1,2})?/u',
            $text,
            $m
        )) {
            $endYear = (int)(($m[4] ?? '') !== '' ? $m[4] : $m[1]);
            $endMonth = (int)(($m[5] ?? '') !== '' ? $m[5] : (($m[2] ?? '') !== '' ? $m[2] : 12));
            $endDay = (int)(($m[6] ?? '') !== '' ? $m[6] : 0);
            if ($endDay === 0) {
                $endDay = (int)(new \DateTimeImmutable(sprintf('%04d-%02d-01', $endYear, $endMonth)))
                    ->modify('last day of this month')
                    ->format('d');
            }

            return $this->makeDate($endYear, $endMonth, $endDay);
        }

        // 연도만: 2025년 / 2023년 ~ 2025년 → 마지막 연도 말
        if (preg_match_all('/(\d{4})\s*년/u', $text, $years) || preg_match_all('/(?:^|[^\d])(\d{4})(?:[^\d]|$)/u', $text, $years)) {
            $yearList = array_map('intval', $years[1]);
            if ($yearList !== []) {
                $endYear = max($yearList);

                return $this->makeDate($endYear, 12, 31);
            }
        }

        return null;
    }

    private function makeDate(int $year, int $month, int $day): ?\DateTimeImmutable
    {
        if ($year < 1990 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }
        if (!checkdate($month, $day, $year)) {
            // 말일 보정
            $day = (int)(new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
                ->modify('last day of this month')
                ->format('d');
        }

        return \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-%02d', $year, $month, $day),
            new \DateTimeZone('Asia/Seoul')
        ) ?: null;
    }

    /**
     * 광주·전남 중심 필터.
     * 중앙복지(nwlf)는 전국 서비스라 지역 키워드 없이 통과.
     */
    private function areaOk(array $item, string $area): bool
    {
        $kind = (string)($item['kind'] ?? '');
        $blob = implode(' ', [
            $item['jobArea'] ?? '',
            $item['jobTitle'] ?? '',
            $item['jobTarget'] ?? '',
            $item['jobContent'] ?? '',
            $item['jobCategoryLabel'] ?? '',
            $item['jobDepartLabel'] ?? '',
        ]);

        if ($area !== '' && mb_strpos($blob, $area) !== false) {
            return true;
        }

        if ($kind === 'nwlf') {
            return true;
        }

        $isLocal = $this->containsAny($blob, self::JN_HINTS);
        $isOther = $this->containsAny($blob, self::OTHER_REGIONS);

        if ($kind === 'gov24') {
            if ($isLocal) {
                return $area === '' || mb_strpos($blob, $area) !== false;
            }
            if ($isOther) {
                return false;
            }

            return $area === '';
        }

        if ($kind === 'youth') {
            // 타 지역만 단독으로 있으면 제외. 전남·광주가 함께면 통과.
            if ($isOther && !$isLocal) {
                return false;
            }
            if (!$isLocal) {
                return false;
            }
            if ($area !== '' && mb_strpos($blob, $area) === false) {
                return false;
            }

            return true;
        }

        if ($kind === 'lcgv' || in_array($kind, ['hopebus', 'jobmatch', 'meetday', 'center'], true)) {
            if (!$isLocal) {
                // 지원프로그램은 소관이 전남이라 jobArea 기본값이 전남인 경우가 많음
                if (in_array($kind, ['hopebus', 'jobmatch', 'meetday', 'center'], true)
                    && (($item['jobArea'] ?? '') === '전남' || ($item['jobArea'] ?? '') === '')) {
                    $isLocal = true;
                } else {
                    return false;
                }
            }
            if ($area !== '' && mb_strpos($blob, $area) === false && $area !== '전남') {
                return false;
            }

            return true;
        }

        // support / govjob 등
        if ($isOther && !$isLocal) {
            return false;
        }
        if (!$isLocal) {
            return false;
        }
        if ($area !== '' && mb_strpos($blob, $area) === false) {
            return false;
        }

        return true;
    }

    private function score(array $item, array $parsed): int
    {
        $title = (string)($item['jobTitle'] ?? '');
        $target = (string)($item['jobTarget'] ?? '');
        $depart = (string)($item['jobDepartLabel'] ?? '');
        $blob = $title . ' ' . $target . ' ' . $depart . ' ' . (string)($item['jobContent'] ?? '');
        $query = (string)($parsed['query'] ?? '');
        $score = 0;

        if ($parsed['ageMin'] !== null) {
            if (preg_match('/30대|만\s*3[0-9]세|39세/u', $blob)) {
                $score += 8;
            } elseif (mb_strpos($blob, '청년') !== false || mb_strpos((string)($item['jobCategoryLabel'] ?? ''), '청년') !== false) {
                $score += 5;
            }
        }
        if (($parsed['area'] ?? '') !== '' && mb_strpos((string)($item['jobArea'] ?? ''), $parsed['area']) !== false) {
            $score += 6;
        } elseif ($this->containsAny((string)($item['jobArea'] ?? ''), ['전남', '광주', '전라남도'])) {
            $score += 3;
        }

        foreach ($parsed['topics'] ?? [] as $topic) {
            foreach (self::TOPIC_GROUPS[$topic] ?? [] as $term) {
                if (mb_strpos($title, $term) !== false) {
                    $score += 12;
                    break;
                }
                if (mb_strpos($blob, $term) !== false) {
                    $score += 6;
                    break;
                }
            }
        }

        // 검색어/확장 키워드가 제목에 있으면 강하게 가산
        foreach ($parsed['searchTerms'] ?? [] as $term) {
            $term = trim((string)$term);
            if ($term === '' || mb_strlen($term) < 2) {
                continue;
            }
            if (mb_strpos($title, $term) !== false) {
                $score += 14;
            } elseif (mb_strpos($blob, $term) !== false) {
                $score += 5;
            }
        }

        // 구직 의도: 구직·면접·장려금 우선, 이미 취업한 사람 주거비는 뒤로
        if ($this->containsAny($query, ['구직', '취준', '면접']) || in_array('취업', $parsed['topics'] ?? [], true)) {
            if ($this->containsAny($title, ['구직', '구직활동', '면접', '취업장려', '증명사진'])) {
                $score += 18;
            }
            if (mb_strpos($title, '취업자') !== false && mb_strpos($title, '주거') !== false) {
                $score -= 16;
            }
            if (mb_strpos($title, '창업') !== false && !$this->containsAny($query, ['창업'])) {
                $score -= 8;
            }
        }

        // 독립/주거 의도: 청년 주거 우선, 아동·신혼·노인 단독은 뒤로
        if ($this->containsAny($query, ['독립', '자립', '자취']) || in_array('주거', $parsed['topics'] ?? [], true)) {
            if ($this->containsAny($title, ['청년', '독립', '자립', '자취'])) {
                $score += 16;
            }
            if ($this->containsAny($depart, ['주거', '자립'])) {
                $score += 8;
            }
            if ($this->containsAny($title, ['아동', '신혼', '노인', '노년', '어르신'])
                && !$this->containsAny($query, ['아동', '신혼', '노인', '어르신'])) {
                $score -= 14;
            }
            // 독립만 물었을 때 이미 취업한 사람 대상 공고는 낮춤
            if ($this->containsAny($query, ['독립', '자립', '자취'])
                && !$this->containsAny($query, ['취업', '구직', '취준'])
                && mb_strpos($title, '취업자') !== false) {
                $score -= 10;
            }
        }

        return $score;
    }

    private function summarize(array $parsed): string
    {
        if (($parsed['intentSource'] ?? '') === 'gemini' && trim((string)($parsed['summary'] ?? '')) !== '') {
            return (string)$parsed['summary'];
        }

        $labels = [];
        if ($parsed['ageMin'] !== null) {
            $labels[] = $parsed['ageMin'] === $parsed['ageMax']
                ? $parsed['ageMin'] . '세'
                : $parsed['ageMin'] . '~' . $parsed['ageMax'] . '세';
        }
        foreach ($parsed['categories'] as $c) {
            if (isset(self::CATEGORY[$c])) {
                $labels[] = self::CATEGORY[$c];
            }
        }
        if (($parsed['area'] ?? '') !== '') {
            $labels[] = $parsed['area'];
        }
        if (($parsed['topics'] ?? []) !== []) {
            foreach ($parsed['topics'] as $name) {
                $labels[] = $name . '만';
            }
        } elseif (($parsed['depart'] ?? '') !== '' && isset(self::DEPART[$parsed['depart']])) {
            $labels[] = self::DEPART[$parsed['depart']];
        }
        foreach ($parsed['keywords'] ?? [] as $word) {
            $labels[] = $word;
        }

        return $labels !== [] ? implode(' · ', $labels) : '청년 전체';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
