<main class="results">
  <div class="results-head">
    <div>
      <h1 id="result-title">공고 검색 결과</h1>
      <p id="result-sub">입력하신 정보를 바탕으로 확인된 공고입니다.</p>
    </div>
    <button class="summary-btn" id="summary-btn" type="button">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 4h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M9 9h6M9 13h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      결과 요약
    </button>
  </div>
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
    <input id="q" name="q" placeholder="추가로 물어보세요... (예: 소득 기준은 어떻게 되나요?)" autocomplete="off" />
    <button class="send" type="submit" aria-label="추가 질문">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 11.5 20 4l-5.2 16.5-3.1-6.2L4 11.5Z" fill="currentColor"/></svg>
    </button>
  </form>
</div>

<dialog class="summary-dialog" id="summary">
  <div class="summary-sheet">
    <h2>결과 요약</h2>
    <p id="summary-text"></p>
    <ul id="summary-list"></ul>
    <button type="button" id="summary-close">닫기</button>
  </div>
</dialog>
