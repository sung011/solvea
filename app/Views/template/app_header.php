<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?= esc($pageTitle ?? '나도대상?') ?></title>
    <link rel="stylesheet" href="/css/style.css?v=20260904i"/>
    <script>
        const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    </script>
    <script src="/js/common/api.js"></script>
    <script src="/js/common/sweetalert2.all.min.js"></script>
    <script src="/js/common/utils.js"></script>
</head>
<body class="<?= esc($bodyClass ?? 'page-home') ?>">
<header class="site-header">
    <div class="header-left">
        <a class="brand" href="/">
            <span class="brand-mark" aria-hidden="true">
              <svg viewBox="0 0 40 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                  <linearGradient id="brandGrad" x1="6" y1="4" x2="34" y2="44" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#5FE3C0"/>
                    <stop offset=".5" stop-color="#1CB8A8"/>
                    <stop offset="1" stop-color="#0A8F8A"/>
                  </linearGradient>
                </defs>
                <path d="M20 4c-7.2 0-13 5-13 12 0 4.2 2.1 7.3 5.6 9.7 2.3 1.6 3.9 3.3 4.2 6.1h6.1c-.2-4.1-2.3-6.5-5.2-8.5-2.8-1.9-4.3-3.7-4.3-7.1 0-4.1 3.1-6.9 6.6-6.9s6.5 2.6 6.5 6.4c0 2.1-.8 3.7-2.4 5.1l4.3 3.6c2.6-2.4 4.1-5.5 4.1-9.2C32.5 8.6 27.1 4 20 4Z"
                      fill="url(#brandGrad)"/>
                <path d="M17.2 36.2c1.1 1.9 2.7 3.6 4.9 5.1.8.55 1.8.2 2.2-.7l1.1-2.4c.35-.8-.05-1.7-.9-2-1.6-.6-2.8-1.4-3.7-2.4l-3.6 2.4Z"
                      fill="url(#brandGrad)"/>
                <path d="M29 9h3.5M31.2 6.2l2 2M33.2 11.8l2-1.2" stroke="url(#brandGrad)" stroke-width="2.6"
                      stroke-linecap="round"/>
              </svg>
            </span>
            
            나도대상?
        </a>
        <?php if (($bodyClass ?? '') !== 'page-auth'): ?>
            <nav class="nav-links" id="main-nav" aria-label="주 메뉴">
                <a class="new-chat-link <?= in_array(($bodyClass ?? ''), ['page-home', 'page-results'], true) ? 'is-active' : '' ?>"
                   href="/">
                    새 대화
                </a>
                <a class="<?= ($bodyClass ?? '') === 'page-favorites' ? 'is-active' : '' ?>" href="/favorites">저장한
                    정책</a>
            </nav>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <?php if (($bodyClass ?? '') !== 'page-auth'): ?>
            <span class="header-example">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z" stroke="#6B7280" stroke-width="1.8"/>
          <circle cx="12" cy="10" r="2.2" stroke="#6B7280" stroke-width="1.8"/>
        </svg>
        정책 · 조건 예시
      </span>
            <?php if (!empty($currentUser)): ?>
                <span class="header-user"><?= esc($currentUser['name'] ?: $currentUser['user_id']) ?> 님</span>
                <a class="header-link is-ghost" href="/logout">로그아웃</a>
            <?php else: ?>
                <a class="header-link is-ghost" href="/login">로그인</a>
                <a class="header-link" href="/signup">회원가입</a>
            <?php endif; ?>
            <button class="menu-toggle" type="button" aria-label="메뉴 열기" aria-expanded="false"
                    aria-controls="menu-panel">
                <span></span><span></span><span></span>
            </button>
        <?php elseif (($headerAction ?? '') === 'signup'): ?>
            <a class="header-link" href="/signup">회원가입</a>
        <?php else: ?>
            <a class="header-link" href="/login">로그인</a>
        <?php endif; ?>
    </div>
</header>
<?php if (($bodyClass ?? '') !== 'page-auth'): ?>
    <div class="menu-backdrop" id="menu-backdrop" aria-hidden="true"></div>
    <aside class="menu-panel" id="menu-panel" aria-hidden="true" aria-label="추천 검색">
        <div class="menu-panel-head">
            <strong>추천 질문</strong>
            <button type="button" class="menu-panel-close" id="menu-panel-close" aria-label="메뉴 닫기">×</button>
        </div>
        <div class="menu-recommendations">
            <button type="button" class="menu-recommendation" data-q="청년 주거 지원을 찾아줘">청년 주거 지원</button>
            <button type="button" class="menu-recommendation" data-q="취업 준비 지원 정책을 찾아줘">취업 준비 지원</button>
            <button type="button" class="menu-recommendation" data-q="청년 교통비 지원이 있어?">청년 교통비 지원</button>
            <button type="button" class="menu-recommendation" data-q="창업 지원 정책을 찾아줘">창업 지원</button>
            <button type="button" class="menu-recommendation" data-q="교육비와 직업훈련 지원을 찾아줘">교육·직업훈련 지원</button>
        </div>
        <div class="menu-panel-links">
            <a href="/">새 대화</a>
            <a href="/favorites">저장한 정책</a>
        </div>
    </aside>
    <style>
        .menu-toggle {
            display: inline-grid;
            flex-shrink: 0;
            gap: 5px;
            width: 40px;
            height: 40px;
            place-content: center;
            padding: 0;
            border: 1px solid #a8dcd6;
            border-radius: 10px;
            background: #fff;
            cursor: pointer
        }

        .menu-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 2px;
            background: #176f70
        }

        .menu-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(16, 43, 59, .35);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .22s ease, visibility .22s ease
        }

        .menu-backdrop.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto
        }

        .menu-panel {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            width: min(320px, calc(100vw - 18px));
            height: 100vh;
            height: 100dvh;
            padding: 22px 18px 28px;
            border-left: 1px solid #e8eaed;
            border-radius: 22px 0 0 22px;
            background: #fff;
            box-shadow: -16px 0 40px rgba(16, 43, 59, .12);
            transform: translate3d(100%, 0, 0);
            transition: transform .24s ease;
            pointer-events: none;
            visibility: hidden
        }

        .menu-panel.is-open {
            transform: translate3d(0, 0, 0);
            pointer-events: auto;
            visibility: visible
        }

        .menu-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px
        }

        .menu-panel-head strong {
            color: #111827;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.03em
        }

        .menu-panel-close {
            width: 36px;
            height: 36px;
            border: 1px solid #d1d5db;
            border-radius: 50%;
            background: #fff;
            color: #4b5563;
            font-size: 22px;
            line-height: 1;
            cursor: pointer
        }

        .menu-recommendations {
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .menu-recommendation {
            display: block;
            width: 100%;
            min-height: 52px;
            padding: 14px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 15px;
            font-weight: 600;
            text-align: left;
            cursor: pointer
        }

        .menu-recommendation:hover {
            border-color: #087773;
            background: #f3fcfa;
            color: #087773
        }

        .menu-panel-links {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: auto;
            padding-top: 22px;
            border-top: 1px solid #e5e7eb
        }

        .menu-panel-links a {
            color: #176f70;
            font-size: 15px;
            font-weight: 700
        }

        body.menu-open {
            overflow: hidden
        }

        @media (min-width: 721px) {
            .menu-panel {
                width: min(360px, 40vw);
                border-radius: 0
            }
        }
    </style>
    <script>
        (function () {
            function initMenu() {
                var toggle = document.querySelector(".menu-toggle");
                var panel = document.getElementById("menu-panel");
                var backdrop = document.getElementById("menu-backdrop");
                var closeBtn = document.getElementById("menu-panel-close");
                if (!toggle || !panel || toggle.dataset.menuBound) return;
                toggle.dataset.menuBound = "1";

                function setOpen(open) {
                    panel.classList.toggle("is-open", open);
                    panel.setAttribute("aria-hidden", open ? "false" : "true");
                    toggle.setAttribute("aria-expanded", open ? "true" : "false");
                    toggle.setAttribute("aria-label", open ? "메뉴 닫기" : "메뉴 열기");
                    document.body.classList.toggle("menu-open", open);
                    if (backdrop) {
                        backdrop.classList.toggle("is-open", open);
                        backdrop.setAttribute("aria-hidden", open ? "false" : "true");
                    }
                }

                toggle.addEventListener("click", function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    setOpen(!panel.classList.contains("is-open"));
                });
                if (closeBtn) closeBtn.addEventListener("click", function () {
                    setOpen(false);
                });
                if (backdrop) backdrop.addEventListener("click", function () {
                    setOpen(false);
                });
                document.addEventListener("keydown", function (e) {
                    if (e.key === "Escape") setOpen(false);
                });
                panel.querySelectorAll(".menu-recommendation").forEach(function (btn) {
                    btn.addEventListener("click", function () {
                        var q = (btn.getAttribute("data-q") || "").trim();
                        if (!q) return;
                        setOpen(false);
                        location.href = "/results?q=" + encodeURIComponent(q);
                    });
                });
            }

            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initMenu);
            else initMenu();
        })();
    </script>
<?php endif; ?>
