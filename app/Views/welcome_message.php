<main class="hero">
    <h1>공고는 찾았는데,<br/>나한테 될까요?</h1>
    <p>나이, 거주, 취업 상태를 말하면 지역 지원사업 자격과<br/>준비 서류를 공고 근거와 함께 찾아 드립니다.</p>
</main>

<section class="prompt-grid" aria-label="빠른 검색">
    <button class="prompt-card" type="button" data-q="광주 27살 구직중">
      <span class="prompt-icon icon-user">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor"
                                                                            stroke-width="1.8"/><path
                    d="M5.5 19.2c1.4-3 3.7-4.5 6.5-4.5s5.1 1.5 6.5 4.5" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round"/></svg>
      </span>
        광주 27살 · 구직중
    </button>
    <button class="prompt-card" type="button" data-q="전남 취업자 월세 지원">
      <span class="prompt-icon icon-job">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="7" width="17" height="13" rx="2"
                                                                          stroke="currentColor" stroke-width="1.8"/><path
                    d="M8 7V6.2A2.2 2.2 0 0 1 10.2 4h3.6A2.2 2.2 0 0 1 16 6.2V7" stroke="currentColor"
                    stroke-width="1.8"/></svg>
      </span>
        전남 취업자 · 월세
    </button>
    <button class="prompt-card" type="button" data-q="임차보증금 이자지원">
      <span class="prompt-icon icon-home">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path
                    d="M4 11.5 12 4l8 7.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-8.5Z" stroke="currentColor"
                    stroke-width="1.8" stroke-linejoin="round"/></svg>
      </span>
        임차보증금 이자지원
    </button>
    <button class="prompt-card" type="button" data-q="청년 주거 지원 서류가 뭐가 필요해?">
      <span class="prompt-icon icon-doc">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path
                    d="M7 3.5h7l5 5V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor"
                    stroke-width="1.8"/><path d="M14 3.5V9h5.5M8.5 13h7M8.5 16.5h5" stroke="currentColor"
                                              stroke-width="1.8" stroke-linecap="round"/></svg>
      </span>
        서류가 뭐가 필요해?
    </button>
</section>
<form class="composer-wrap" id="ask" name="ask" autocomplete="off">
    <div class="composer">
        <input id="q" name="q" placeholder="예: 광주 살고 구직 중인데 27살이에요" autocomplete="off"/>
        <button class="send" type="submit" aria-label="검색">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M4 11.5 20 4l-5.2 16.5-3.1-6.2L4 11.5Z" fill="currentColor"/>
            </svg>
        </button>
    </div>
</form>
<script src="<?= base_url() ?>js/welcome.js"></script>
