<?php

namespace App\Controllers;

use App\Libraries\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseController
{
    private AuthService $auth;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->auth = new AuthService();
    }

    public function login(): string
    {
        return render('auth/login', [
            'bodyClass'    => 'page-auth',
            'pageTitle'    => '로그인 · 나도대상?',
            'headerAction' => 'signup',
            'isAuthPage'   => true,
        ]);
    }

    public function signup(): string
    {
        return render('auth/signup', [
            'bodyClass'    => 'page-auth',
            'pageTitle'    => '회원가입 · 나도대상?',
            'headerAction' => 'login',
            'isAuthPage'   => true,
        ]);
    }

    public function signupApi(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $result = $this->auth->signup(is_array($payload) ? $payload : []);

        if (! ($result['ok'] ?? false)) {
            return $this->response
                ->setStatusCode(400)
                ->setHeader('Cache-Control', 'no-store')
                ->setJSON(['detail' => $result['detail'] ?? '회원가입에 실패했습니다.']);
        }

        unset($result['ok']);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($result);
    }

    public function loginApi(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $result = $this->auth->login(
            (string) ($payload['user_id'] ?? ''),
            (string) ($payload['password'] ?? '')
        );

        if (! ($result['ok'] ?? false)) {
            return $this->response
                ->setStatusCode(401)
                ->setHeader('Cache-Control', 'no-store')
                ->setJSON(['detail' => $result['detail'] ?? '로그인에 실패했습니다.']);
        }

        unset($result['ok']);
        $this->startUserSession($result);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($result);
    }

    public function logout()
    {
        $session = session();
        $session->remove(['logged_in', 'user']);
        $session->destroy();

        if ($this->request->isAJAX() || str_starts_with($this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->response
                ->setHeader('Cache-Control', 'no-store')
                ->setJSON(['ok' => true]);
        }

        return redirect()->to('/');
    }

    public function me(): ResponseInterface
    {
        $user = session()->get('user');
        if (! session()->get('logged_in') || ! is_array($user)) {
            return $this->response
                ->setStatusCode(401)
                ->setHeader('Cache-Control', 'no-store')
                ->setJSON(['detail' => '로그인이 필요합니다.']);
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($user);
    }

    /**
     * @param array{user_id: string, name?: mixed, age?: mixed, address?: mixed} $user
     */
    private function startUserSession(array $user): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set([
            'logged_in' => true,
            'user'      => [
                'user_id' => (string) ($user['user_id'] ?? ''),
                'name'    => $user['name'] ?? null,
                'age'     => $user['age'] ?? null,
                'address' => $user['address'] ?? null,
            ],
        ]);
    }
}
