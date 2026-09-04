<?php

namespace App\Libraries;

use PDO;
use Throwable;

/**
 * 공공 API 수집 결과를 크롤링 공통 스키마에 저장한다.
 *
 * 목록 검색 요청에서 실행하지 않고, 관리자/cron에서 실행해야 한다.
 */
class CrawlService
{
    private const KSTARTUP_URL = 'https://apis.data.go.kr/B552735/kisedKstartupService01/getAnnouncementInformation01';
    private const KSTARTUP_BASE = 'https://apis.data.go.kr/B552735/kisedKstartupService01/';
    private const GOV24_URL = 'https://api.odcloud.kr/api/gov24/v3/serviceList';
    private const MOEF_URL = 'https://apis.data.go.kr/1051000/MoefOpenAPI2025/T_OPD_ASBS_PBNS_UNITY';
    private const BID_BASE = 'https://apis.data.go.kr/1230000/ad/BidPublicInfoService/';
    private const SEOGU_LIST = 'https://www.seogu.gwangju.kr/api/eminwon/gosiList.es';
    private const DONGGU_LIST = 'https://eminwon.donggu.gwangju.kr/emwp/jsp/ofr/OfrNotAncmtLSub.jsp';
    private const DONGGU_DETAIL = 'https://eminwon.donggu.gwangju.kr/emwp/gov/mogaha/ntis/web/ofr/action/OfrAction.do';
    private const NAMGU_LIST = 'https://www.namgu.gwangju.kr/board.es?mid=a10707060200&bid=0001';
    private const GJ_CENTER_API = 'https://api.gjyouthcenter.kr/api/v1/notices';

    private PDO $db;
    private string $serviceKey;

    public function __construct(?string $dbPath = null, ?string $serviceKey = null)
    {
        $path = $dbPath ?: WRITEPATH . 'jn_support.db';
        $this->db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->serviceKey = $serviceKey ?: (string) env('DATA_GO_KR_KEY', '');
    }

    public function runAll(int $limit = 100): array
    {
        $result = [];
        $result['kstartup'] = $this->collectKStartup($limit);
        $result['kstartup_business'] = $this->collectKStartupBusiness($limit);
        $result['kstartup_content'] = $this->collectKStartupContent($limit);
        $result['donggu_youth'] = $this->collectDongGuYouth();
        $result['namgu_youth'] = $this->collectNamGuYouth();
        $result['seogu_youth'] = $this->collectSeoGuYouth();
        $result['gwangju_youth_center'] = $this->collectGwangjuYouthCenter();
        $result['gov24'] = $this->collectGov24($limit);

        // 서비스명은 확인됐지만 현재 프로젝트에는 엔드포인트가 등록되지 않은 API
        $result['national_subsidy'] = $this->collectSubsidy($limit);
        $result['나라장터'] = $this->collectBids($limit);

        return $result;
    }

    public function status(): array
    {
        $rows = $this->db->query(
            "SELECT source, synced_at, note FROM sync_meta
             WHERE source LIKE 'crawl:%' ORDER BY source"
        )->fetchAll();
        $counts = [];
        foreach ($this->db->query(
            "SELECT source_id, COUNT(*) AS count FROM crawl_notice GROUP BY source_id"
        ) as $row) {
            $counts[$row['source_id']] = (int) $row['count'];
        }

        return ['sources' => $rows, 'counts' => $counts];
    }

    private function collectKStartup(int $limit): array
    {
        $this->registerSource('kstartup', '창업진흥원 K-Startup', self::KSTARTUP_URL, 'public_api');
        $url = self::KSTARTUP_URL . '?' . http_build_query([
            'serviceKey' => $this->serviceKey,
            'page' => 1,
            'perPage' => min(100, max(1, $limit)),
            'returnType' => 'json',
        ]);
        $raw = $this->get($url);
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return $this->failed('응답 오류 또는 인증키/엔드포인트 오류', $raw);
        }

        $count = 0;
        foreach ($json['data'] as $row) {
            $key = (string) ($row['pbanc_sn'] ?? $row['id'] ?? '');
            $title = trim((string) ($row['biz_pbanc_nm'] ?? $row['intg_pbanc_biz_nm'] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $this->upsert([
                'source_id' => 'kstartup',
                'source_name' => '창업진흥원 K-Startup',
                'source_type' => 'public_api',
                'list_url' => self::KSTARTUP_URL,
                'original_url' => $row['detl_pg_url'] ?? $row['biz_gdnc_url'] ?? null,
                'title' => $title,
                'posted_at' => null,
                'apply_start' => $this->date($row['pbanc_rcpt_bgng_dt'] ?? null),
                'apply_end' => $this->date($row['pbanc_rcpt_end_dt'] ?? null),
                'body_text' => $this->text($row['pbanc_ctnt'] ?? '') . "\n" . $this->text($row['aply_trgt_ctnt'] ?? ''),
                'attachment_urls' => null,
                'organizer' => $row['pbanc_ntrp_nm'] ?? null,
                'operator' => $row['biz_prch_dprt_nm'] ?? null,
                'contact' => $row['prch_cnpl_no'] ?? null,
                'region' => $row['supt_regin'] ?? null,
                'raw_html_or_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => $row['supt_biz_clsfc'] ?? null,
                'benefit' => null,
                'source_dup_key' => 'kstartup:' . $key,
            ]);
            $count++;
        }
        $this->mark('kstartup', 'success count=' . $count);

        return ['status' => 'success', 'count' => $count];
    }

    private function collectGov24(int $limit): array
    {
        $this->registerSource('gov24', '행정안전부 대한민국 공공서비스', self::GOV24_URL, 'public_api');
        $url = self::GOV24_URL . '?' . http_build_query([
            'serviceKey' => $this->serviceKey,
            'page' => 1,
            'perPage' => min(100, max(1, $limit)),
            'returnType' => 'json',
        ]);
        $raw = $this->get($url);
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return $this->failed('응답 오류 또는 인증키/엔드포인트 오류', $raw);
        }

        $count = 0;
        foreach ($json['data'] as $row) {
            $key = (string) ($row['서비스ID'] ?? '');
            $title = trim((string) ($row['서비스명'] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $this->upsert([
                'source_id' => 'gov24',
                'source_name' => '행정안전부 대한민국 공공서비스',
                'source_type' => 'public_api',
                'list_url' => self::GOV24_URL,
                'original_url' => $row['상세조회URL'] ?? null,
                'title' => $title,
                'posted_at' => $this->date($row['등록일시'] ?? null),
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text(($row['서비스목적요약'] ?? '') . "\n" . ($row['지원내용'] ?? '') . "\n" . ($row['지원대상'] ?? '')),
                'attachment_urls' => null,
                'organizer' => $row['소관기관명'] ?? null,
                'operator' => $row['접수기관'] ?? null,
                'contact' => $row['전화문의'] ?? null,
                'region' => null,
                'raw_html_or_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => $row['서비스분야'] ?? null,
                'benefit' => $row['지원내용'] ?? null,
                'source_dup_key' => 'gov24:' . $key,
            ]);
            $count++;
        }
        $this->mark('gov24', 'success count=' . $count);

        return ['status' => 'success', 'count' => $count];
    }

    private function collectSeoGuYouth(): array
    {
        $listUrl = self::SEOGU_LIST . '?' . http_build_query([
            'mid' => 'a10807010000',
            'Key' => 'B_Subject',
            'temp' => '청년',
            'pageIndex' => 1,
        ]);
        $this->registerSource('seogu_youth', '광주 서구청 청년 공고', $listUrl, 'local_web');
        $html = $this->get($listUrl);
        if ($html === '') {
            return $this->failed('광주 서구청 응답 없음', '');
        }
        preg_match_all('/searchDetail\(\'([^\']+)\'\).*?>(.*?)<\/a>/su', $html, $matches, PREG_SET_ORDER);
        $count = 0;
        foreach (array_slice($matches, 0, 50) as $match) {
            $id = trim($match[1]);
            $title = $this->text($match[2]);
            if ($id === '' || $title === '') {
                continue;
            }
            $detailUrl = 'https://www.seogu.gwangju.kr/api/eminwon/gosiView.es?' . http_build_query([
                'mid' => 'a10807010000',
                'not_ancmt_mgt_no' => $id,
                'method' => 'selectOfrNotAncmt',
                'methodnm' => 'selectOfrNotAncmtRegst',
            ]);
            $raw = $this->get($detailUrl);
            $this->upsert([
                'source_id' => 'seogu_youth',
                'source_name' => '광주 서구청 청년 공고',
                'source_type' => 'local_web',
                'list_url' => $listUrl,
                'original_url' => $detailUrl,
                'title' => $title,
                'posted_at' => null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text($raw),
                'attachment_urls' => null,
                'organizer' => '광주 서구청',
                'operator' => null,
                'contact' => null,
                'region' => '광주 서구',
                'raw_html_or_json' => $raw,
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => '청년',
                'benefit' => null,
                'source_dup_key' => 'seogu_youth:' . $id,
            ]);
            $count++;
        }
        $this->mark('seogu_youth', 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    /**
     * 동구·서구의 행정 고시공고 시스템은 같은 NTIS 계열을 사용한다.
     * 검색 결과가 비어도 수집 실패로 처리하지 않고 다음 실행을 기다린다.
     */
    private function collectDongGuYouth(): array
    {
        $listUrl = self::DONGGU_LIST . '?' . http_build_query([
            'context' => 'NTIS',
            'homepage_pbs_yn' => 'Y',
            'jndinm' => 'OfrNotAncmtEJB',
            'method' => 'selectListOfrNotAncmt',
            'methodnm' => 'selectListOfrNotAncmtHomepage',
            'not_ancmt_se_code' => '01,04,05',
            'ofr_pageSize' => 50,
            'subCheck' => 'Y',
            'Key' => 'B_Subject',
            'temp' => '청년',
        ]);
        $this->registerSource('donggu_youth', '광주 동구청 청년 공고', $listUrl, 'local_web');
        $html = $this->get($listUrl);
        if ($html === '') {
            return $this->failed('광주 동구청 응답 없음', '');
        }

        preg_match_all('/searchDetail\([\'"]([^\'"]+)[\'"]\).*?>(.*?)<\/a>/su', $html, $matches, PREG_SET_ORDER);
        $count = 0;
        foreach (array_slice($matches, 0, 50) as $match) {
            $id = trim($match[1]);
            $title = $this->text($match[2]);
            if ($id === '' || $title === '' || ! $this->isYouthNotice($title)) {
                continue;
            }
            $detailUrl = self::DONGGU_DETAIL . '?' . http_build_query([
                'context' => 'NTIS',
                'jndinm' => 'OfrNotAncmtEJB',
                'method' => 'selectOfrNotAncmt',
                'methodnm' => 'selectOfrNotAncmtRegst',
                'not_ancmt_mgt_no' => $id,
                'homepage_pbs_yn' => 'Y',
                'subCheck' => 'Y',
            ]);
            $raw = $this->get($detailUrl);
            $this->upsert([
                'source_id' => 'donggu_youth',
                'source_name' => '광주 동구청 청년 공고',
                'source_type' => 'local_web',
                'list_url' => $listUrl,
                'original_url' => $detailUrl,
                'title' => $title,
                'posted_at' => null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text($raw),
                'attachment_urls' => null,
                'organizer' => '광주 동구청',
                'operator' => null,
                'contact' => null,
                'region' => '광주 동구',
                'raw_html_or_json' => $raw,
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => '청년',
                'benefit' => null,
                'source_dup_key' => 'donggu_youth:' . $id,
            ]);
            $count++;
        }
        $this->mark('donggu_youth', 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    private function collectNamGuYouth(): array
    {
        $this->registerSource('namgu_youth', '광주 남구청 청년 공고', self::NAMGU_LIST, 'local_web');
        $html = $this->get(self::NAMGU_LIST . '&nPage=1');
        if ($html === '') {
            return $this->failed('광주 남구청 응답 없음', '');
        }

        preg_match_all('/<a[^>]+href=["\']([^"\']*list_no=[^"\']+)["\'][^>]*>(.*?)<\/a>/su', $html, $matches, PREG_SET_ORDER);
        $count = 0;
        $seen = [];
        foreach (array_slice($matches, 0, 100) as $match) {
            $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = $this->text($match[2]);
            if (! preg_match('/(?:^|[?&])list_no=([0-9]+)/', $href, $idMatch)) {
                continue;
            }
            $id = $idMatch[1];
            if (isset($seen[$id]) || ! $this->isYouthNotice($title)) {
                continue;
            }
            $seen[$id] = true;
            $detailUrl = 'https://www.namgu.gwangju.kr' . (str_starts_with($href, '/') ? $href : '/' . $href);
            $raw = $this->get($detailUrl);
            $this->upsert([
                'source_id' => 'namgu_youth',
                'source_name' => '광주 남구청 청년 공고',
                'source_type' => 'local_web',
                'list_url' => self::NAMGU_LIST,
                'original_url' => $detailUrl,
                'title' => $title,
                'posted_at' => null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text($raw),
                'attachment_urls' => null,
                'organizer' => '광주 남구청',
                'operator' => null,
                'contact' => null,
                'region' => '광주 남구',
                'raw_html_or_json' => $raw,
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => '청년',
                'benefit' => null,
                'source_dup_key' => 'namgu_youth:' . $id,
            ]);
            $count++;
        }
        $this->mark('namgu_youth', 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    private function isYouthNotice(string $title): bool
    {
        return mb_strpos($title, '청년') !== false;
    }

    private function collectGwangjuYouthCenter(): array
    {
        $this->registerSource('gwangju_youth_center', '광주청년센터', self::GJ_CENTER_API, 'public_api');
        $list = json_decode($this->get(self::GJ_CENTER_API), true);
        if (!is_array($list) || !is_array($list['items'] ?? null)) {
            return $this->failed('광주청년센터 API 응답 오류', '');
        }
        $rows = array_merge($list['pinnedItems'] ?? [], $list['items']);
        $count = 0;
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $detailUrl = self::GJ_CENTER_API . '/' . rawurlencode($id);
            $detail = json_decode($this->get($detailUrl), true);
            if (!is_array($detail)) {
                continue;
            }
            $this->upsert([
                'source_id' => 'gwangju_youth_center',
                'source_name' => '광주청년센터',
                'source_type' => 'public_api',
                'list_url' => self::GJ_CENTER_API,
                'original_url' => 'https://gjyouthcenter.kr/news/notices/detail/?id=' . rawurlencode($id),
                'title' => $this->text($detail['title'] ?? $row['title'] ?? ''),
                'posted_at' => $detail['createdAt'] ?? null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $detail['content'] ?? '',
                'attachment_urls' => json_encode($detail['files'] ?? [], JSON_UNESCAPED_UNICODE),
                'organizer' => '광주청년센터',
                'operator' => $detail['authorName'] ?? null,
                'contact' => null,
                'region' => '광주',
                'raw_html_or_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => '청년',
                'benefit' => null,
                'source_dup_key' => 'gwangju_youth_center:' . $id,
            ]);
            $count++;
        }
        $this->mark('gwangju_youth_center', 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    private function collectSubsidy(int $limit): array
    {
        $this->registerSource('national_subsidy', '기획재정부 국고보조금 공모사업', self::MOEF_URL, 'public_api');
        $url = self::MOEF_URL . '?' . http_build_query([
            'serviceKey' => $this->serviceKey,
            'page' => 1,
            'perPage' => min(100, max(1, $limit)),
            'resultType' => 'json',
        ]);
        $json = json_decode($this->get($url), true);
        if (!is_array($json) || isset($json['OpenAPI_ServiceResponse'])) {
            return $this->failed('국고보조금 API 인증/응답 오류', '');
        }

        $items = $json['body']['items']['item'] ?? [];
        if (isset($items['BSNSYEAR'])) {
            $items = [$items];
        }
        $count = 0;
        foreach (is_array($items) ? $items : [] as $row) {
            $key = (string) ($row['ASBS_BSNS_ID'] ?? $row['DTLBZ_ID'] ?? '');
            $title = trim((string) ($row['ASBS_BSNS_NM'] ?? $row['DTLBZ_NM'] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $this->upsert([
                'source_id' => 'national_subsidy',
                'source_name' => '기획재정부 국고보조금 공모사업',
                'source_type' => 'public_api',
                'list_url' => self::MOEF_URL,
                'original_url' => null,
                'title' => $this->text($title),
                'posted_at' => null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text(json_encode($row, JSON_UNESCAPED_UNICODE)),
                'attachment_urls' => null,
                'organizer' => null,
                'operator' => null,
                'contact' => null,
                'region' => null,
                'raw_html_or_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => '국고보조금',
                'benefit' => null,
                'source_dup_key' => 'national_subsidy:' . $key,
            ]);
            $count++;
        }
        $this->mark('national_subsidy', 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    private function collectBids(int $limit): array
    {
        $this->registerSource('나라장터', '조달청 나라장터 입찰공고', self::BID_BASE . 'getBidPblancListInfoCnstwk', 'public_api');
        // 자정 직후에도 전날 등록 공고가 누락되지 않도록 최근 2일 조회
        $today = date('Ymd');
        $from = date('Ymd', strtotime('-1 day'));
        $count = 0;
        $errors = [];
        foreach (['Cnstwk', 'Servc', 'Thng'] as $type) {
            $endpoint = self::BID_BASE . 'getBidPblancListInfo' . $type;
            $url = $endpoint . '?' . http_build_query([
                'serviceKey' => $this->serviceKey,
                'pageNo' => 1,
                'numOfRows' => min(100, max(1, $limit)),
                'type' => 'json',
                'inqryDiv' => '1',
                'inqryBgnDt' => $from . '0000',
                'inqryEndDt' => $today . '2359',
            ]);
            $json = json_decode($this->get($url), true);
            $items = $json['response']['body']['items'] ?? [];
            if (isset($items['bidNtceNo'])) {
                $items = [$items];
            }
            if (!is_array($items)) {
                $errors[] = $type;
                continue;
            }
            foreach ($items as $row) {
                $key = (string) ($row['bidNtceNo'] ?? '');
                $title = trim((string) ($row['bidNtceNm'] ?? ''));
                if ($key === '' || $title === '') {
                    continue;
                }
                $this->upsert([
                    'source_id' => '나라장터',
                    'source_name' => '조달청 나라장터 입찰공고',
                    'source_type' => 'public_api',
                    'list_url' => $endpoint,
                    'original_url' => $row['bidNtceUrl'] ?? null,
                    'title' => $this->text($title),
                    'posted_at' => $row['bidNtceDt'] ?? null,
                    'apply_start' => $row['bidBeginDt'] ?? null,
                    'apply_end' => $row['bidClseDt'] ?? null,
                    'body_text' => $this->text(($row['ntceInsttNm'] ?? '') . ' ' . ($row['presmptPrce'] ?? '') . ' ' . ($row['bidMethdNm'] ?? '')),
                    'attachment_urls' => null,
                    'organizer' => $row['ntceInsttNm'] ?? null,
                    'operator' => $row['ntceInsttOfclNm'] ?? null,
                    'contact' => $row['ntceInsttOfclTelNo'] ?? null,
                    'region' => null,
                    'raw_html_or_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'collected_at' => date('Y-m-d H:i:s'),
                    'category' => '입찰공고',
                    'benefit' => null,
                    'source_dup_key' => '나라장터:' . $type . ':' . $key,
                ]);
                $count++;
            }
        }
        $this->mark('나라장터', $errors === [] ? 'success count=' . $count : 'partial count=' . $count . ' errors=' . implode(',', $errors));
        return ['status' => $errors === [] ? 'success' : 'partial', 'count' => $count, 'errors' => $errors];
    }

    private function collectKStartupBusiness(int $limit): array
    {
        $url = self::KSTARTUP_BASE . 'getBusinessInformation01';
        $this->registerSource('kstartup_business', 'K-Startup 사업소개', $url, 'public_api');
        return $this->collectSimpleKStartup($url, 'kstartup_business', '사업소개', $limit,
            static fn (array $r): array => [
                'key' => 'business:' . ($r['id'] ?? ''),
                'title' => $r['supt_biz_titl_nm'] ?? '',
                'body' => ($r['supt_biz_intrd_info'] ?? '') . "\n" . ($r['supt_biz_chrct'] ?? '') . "\n" . ($r['biz_supt_ctnt'] ?? ''),
                'target' => $r['biz_supt_trgt_info'] ?? '',
                'url' => $r['detl_pg_url'] ?? '',
                'category' => $r['biz_category_cd'] ?? '',
            ]);
    }

    private function collectKStartupContent(int $limit): array
    {
        $url = self::KSTARTUP_BASE . 'getContentInformation01';
        $this->registerSource('kstartup_content', 'K-Startup 콘텐츠', $url, 'public_api');
        return $this->collectSimpleKStartup($url, 'kstartup_content', '콘텐츠', $limit,
            static fn (array $r): array => [
                'key' => 'content:' . (preg_match('/[?&]id=([0-9]+)/', (string) ($r['detl_pg_url'] ?? ''), $m) ? $m[1] : ''),
                'title' => $r['titl_nm'] ?? '',
                'body' => '',
                'target' => '',
                'url' => $r['detl_pg_url'] ?? '',
                'category' => $r['clss_cd'] ?? '',
            ]);
    }

    private function collectSimpleKStartup(string $endpoint, string $sourceId, string $label, int $limit, callable $map): array
    {
        $url = $endpoint . '?' . http_build_query([
            'serviceKey' => $this->serviceKey,
            'pageNo' => 1,
            'numOfRows' => min(100, max(1, $limit)),
            'returnType' => 'json',
        ]);
        $json = json_decode($this->get($url), true);
        if (!is_array($json) || !is_array($json['data'] ?? null)) {
            return $this->failed($label . ' 응답 오류 또는 인증키/엔드포인트 오류', '');
        }
        $count = 0;
        foreach ($json['data'] as $raw) {
            $mapped = $map($raw);
            if (trim((string) $mapped['key']) === '' || trim((string) $mapped['title']) === '') {
                continue;
            }
            $this->upsert([
                'source_id' => $sourceId,
                'source_name' => '창업진흥원 K-Startup ' . $label,
                'source_type' => 'public_api',
                'list_url' => $endpoint,
                'original_url' => $mapped['url'] ?: null,
                'title' => $this->text($mapped['title']),
                'posted_at' => null,
                'apply_start' => null,
                'apply_end' => null,
                'body_text' => $this->text($mapped['body']),
                'attachment_urls' => null,
                'organizer' => '창업진흥원',
                'operator' => null,
                'contact' => null,
                'region' => null,
                'raw_html_or_json' => json_encode($raw, JSON_UNESCAPED_UNICODE),
                'collected_at' => date('Y-m-d H:i:s'),
                'category' => $mapped['category'],
                'benefit' => null,
                'source_dup_key' => $sourceId . ':' . $mapped['key'],
            ]);
            $count++;
        }
        $this->mark($sourceId, 'success count=' . $count);
        return ['status' => 'success', 'count' => $count];
    }

    private function upsert(array $row): void
    {
        $columns = array_keys($row);
        $values = array_map(static fn ($column) => ':' . $column, $columns);
        $updates = array_map(static fn ($column) => $column . '=excluded.' . $column, array_diff($columns, ['source_dup_key']));
        $sql = 'INSERT INTO crawl_notice (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ') '
            . 'ON CONFLICT(source_dup_key) DO UPDATE SET ' . implode(',', $updates) . ',updated_at=datetime(\'now\')';
        $stmt = $this->db->prepare($sql);
        foreach ($row as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    private function mark(string $source, string $note): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO sync_meta(source,synced_at,note) VALUES(:source,:time,:note)
             ON CONFLICT(source) DO UPDATE SET synced_at=excluded.synced_at,note=excluded.note"
        );
        $stmt->execute(['source' => 'crawl:' . $source, 'time' => microtime(true), 'note' => $note]);
    }

    private function get(string $url): string
    {
        $context = stream_context_create(['http' => [
            'timeout' => max(3, (int) env('HTTP_API_TIMEOUT', 8)),
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: jn-support-crawler/1.0\r\n",
        ]]);
        return (string) @file_get_contents($url, false, $context);
    }

    private function date($value): ?string
    {
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        if (strlen($value) < 8) {
            return null;
        }
        return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    private function text($value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function failed(string $message, string $raw): array
    {
        $this->mark('unknown', $message . ' ' . substr($raw, 0, 100));
        return ['status' => 'failed', 'count' => 0, 'message' => $message];
    }

    private function notConfigured(string $name, string $message): array
    {
        return ['status' => 'not_configured', 'count' => 0, 'message' => $name . ': ' . $message];
    }

    private function registerSource(string $id, string $name, string $url, string $type): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO crawl_source(source_id,source_name,source_type,list_url,crawl_method,last_collected_at)
             VALUES(:id,:name,:type,:url,:method,NULL)
             ON CONFLICT(source_id) DO UPDATE SET source_name=excluded.source_name,
             source_type=excluded.source_type,list_url=excluded.list_url,crawl_method=excluded.crawl_method"
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'url' => $url,
            'method' => $type === 'local_web' ? 'http_html' : 'api',
        ]);
    }
}
