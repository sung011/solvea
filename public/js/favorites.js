(function () {
  const list = document.getElementById("favorite-list");
  if (!list) return;

  const esc = (value) => String(value || "").replace(/[&<>"']/g, (ch) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[ch]));

  function toast(message) {
    if (typeof utils !== "undefined" && utils.showToast) utils.showToast(message);
  }

  function emptyHtml() {
    return `<div class="favorite-empty"><strong>아직 저장한 정책이 없어요.</strong><p>검색 결과에서 마음에 드는 공고를 저장해 보세요.</p><a href="/">새 정책 찾기</a></div>`;
  }

  function renderItems(items) {
    if (!items.length) {
      list.innerHTML = emptyHtml();
      return;
    }
    list.innerHTML = items.map((item) => `
      <article class="saved-card" data-kind="${esc(item.kind || "support")}" data-key="${esc(item.jobKey || "")}">
        <label class="favorite-toggle saved-check">
          <input class="favorite-check" type="checkbox" checked aria-label="관심 공고 저장 유지">
          <span class="favorite-box" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6.5 4.5h11v16l-5.5-3.5-5.5 3.5v-16Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
          </span>
          <span class="favorite-label">저장됨</span>
        </label>
        <div class="saved-copy">
          <h2>${esc(item.jobTitle || "제목 없는 공고")}</h2>
          <p>${esc(item.jobArea || "지역 정보 없음")}</p>
        </div>
        ${item.jobLink ? `<a class="saved-link" href="${esc(item.jobLink)}" target="_blank" rel="noopener">공고 보기</a>` : ""}
      </article>
    `).join("");
  }

  async function toggleSaved(card, checked) {
    const payload = {
      kind: card.dataset.kind || "support",
      jobKey: card.dataset.key || "",
      jobTitle: card.querySelector("h2")?.textContent || "",
      jobArea: card.querySelector(".saved-copy p")?.textContent || "",
      jobLink: card.querySelector(".saved-link")?.getAttribute("href") || "",
    };
    const res = await fetch("/api/favorites", {
      method: checked ? "POST" : "DELETE",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.detail || "저장 상태 변경에 실패했습니다.");
  }

  list.addEventListener("change", async (event) => {
    const input = event.target.closest(".favorite-check");
    if (!input) return;
    const card = input.closest(".saved-card");
    const label = card?.querySelector(".favorite-label");
    if (!card) return;

    input.disabled = true;
    try {
      await toggleSaved(card, input.checked);
      if (label) label.textContent = input.checked ? "저장됨" : "저장";
      toast(input.checked ? "관심 공고에 다시 저장했습니다." : "관심 공고에서 해제했습니다.");
      if (!input.checked) {
        card.remove();
        if (!list.querySelector(".saved-card")) list.innerHTML = emptyHtml();
      }
    } catch (error) {
      input.checked = !input.checked;
      if (label) label.textContent = input.checked ? "저장됨" : "저장";
      toast(error.message);
    } finally {
      input.disabled = false;
    }
  });

  fetch("/api/favorites", { credentials: "same-origin" })
    .then(async (res) => {
      const data = await res.json();
      if (res.status === 401) {
        list.innerHTML = `<div class="favorite-empty"><strong>로그인이 필요해요.</strong><p>저장한 정책을 확인하려면 로그인해 주세요.</p><a href="/login">로그인하기</a></div>`;
        return null;
      }
      if (!res.ok) throw new Error(data.detail || "목록을 불러오지 못했습니다.");
      return data.items || [];
    })
    .then((items) => {
      if (!items) return;
      renderItems(items);
    })
    .catch((error) => {
      list.innerHTML = `<div class="error">${esc(error.message)}</div>`;
    });
})();
