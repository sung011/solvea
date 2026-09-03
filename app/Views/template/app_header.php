<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>나도대상?</title>
    <link rel="stylesheet" href="/css/style.css"/>
    <script>
        const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    </script>
    <script src="/js/common/api.js"></script>
    <script src="/js/common/sweetalert2.all.min.js"></script>
    <script src="/js/common/utils.js"></script>
</head>
<body class="<?= esc($bodyClass ?? 'page-home') ?>">
<header class="site-header">
    <a class="brand" href="/">
      <span class="brand-mark" aria-hidden="true">
        <!--<svg width="28" height="28" viewBox="0 0 32 32" fill="none">
          <defs>
            <linearGradient id="star" x1="4" y1="2" x2="28" y2="30" gradientUnits="userSpaceOnUse">
              <stop stop-color="#3B82F6"/>
              <stop offset="0.55" stop-color="#60A5FA"/>
              <stop offset="1" stop-color="#FB7185"/>
            </linearGradient>
          </defs>
          <path fill="url(#star)"
                d="M16 1.6c.4 0 .7.2.9.6l3.5 7.6c.1.3.4.5.7.6l8.1 1.1c.9.1 1.2 1.2.6 1.8l-5.9 5.6c-.2.2-.3.5-.3.8l1.5 8c.2.9-.8 1.5-1.6 1.1L16 25.4c-.3-.1-.6-.1-.9 0l-7.5 4.1c-.8.4-1.8-.2-1.6-1.1l1.5-8c0-.3 0-.6-.3-.8L1.3 13.3c-.6-.6-.3-1.7.6-1.8l8.1-1.1c.3 0 .6-.3.7-.6L16.2 2.2c.2-.4.5-.6.8-.6Z"/>
        </svg>-->
      </span>
        나도대상?
    </a>
    <div class="header-right">
      <span class="region-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z" stroke="#6B7280" stroke-width="1.8"/>
          <circle cx="12" cy="10" r="2.2" stroke="#6B7280" stroke-width="1.8"/>
        </svg>
        광주 · 전남 청년 지원사업
      </span>
    </div>
</header>
