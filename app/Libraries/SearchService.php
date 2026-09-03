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
    private const GOVJOB_BASE  = 'https://apis.data.go.kr/6460000/jnGovjobInfo';

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
    ];

    private const TOPIC_GROUPS = [
        '주거' => ['주거', '주거비', '전세', '월세', '임대주택', '기숙사', '주택', '임차'],
        '창업' => ['창업', '스타트업', '창업자', '예비창업'],
        '취업' => ['취업', '구직', '채용'],
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
    ];

    private string $serviceKey;
    private string $dbPath;

    public function __construct(?string $serviceKey = null, ?string $dbPath = null)
    {
        $this->serviceKey = $serviceKey
            ?: (string) env('DATA_GO_KR_KEY', 'W0dlVSMzb/3Goq3tjjPjFiKzWvtgOcuagSX1992mmCvID76qIHwen0+63lA/yLEYB6kC2USG1Ce6RROg3CH0WA==');
        $this->dbPath = $dbPath ?: WRITEPATH . 'jn_support.db';
    }

    public function recommend(string $query, int $startPage = 1, int $pageSize = 8): array
    {
        $parsed = $this->parseQuery($query);
        $items  = $this->collectItems($parsed);

        usort($items, static function (array $a, array $b) use ($parsed): int {
            return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
        });

        $total = count($items);
        $start = max($startPage - 1, 0) * $pageSize;
        $pageItems = array_slice($items, $start, $pageSize);

        foreach ($pageItems as &$item) {
            unset($item['_score']);
        }
        unset($item);

        return [
            'resultCode' => '00',
            'resultMsg'  => 'success',
            'totalCount' => $total,
            'startPage'  => (string) $startPage,
            'pageSize'   => (string) $pageSize,
            'items'      => array_values($pageItems),
            'parsed'     => $parsed,
            'filters'    => [
                'jobCategory' => $parsed['categories'][0] ?? 'B',
                'jobArea'     => $parsed['area'] !== '' ? $parsed['area'] : '전체',
                'jobDepart'   => $parsed['depart'],
                'jobApply'    => '',
                'jobContent'  => implode(' ', $parsed['keywords']),
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
                'resultMsg'  => 'jobKey가 필요합니다.',
                'items'      => [],
                'files'      => [],
            ];
        }

        if ($kind === 'support') {
            $live = $this->fetchSupportDetail($jobKey);
            if (($live['resultCode'] ?? '') === '00' && ! empty($live['items'])) {
                $live['files'] = $this->fetchSupportFiles($jobKey);
                $live['source'] = [
                    'list'   => 'getSupportList',
                    'detail' => 'getSupportInfo',
                    'file'   => 'getSupportInfoFile',
                ];

                return $live;
            }
        }

        if ($kind === 'govjob') {
            $live = $this->fetchGovjobDetail($jobKey);
            if (($live['resultCode'] ?? '') === '00' && ! empty($live['items'])) {
                $live['files'] = [];
                $live['source'] = [
                    'list'   => 'getGovjobList',
                    'detail' => 'getGovjobInfo',
                    'file'   => 'getGovjobFile',
                ];

                return $live;
            }
        }

        $item = $this->dbFind($kind, $jobKey);
        if ($item === null) {
            return [
                'resultCode' => '99',
                'resultMsg'  => '항목을 찾지 못했습니다.',
                'items'      => [],
                'files'      => [],
            ];
        }

        $item = $this->enrichDetail($item);

        return [
            'resultCode' => '00',
            'resultMsg'  => 'success',
            'items'      => [$item],
            'files'      => $this->dbFiles($kind, $jobKey),
            'source'     => ['list' => 'local-db', 'detail' => 'local-db', 'file' => ''],
        ];
    }

    public function parseQuery(string $text): array
    {
        $raw = trim($text);
        $ageMin = null;
        $ageMax = null;

        if (preg_match('/(\d{2})대/u', $raw, $m)) {
            $start = (int) $m[1];
            $ageMin = $start;
            $ageMax = $start + 9;
        } elseif (preg_match('/(?:만\s*)?(\d{1,2})\s*(?:세|살)/u', $raw, $m)) {
            $age = (int) $m[1];
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
        $leftover = preg_replace('/(?:만\s*)?\d{1,2}\s*(?:세|살|대)/u', ' ', $leftover) ?? $leftover;
        $leftover = preg_replace('/[^0-9A-Za-z가-힣]+/u', ' ', $leftover) ?? $leftover;
        $keywords = [];
        foreach (preg_split('/\s+/u', trim($leftover)) ?: [] as $word) {
            if (mb_strlen($word) >= 2) {
                $keywords[] = $word;
            }
        }

        $parsed = [
            'query'        => $raw,
            'ageMin'       => $ageMin,
            'ageMax'       => $ageMax,
            'categories'   => $categories,
            'area'         => $area,
            'depart'       => $depart,
            'topics'       => $topics,
            'keywords'     => $keywords,
            'intentSource' => 'rules',
            'summary'      => '',
        ];
        $parsed['summary'] = $this->summarize($parsed);

        return $parsed;
    }

    private function collectItems(array $parsed): array
    {
        $items = [];
        $seen  = [];

        foreach ($this->dbSearch($parsed) as $item) {
            $key = ($item['kind'] ?? '') . ':' . ($item['jobKey'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['_score'] = $this->score($item, $parsed);
            $items[] = $item;
        }

        // 공공 API는 가능하면 보강 (실패해도 DB 결과 유지)
        foreach ($parsed['categories'] as $category) {
            $apiDepart = $parsed['topics'] !== [] ? '' : ($parsed['depart'] ?? '');
            foreach ($this->fetchSupportList($category, $parsed['area'] ?? '', $apiDepart) as $item) {
                if (! $this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                    continue;
                }
                if (! $this->areaOk($item, $parsed['area'] ?? '')) {
                    continue;
                }
                if (! $this->itemAllowed($item, $parsed)) {
                    continue;
                }
                $key = ($item['kind'] ?? 'support') . ':' . ($item['jobKey'] ?? '');
                if ($key === 'support:' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $item['_score'] = $this->score($item, $parsed);
                $items[] = $item;
            }
        }

        return $items;
    }

    private function db(): ?PDO
    {
        if (! is_file($this->dbPath)) {
            return null;
        }

        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $pdo = new PDO('sqlite:' . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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
                $item['ageMin'] = $extra['age_min'] !== null ? (int) $extra['age_min'] : null;
                $item['ageMax'] = $extra['age_max'] !== null ? (int) $extra['age_max'] : null;
                $item['ageLmtYn'] = $extra['age_lmt_yn'];
            }
            if ($item['kind'] === 'gov24' && isset($govAges[$item['jobKey']])) {
                $cond = $govAges[$item['jobKey']];
                if ($cond['age_start'] !== null || $cond['age_end'] !== null) {
                    $item['ageMin'] = $cond['age_start'] !== null ? (int) $cond['age_start'] : null;
                    $item['ageMax'] = $cond['age_end'] !== null ? (int) $cond['age_end'] : null;
                }
            }

            if (! $this->ageMatches($item, $parsed['ageMin'], $parsed['ageMax'])) {
                continue;
            }
            if (! $this->areaOk($item, $parsed['area'] ?? '')) {
                continue;
            }
            if (! $this->itemAllowed($item, $parsed)) {
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
        $stmt = $pdo->prepare('SELECT file_nm, file_url FROM program_file WHERE kind = ? AND job_key = ?');
        $stmt->execute([$kind, $jobKey]);
        $files = [];
        foreach ($stmt->fetchAll() as $row) {
            if (! empty($row['file_url'])) {
                $files[] = [
                    'jobFileNm'  => $row['file_nm'] ?: '첨부파일',
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
        $key  = $item['jobKey'] ?? '';

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
                if (! empty($extra['apply_url'])) {
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
                if (! empty($extra['apply_url'])) {
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
                if (! empty($extra['phone'])) {
                    $item['jobManagerTel'] = $extra['phone'];
                }
                if (! empty($extra['ministry'])) {
                    $item['jobManager'] = $extra['ministry'];
                }
            }
        }

        return $item;
    }

    private function rowToItem(array $row): array
    {
        $extra = [];
        if (! empty($row['extra_json'])) {
            $decoded = json_decode($row['extra_json'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $item = [
            'kind'             => $row['kind'],
            'jobKey'           => $row['job_key'],
            'jobTitle'         => $row['title'],
            'jobArea'          => $row['area'],
            'jobCategoryLabel' => $row['category_label'],
            'jobDepartLabel'   => $row['depart_label'],
            'jobApplyLabel'    => $row['apply_label'],
            'jobTarget'        => $row['target'],
            'jobContent'       => $row['content'],
            'jobStartDt'       => $row['start_dt'],
            'jobEndDt'         => $row['end_dt'],
            'jobManager'       => $row['manager'],
            'jobManagerTel'    => $row['manager_tel'],
            'jobLink'          => $row['link'],
            'jobReadCnt'       => $row['read_cnt'],
            'jobWriter'        => $row['manager'],
        ];

        return array_merge($item, $extra);
    }

    private function fetchSupportList(string $category, string $area = '', string $depart = ''): array
    {
        $params = [
            'startPage'   => '1',
            'pageSize'    => '50',
            'jobCategory' => $category,
        ];
        if ($area !== '') {
            $params['jobArea'] = $area;
        }
        if ($depart !== '') {
            $params['jobDepart'] = $depart;
        }

        $result = $this->fetchXml(self::SUPPORT_BASE . '/getSupportList', $params, 'list');
        if (($result['resultCode'] ?? '') !== '00') {
            return [];
        }

        return $result['items'] ?? [];
    }

    private function fetchSupportDetail(string $jobKey): array
    {
        return $this->fetchXml(self::SUPPORT_BASE . '/getSupportInfo', [
            'jobKey'    => $jobKey,
            'startPage' => '1',
            'pageSize'  => '1',
        ], 'detail');
    }

    private function fetchSupportFiles(string $jobKey): array
    {
        $result = $this->fetchXml(self::SUPPORT_BASE . '/getSupportInfoFile', [
            'jobKey'    => $jobKey,
            'startPage' => '1',
            'pageSize'  => '20',
        ], 'file');

        if (($result['resultCode'] ?? '') !== '00') {
            return [];
        }

        return array_values(array_filter($result['items'] ?? [], static fn ($f) => ! empty($f['jobFileUrl'])));
    }

    private function fetchGovjobDetail(string $jobKey): array
    {
        return $this->fetchXml(self::GOVJOB_BASE . '/getGovjobInfo', [
            'jobKey'    => $jobKey,
            'startPage' => '1',
            'pageSize'  => '1',
        ], 'govjob-detail');
    }

    private function fetchXml(string $url, array $params, string $kind): array
    {
        $query = array_merge(['serviceKey' => $this->serviceKey], $params);
        $full  = $url . '?' . http_build_query($query);

        try {
            $client = service('curlrequest');
            $response = $client->get($full, [
                'timeout' => 20,
                'http_errors' => false,
                'headers' => ['User-Agent' => 'jn-support-ci4/1.0'],
            ]);
            $xmlText = (string) $response->getBody();
        } catch (Throwable $e) {
            return [
                'resultCode' => '99',
                'resultMsg'  => $e->getMessage(),
                'totalCount' => 0,
                'items'      => [],
            ];
        }

        return $this->parseXml($xmlText, $kind);
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

        if (isset($root->cmmMsgHeader) || str_contains($root->getName(), 'OpenAPI_ServiceResponse')) {
            return [
                'resultCode' => trim((string) ($root->cmmMsgHeader->returnReasonCode ?? '04')),
                'resultMsg'  => trim((string) ($root->cmmMsgHeader->returnAuthMsg ?? $root->cmmMsgHeader->errMsg ?? 'gateway error')),
                'totalCount' => 0,
                'items'      => [],
                'gateway'    => true,
            ];
        }

        $resultCode = trim((string) ($root->header->resultCode ?? ''));
        $resultMsg  = trim((string) ($root->header->resultMsg ?? ''));
        $totalCount = (int) ((string) ($root->body->totalCount ?? 0));
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
                $nm  = $this->xmlText($node, 'jobFileNm');
                $url = $this->xmlText($node, 'jobFileUrl');
                if ($nm !== '' || $url !== '') {
                    $items[] = ['jobFileNm' => $nm !== '' ? $nm : '첨부파일', 'jobFileUrl' => $url];
                }
                continue;
            }

            if (str_starts_with($kind, 'govjob')) {
                $items[] = $this->govjobToDict($node, str_ends_with($kind, 'detail'));
                continue;
            }

            $items[] = $this->supportToDict($node, $kind === 'detail');
        }

        return [
            'resultCode' => $resultCode,
            'resultMsg'  => $resultMsg,
            'totalCount' => $totalCount,
            'startPage'  => (string) ($root->body->startPage ?? '1'),
            'pageSize'   => (string) ($root->body->pageSize ?? '10'),
            'items'      => $items,
        ];
    }

    private function supportToDict(SimpleXMLElement $node, bool $detail = false): array
    {
        $category = $this->xmlText($node, 'jobCategory');
        $depart   = $this->xmlText($node, 'jobDepart');
        $apply    = $this->xmlText($node, 'jobApply');
        $content  = html_entity_decode($this->xmlText($node, 'jobContent'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $data = [
            'kind'             => 'support',
            'jobKey'           => $this->xmlText($node, 'jobKey'),
            'jobCategory'      => $category,
            'jobCategoryLabel' => $this->decodeCodes($category, self::CATEGORY),
            'jobTitle'         => $this->xmlText($node, 'jobTitle'),
            'jobArea'          => $this->xmlText($node, 'jobArea'),
            'jobDepart'        => $depart,
            'jobDepartLabel'   => $this->decodeCodes($depart, self::DEPART),
            'jobApply'         => $apply,
            'jobApplyLabel'    => $this->decodeCodes($apply, self::APPLY),
            'jobTarget'        => $this->xmlText($node, 'jobTarget'),
            'jobStartDt'       => $this->xmlText($node, 'jobStartDt'),
            'jobEndDt'         => $this->xmlText($node, 'jobEndDt'),
        ];

        if ($detail) {
            $data['jobContent'] = $content;
            $data['jobManager'] = $this->xmlText($node, 'jobManager');
        }

        return $data;
    }

    private function govjobToDict(SimpleXMLElement $node, bool $detail = false): array
    {
        $content = html_entity_decode($this->xmlText($node, 'jobContent'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $area    = $this->xmlText($node, 'jobCategoryNm');
        $status  = $this->xmlText($node, 'jobStatus');
        $data = [
            'kind'             => 'govjob',
            'jobKey'           => $this->xmlText($node, 'jobKey'),
            'jobTitle'         => $this->xmlText($node, 'jobTitle'),
            'jobArea'          => $area,
            'jobCategoryLabel' => '공공일자리',
            'jobDepartLabel'   => $area,
            'jobWriter'        => $this->xmlText($node, 'jobWriter') ?: $this->xmlText($node, 'jobManager'),
            'jobStartDt'       => $this->xmlText($node, 'jobStartDt'),
            'jobEndDt'         => $this->xmlText($node, 'jobEndDt'),
            'jobTarget'        => $status ?: $this->xmlText($node, 'jobWriter'),
            'jobApplyLabel'    => $status,
        ];
        if ($detail) {
            $data['jobContent']    = $content;
            $data['jobManager']    = $this->xmlText($node, 'jobManager');
            $data['jobManagerTel'] = $this->xmlText($node, 'jobManagerTel');
            $data['jobLink']       = $this->xmlText($node, 'jobLink');
            $data['jobStatus']     = $status;
        }

        return $data;
    }

    private function xmlText(SimpleXMLElement $node, string $tag): string
    {
        return trim((string) ($node->{$tag} ?? ''));
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
            && $this->keywordMatches($item, $parsed['keywords'] ?? []);
    }

    private function topicMatches(array $item, array $topics): bool
    {
        if ($topics === []) {
            return true;
        }
        $title = (string) ($item['jobTitle'] ?? '');
        $blob  = $title . ' ' . ($item['jobTarget'] ?? '') . ' ' . ($item['jobContent'] ?? '');
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
            if ($terms !== [] && ! $hit) {
                return false;
            }
        }

        return true;
    }

    private function keywordMatches(array $item, array $keywords): bool
    {
        if ($keywords === []) {
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
        foreach ($keywords as $word) {
            if (mb_strpos($blob, $word) === false) {
                return false;
            }
        }

        return true;
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

            return ! ($ymax < $ageMin || $ymin > $hi);
        }

        $blob = ($item['jobTarget'] ?? '') . ' ' . ($item['jobTitle'] ?? '');
        if (preg_match('/(?:만)?(\d{1,2})세(?:이상)?[~\-～부터]+(?:만)?(\d{1,2})세/u', str_replace(' ', '', $blob), $m)) {
            return ! ($hi < (int) $m[1] || $ageMin > (int) $m[2]);
        }
        if (mb_strpos($blob, '청년') !== false) {
            return ! ($hi < 19 || $ageMin > 39);
        }

        return true;
    }

    /**
     * 광주·전남 데이터만 통과.
     * 사용자가 시군(예: 목포)을 말하면 그 시군도 본문에 있어야 한다.
     */
    private function areaOk(array $item, string $area): bool
    {
        $blob = implode(' ', [
            $item['jobArea'] ?? '',
            $item['jobTitle'] ?? '',
            $item['jobTarget'] ?? '',
            $item['jobContent'] ?? '',
            $item['jobCategoryLabel'] ?? '',
            $item['jobDepartLabel'] ?? '',
        ]);

        // 타 지역이면 제외
        if ($this->containsAny($blob, self::OTHER_REGIONS)) {
            // 광주/전남 키워드가 함께 있으면(예: 비교 문구) 아래 local 검사로 이어감
            if (! $this->containsAny($blob, self::JN_HINTS)) {
                return false;
            }
        }

        $isLocal = $this->containsAny($blob, self::JN_HINTS);
        if (! $isLocal) {
            return false;
        }

        // 특정 시군이 지정되면 그 지역이 본문에 있어야 함
        if ($area !== '' && mb_strpos($blob, $area) === false) {
            return false;
        }

        return true;
    }

    private function score(array $item, array $parsed): int
    {
        $title = (string) ($item['jobTitle'] ?? '');
        $target = (string) ($item['jobTarget'] ?? '');
        $blob = $title . ' ' . $target;
        $score = 0;

        if ($parsed['ageMin'] !== null) {
            if (preg_match('/30대|만\s*3[0-9]세|39세/u', $blob)) {
                $score += 8;
            } elseif (mb_strpos($blob, '청년') !== false || mb_strpos((string) ($item['jobCategoryLabel'] ?? ''), '청년') !== false) {
                $score += 3;
            }
        }
        if (($parsed['area'] ?? '') !== '' && mb_strpos((string) ($item['jobArea'] ?? ''), $parsed['area']) !== false) {
            $score += 4;
        } elseif ($this->containsAny((string) ($item['jobArea'] ?? ''), ['전남', '광주', '전라남도'])) {
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

        return $score;
    }

    private function summarize(array $parsed): string
    {
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
