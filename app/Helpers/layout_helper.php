<?php

/**
 * 레이아웃 헬퍼 — 뷰를 공통 헤더/푸터로 감쌉니다.
 */
if (! function_exists('render')) {
    function render(string $name, array $data = [], array $options = []): string
    {
        $isAdmPage = isset($data['isAdmPage']) && $data['isAdmPage'] === true;

        return view('template/layout', [
            'content'   => view($name, $data, $options),
            'isAdmPage' => $isAdmPage,
            'bodyClass' => $data['bodyClass'] ?? 'page-home',
        ], $options);
    }
}
