<main class="results">
  <section class="chat-thread" aria-label="대화">
    <h1 class="chat-topic" id="result-title">정책 검색 결과</h1>
    <div class="chat-bubble chat-bubble-user" id="chat-user">
      <p id="chat-user-text"></p>
    </div>
    <div class="chat-row chat-row-bot">
      <span class="chat-bot-icon" aria-hidden="true">
        <svg viewBox="0 0 40 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="botGrad" x1="6" y1="4" x2="34" y2="44" gradientUnits="userSpaceOnUse">
              <stop stop-color="#5FE3C0"/>
              <stop offset=".5" stop-color="#1CB8A8"/>
              <stop offset="1" stop-color="#0A8F8A"/>
            </linearGradient>
          </defs>
          <path d="M20 4c-7.2 0-13 5-13 12 0 4.2 2.1 7.3 5.6 9.7 2.3 1.6 3.9 3.3 4.2 6.1h6.1c-.2-4.1-2.3-6.5-5.2-8.5-2.8-1.9-4.3-3.7-4.3-7.1 0-4.1 3.1-6.9 6.6-6.9s6.5 2.6 6.5 6.4c0 2.1-.8 3.7-2.4 5.1l4.3 3.6c2.6-2.4 4.1-5.5 4.1-9.2C32.5 8.6 27.1 4 20 4Z" fill="url(#botGrad)"/>
          <path d="M17.2 36.2c1.1 1.9 2.7 3.6 4.9 5.1.8.55 1.8.2 2.2-.7l1.1-2.4c.35-.8-.05-1.7-.9-2-1.6-.6-2.8-1.4-3.7-2.4l-3.6 2.4Z" fill="url(#botGrad)"/>
          <path d="M29 9h3.5M31.2 6.2l2 2M33.2 11.8l2-1.2" stroke="url(#botGrad)" stroke-width="2.6" stroke-linecap="round"/>
        </svg>
      </span>
      <div class="chat-bubble chat-bubble-bot">
        <p id="result-sub">입력하신 정보를 바탕으로 공고를 비교했어요. 모르는 항목은 확인 방법을 눌러 주세요.</p>
      </div>
    </div>
  </section>

  <div class="result-list" id="list">
    <div class="loading">불러오는 중…</div>
  </div>
  <div class="pager">
    <button type="button" id="prev" disabled>이전</button>
    <button type="button" id="next" disabled>다음</button>
  </div>
</main>

<div class="dock">
  <form class="composer" id="ask" name="ask" autocomplete="off">
    <textarea id="q" name="q" rows="1" placeholder="확인한 내용을 입력하면 정책 결과에 반영돼요" autocomplete="off"></textarea>
    <button class="send" type="submit" aria-label="추가 질문">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 19V5M12 5l-6 6M12 5l6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </form>
  <p class="composer-help">Enter로 전송 · Shift+Enter로 줄바꿈</p>
</div>

<dialog class="policy-dialog" id="policy-dialog">
  <div class="policy-sheet">
    <button class="dialog-close" type="button" data-close-dialog aria-label="닫기">×</button>
    <p class="dialog-kicker" id="dialog-kicker">정책 조건 확인</p>
    <h2 id="policy-dialog-title">내 조건과 비교</h2>
    <p class="dialog-lead" id="policy-dialog-description"></p>
    <div id="policy-dialog-content"></div>
    <div class="dialog-note" id="policy-dialog-note"></div>
    <div class="dialog-actions">
      <a id="policy-dialog-link" class="dialog-secondary" href="#" target="_blank" rel="noopener">공고 원문 보기 ↗</a>
      <button id="policy-dialog-action" class="dialog-primary" type="button">확인</button>
    </div>
  </div>
</dialog>
