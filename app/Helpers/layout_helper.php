<?php

/**
 * 레이아웃 헬퍼 — 뷰를 공통 헤더/푸터로 감쌉니다.
 */
if (! function_exists('current_user')) {
    /**
     * @return array{user_id: string, name?: mixed, age?: mixed, address?: mixed}|null
     */
    function current_user(): ?array
    {
        $session = session();
        if (! $session->get('logged_in')) {
            return null;
        }
        $user = $session->get('user');

        return is_array($user) ? $user : null;
    }
}

if (! function_exists('render')) {
    function render(string $name, array $data = [], array $options = []): string
    {
        $isAdmPage = isset($data['isAdmPage']) && $data['isAdmPage'] === true;
        $user = $data['currentUser'] ?? current_user();

        return view('template/layout', [
            'content'      => view($name, $data + ['currentUser' => $user], $options),
            'isAdmPage'    => $isAdmPage,
            'bodyClass'    => $data['bodyClass'] ?? 'page-home',
            'pageTitle'    => $data['pageTitle'] ?? '나도대상?',
            'headerAction' => $data['headerAction'] ?? '',
            'isAuthPage'   => ! empty($data['isAuthPage']),
            'currentUser'  => $user,
        ], $options);
    }
}
