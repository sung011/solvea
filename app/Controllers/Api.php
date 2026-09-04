<?php

namespace App\Controllers;

use App\Libraries\SearchService;
use App\Libraries\CrawlService;
use App\Libraries\FavoriteService;
use CodeIgniter\HTTP\ResponseInterface;

class Api extends BaseController
{
    private SearchService $search;
    private FavoriteService $favorites;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->search = new SearchService();
        $this->favorites = new FavoriteService();
    }

    public function recommend(): ResponseInterface
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q === '') {
            return $this->json([
                'resultCode' => '99',
                'resultMsg'  => '검색어가 필요합니다.',
                'totalCount' => 0,
                'items'      => [],
            ]);
        }

        $startPage = max(1, (int) ($this->request->getGet('startPage') ?: 1));
        $pageSize  = max(1, min(50, (int) ($this->request->getGet('pageSize') ?: 8)));

        return $this->json($this->search->recommend($q, $startPage, $pageSize));
    }

    public function detail(): ResponseInterface
    {
        $jobKey = trim((string) $this->request->getGet('jobKey'));
        $kind   = trim((string) ($this->request->getGet('kind') ?: 'support'));

        return $this->json($this->search->detail($kind, $jobKey));
    }

    public function subsidy24(): ResponseInterface
    {
        $region = trim((string)($this->request->getGet('region') ?: '전남'));

        return $this->json($this->search->subsidyLatest($region));
    }

    public function crawlRun(): ResponseInterface
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->json(['resultCode' => '405', 'resultMsg' => 'POST 요청만 허용됩니다.']);
        }

        return $this->json((new CrawlService())->runAll());
    }

    public function crawlStatus(): ResponseInterface
    {
        return $this->json((new CrawlService())->status());
    }

    public function favorites(): ResponseInterface
    {
        $user = session()->get('user');
        if (! session()->get('logged_in') || ! is_array($user)) {
            return $this->response->setStatusCode(401)->setJSON(['detail' => '로그인이 필요합니다.']);
        }

        return $this->json(['items' => $this->favorites->list((string) ($user['user_id'] ?? ''))]);
    }

    public function saveFavorite(): ResponseInterface
    {
        $user = session()->get('user');
        if (! session()->get('logged_in') || ! is_array($user)) {
            return $this->response->setStatusCode(401)->setJSON(['detail' => '로그인이 필요합니다.']);
        }

        $payload = $this->request->getJSON(true) ?? [];
        $result = $this->favorites->save((string) ($user['user_id'] ?? ''), is_array($payload) ? $payload : []);
        return $this->favoriteResponse($result);
    }

    public function removeFavorite(): ResponseInterface
    {
        $user = session()->get('user');
        if (! session()->get('logged_in') || ! is_array($user)) {
            return $this->response->setStatusCode(401)->setJSON(['detail' => '로그인이 필요합니다.']);
        }

        $payload = $this->request->getJSON(true) ?? [];
        $result = $this->favorites->remove(
            (string) ($user['user_id'] ?? ''),
            (string) ($payload['kind'] ?? ''),
            (string) ($payload['jobKey'] ?? '')
        );
        return $this->favoriteResponse($result);
    }

    private function favoriteResponse(array $result): ResponseInterface
    {
        if (! ($result['ok'] ?? false)) {
            return $this->response->setStatusCode(400)->setJSON([
                'detail' => $result['detail'] ?? '관심 공고 처리에 실패했습니다.',
            ]);
        }

        unset($result['ok']);
        return $this->json($result);
    }

    private function json(array $payload): ResponseInterface
    {
        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($payload);
    }
}
