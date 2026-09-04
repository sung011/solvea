<main class="hero">
    <span class="hero-mark" aria-hidden="true">
      <svg viewBox="0 0 80 96" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="heroGrad" x1="12" y1="8" x2="68" y2="90" gradientUnits="userSpaceOnUse">
            <stop stop-color="#5FE3C0"/>
            <stop offset=".45" stop-color="#1CB8A8"/>
            <stop offset="1" stop-color="#0A8F8A"/>
          </linearGradient>
        </defs>
        <path d="M40 8c-14.5 0-26 10.2-26 24.2 0 8.4 4.2 14.6 11.2 19.4 4.6 3.2 7.8 6.6 8.4 12.2h12.2c-.4-8.2-4.6-13-10.4-17-5.6-3.8-8.6-7.4-8.6-14.2 0-8.2 6.2-13.8 13.2-13.8 7.4 0 13 5.2 13 12.8 0 4.2-1.6 7.4-4.8 10.2l8.6 7.2c5.2-4.8 8.2-11 8.2-18.4C65 17.4 54.2 8 40 8Z" fill="url(#heroGrad)"/>
        <path d="M34.5 72.5c2.2 3.8 5.4 7.2 9.8 10.2 1.6 1.1 3.6.4 4.4-1.4l2.2-4.8c.7-1.6-.1-3.4-1.8-4-3.2-1.2-5.6-2.8-7.4-4.8l-7.2 4.8Z" fill="url(#heroGrad)"/>
        <path d="M58 18h7M62.5 12.5l4 4M66.5 23.5l4-2.5" stroke="url(#heroGrad)" stroke-width="5" stroke-linecap="round"/>
        <path d="M28 84.5c6.5 5.5 14.8 8.8 24.2 8.8" stroke="url(#heroGrad)" stroke-width="4" stroke-linecap="round"/>
      </svg>
    </span>
    <h1>지금 어떤 도움이 필요하신가요?</h1>
    <p>내 상황에 맞는 청년정책, 함께 찾아봐요.</p>

    <form class="composer-wrap" id="ask" name="ask" autocomplete="off">
        <div class="composer">
            <textarea id="q" name="q" rows="2" placeholder="지금 겪고 있는 고민을 자유롭게 적어 주세요" autocomplete="off"></textarea>
            <button class="send" type="submit" aria-label="검색">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 19V5M12 5l-6 6M12 5l6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <p class="composer-help">Enter로 전송 · Shift+Enter로 줄바꿈</p>
    </form>
</main>

<section class="home-prompts" aria-label="빠른 검색">
    <div class="prompt-grid">
        <button class="prompt-card" type="button" data-q="얼른 집에서 독립하고 싶어">얼른 집에서 독립하고 싶어</button>
        <button class="prompt-card" type="button" data-q="취업 준비 비용이 부담돼">취업 준비 비용이 부담돼</button>
        <button class="prompt-card" type="button" data-q="일하면서 배울 기회가 필요해">일하면서 배울 기회가 필요해</button>
    </div>
</section>
<script src="<?= base_url() ?>js/welcome.js"></script>
