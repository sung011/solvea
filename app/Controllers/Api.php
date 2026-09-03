<?php

namespace App\Controllers;

use App\Libraries\SearchService;
use CodeIgniter\HTTP\ResponseInterface;

class Api extends BaseController
{
    private SearchService $search;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->search = new SearchService();
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

    private function json(array $payload): ResponseInterface
    {
        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($payload);
    }
}
