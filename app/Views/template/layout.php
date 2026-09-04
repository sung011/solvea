<?php
/**
 * 공통 레이아웃 — 헤더 / 본문 / 푸터
 */
$isAdmPage = ! empty($isAdmPage);
$bodyClass = $bodyClass ?? 'page-home';
$pageTitle = $pageTitle ?? '나도대상?';
$headerAction = $headerAction ?? '';
$isAuthPage = ! empty($isAuthPage);

$headerData = [
    'bodyClass'    => $bodyClass,
    'pageTitle'    => $pageTitle,
    'headerAction' => $headerAction,
    'currentUser'  => $currentUser ?? null,
];

if ($isAdmPage) {
    echo view('template/adm_header', $headerData);
} else {
    echo view('template/app_header', $headerData);
}

echo $content ?? '';

if ($isAdmPage) {
    echo view('template/adm_footer');
} else {
    echo view('template/app_footer', [
        'isAuthPage' => $isAuthPage,
        'bodyClass' => $bodyClass,
    ]);
}
