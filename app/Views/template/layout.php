<?php
/**
 * 공통 레이아웃 — 헤더 / 본문 / 푸터
 */
$isAdmPage = ! empty($isAdmPage);

$bodyClass = $bodyClass ?? 'page-home';

if ($isAdmPage) {
    echo view('template/adm_header', ['bodyClass' => $bodyClass]);
} else {
    echo view('template/app_header', ['bodyClass' => $bodyClass]);
}

echo $content ?? '';

if ($isAdmPage) {
    echo view('template/adm_footer');
} else {
    echo view('template/app_footer');
}
