const PAGE_SIZE = 8;
const KIND_LABEL = {
  support: "전남지원",
  govjob: "공공일자리",
  hopebus: "희망버스",
  jobmatch: "잡매칭",
  meetday: "만남의 날",
  center: "상담센터",
  youth: "온통청년",
  gov24: "정부24",
  lcgv: "지자체복지",
  nwlf: "중앙복지",
};

let page = 1;
let total = 0;
let lastItems = [];
let lastParsed = null;
let baseQuery = "";
let backgroundSyncStarted = false;
let backgroundSyncQuery = "";
let favoriteKeys = new Set();

const $ = (id) => document.getElementById(id);

function setText(id, value) {
  const element = $(id);
  if (element) element.textContent = value;
  return element;
}

function esc(text) {
  return String(text || "").replace(/[&<>"']/g, (ch) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[ch]));
}

// 공공 API가 보내는 HTML 조각/HTML 엔티티를 화면용 일반 텍스트로 변환
function cleanText(value) {
  const box = document.createElement("div");
  box.innerHTML = String(value || "");
  // &lt;dd&gt;처럼 한 번 인코딩된 응답도 다시 태그로 해석해 제거
  box.innerHTML = box.textContent || box.innerText || "";
  return (box.textContent || box.innerText || "").replace(/\s+/g, " ").trim();
}

// 검색 응답을 막지 않고 최신 API 데이터를 백그라운드에서 갱신
function startBackgroundSync(query) {
  if (backgroundSyncStarted && backgroundSyncQuery === query) return;
  backgroundSyncStarted = true;
  backgroundSyncQuery = query;
  fetch("/api/crawl/run", {
    method: "POST",
    credentials: "same-origin",
  })
    .then(() => {
      // API 저장이 끝난 뒤 DB를 다시 조회해 최신 결과와 총개수를 반영
      if (currentQuery() !== query) return;
      const params = new URLSearchParams({
        q: query,
        startPage: String(page),
        pageSize: String(PAGE_SIZE),
      });
      return fetch("/api/recommend?" + params.toString());
    })
    .then((response) => (response ? response.json() : null))
    .then((data) => {
      if (data && currentQuery() === query) renderResults(data);
    })
    .catch(() => {
      // 백그라운드 갱신 실패는 이미 표시한 DB 결과에 영향을 주지 않음
    });
}

function currentQuery() {
  const input = $("q");
  if (input && input.value.trim()) return input.value.trim();
  return new URLSearchParams(location.search).get("q") || "";
}

function goSearch(query, replace = false) {
  const q = (query || "").trim();
  if (!q || q.length < 2) {
    if (typeof utils !== "undefined" && utils.showToast) {
      utils.showToast("두자리 이상 입력해주세요.", () => $("q")?.focus());
    }
    return false;
  }
  const url = "/results?q=" + encodeURIComponent(q);
  if (replace) location.replace(url);
  else location.href = url;
  return true;
}

function logoMeta(item) {
  const blob = `${item.jobArea || ""} ${item.jobTitle || ""} ${KIND_LABEL[item.kind] || ""}`;
  if (blob.includes("광주")) return { letter: "광", color: "#0f9d8a" };
  if (blob.includes("전남") || blob.includes("전라")) return { letter: "전", color: "#d4a017" };
  if (item.kind === "nwlf" || blob.includes("고용") || blob.includes("노동")) return { letter: "고", color: "#2563eb" };
  if (item.kind === "gov24") return { letter: "정", color: "#4f46e5" };
  if (item.kind === "youth") return { letter: "청", color: "#7c3aed" };
  return { letter: (item.jobTitle || "사").slice(0, 1), color: "#64748b" };
}

function matchLevel(item, parsed) {
  if (!parsed) return "partial";
  let hits = 0;
  let checks = 0;
  const blob = `${item.jobTitle || ""} ${item.jobTarget || ""} ${item.jobContent || ""} ${item.jobArea || ""}`;
  if (parsed.ageMin != null) {
    checks += 1;
    const ageText = blob.match(/(\d{1,2})\s*[~\-～]\s*(\d{1,2})/);
    if (item.ageMin != null && item.ageMax != null) {
      if (!(item.ageMax < parsed.ageMin || item.ageMin > (parsed.ageMax ?? parsed.ageMin))) hits += 1;
    } else if (ageText) {
      const lo = Number(ageText[1]);
      const hi = Number(ageText[2]);
      if (!(hi < parsed.ageMin || lo > (parsed.ageMax ?? parsed.ageMin))) hits += 1;
    } else if (blob.includes("청년") && parsed.ageMin <= 39) {
      hits += 1;
    }
  }
  if (parsed.area) {
    checks += 1;
    if ((item.jobArea || "").includes(parsed.area) || blob.includes(parsed.area)) hits += 1;
  }
  for (const topic of parsed.topics || []) {
    checks += 1;
    if (blob.includes(topic) || (item.jobDepartLabel || "").includes(topic)) hits += 1;
  }
  if (!checks) return "match";
  const ratio = hits / checks;
  if (ratio >= 0.75) return "match";
  if (ratio >= 0.35) return "partial";
  return "none";
}

function badgeHtml(level) {
  if (level === "match") return `<span class="badge badge-match">일치</span>`;
  if (level === "none") return `<span class="badge badge-none">해당 없음</span>`;
  return `<span class="badge badge-partial">부분 일치</span>`;
}

function conditionChips(item, parsed) {
  const chips = [];
  if (item.ageMin != null && item.ageMax != null) chips.push(`나이 만 ${item.ageMin}~${item.ageMax}세`);
  else if (parsed?.ageMin != null) chips.push(`나이 만 ${parsed.ageMin}~${parsed.ageMax ?? parsed.ageMin}세`);
  if (item.jobArea) chips.push(`거주지 ${item.jobArea}`);
  if (item.jobCategoryLabel) chips.push(item.jobCategoryLabel);
  if (item.jobDepartLabel) chips.push(item.jobDepartLabel);
  if (item.jobApplyLabel) chips.push(item.jobApplyLabel);
  const target = (item.jobTarget || "").split(/[·|,/]/).map((part) => part.trim()).filter((part) => part.length >= 2 && part.length <= 18);
  target.slice(0, 3).forEach((part) => {
    if (!chips.some((chip) => chip.includes(part))) chips.push(part);
  });
  return chips.slice(0, 6);
}

function checkIcon() {
  return `<svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M5 12.5 10 17.5 19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

function chevron(up) {
  return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="${up ? "M6 14.5 12 8.5 18 14.5" : "M6 9.5 12 15.5 18 9.5"}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

function statusTags(item, parsed) {
  const tags = [];
  const blob = `${item.jobTitle || ""} ${item.jobTarget || ""} ${item.jobContent || ""} ${item.jobArea || ""}`;
  let ageOk = false;
  let areaOk = false;

  if (parsed?.ageMin != null) {
    const ageText = blob.match(/(\d{1,2})\s*[~\-～]\s*(\d{1,2})/);
    if (item.ageMin != null && item.ageMax != null) {
      ageOk = !(item.ageMax < parsed.ageMin || item.ageMin > (parsed.ageMax ?? parsed.ageMin));
    } else if (ageText) {
      const lo = Number(ageText[1]);
      const hi = Number(ageText[2]);
      ageOk = !(hi < parsed.ageMin || lo > (parsed.ageMax ?? parsed.ageMin));
    } else if (blob.includes("청년") && parsed.ageMin <= 39) {
      ageOk = true;
    }
  }
  if (parsed?.area) {
    areaOk = (item.jobArea || "").includes(parsed.area) || blob.includes(parsed.area);
  }

  if (ageOk && areaOk) tags.push({ tone: "ok", text: "충족 나이 · 지역" });
  else if (ageOk) tags.push({ tone: "ok", text: "충족 나이" });
  else if (areaOk) tags.push({ tone: "ok", text: "충족 지역" });

  if (!parsed?.income && /소득|연소득|가구소득/.test(blob)) {
    tags.push({ tone: "warn", text: "소득 모름" });
  }
  if (!parsed?.student && /재학|대학생|학생/.test(blob)) {
    tags.push({ tone: "warn", text: "재학 여부 미입력" });
  }
  if (parsed?.employed === false && /미취업|구직|실업/.test(blob) === false && /재직|취업자|근로/.test(blob)) {
    tags.push({ tone: "bad", text: "불충족 미취업 조건" });
  } else if (parsed?.employed === true && /미취업|구직자/.test(blob) && !/재직|근로자/.test(blob)) {
    tags.push({ tone: "bad", text: "불충족 미취업 조건" });
  }

  if (!tags.length) {
    const level = matchLevel(item, parsed);
    if (level === "match") tags.push({ tone: "ok", text: "충족 가능성 높음" });
    else if (level === "none") tags.push({ tone: "bad", text: "조건 확인 필요" });
    else tags.push({ tone: "warn", text: "추가 정보 필요" });
  }
  return tags;
}

function needsVerify(tags) {
  return tags.some((tag) => tag.tone === "warn" || tag.tone === "bad");
}

function renderResults(data) {
  total = data.totalCount || 0;
  lastItems = data.items || [];
  lastParsed = data.parsed || null;
  const list = $("list");
  if (!list) return;

  const query = currentQuery();
  const topic = (lastParsed?.topics && lastParsed.topics[0])
    ? `${lastParsed.topics[0]} 관련 지원`
    : (query.length > 24 ? `${query.slice(0, 24)}…` : query) || "정책 검색 결과";
  setText("result-title", topic);
  setText("chat-user-text", query);
  setText("result-sub", lastParsed?.summary
    ? `${lastParsed.summary} 기준으로 공고를 비교했어요. 모르는 항목은 확인 방법을 눌러 주세요.`
    : "입력하신 정보를 바탕으로 공고를 비교했어요. 모르는 항목은 확인 방법을 눌러 주세요.");

  const prev = $("prev");
  const next = $("next");
  if (prev) prev.disabled = page <= 1;
  if (next) next.disabled = page * PAGE_SIZE >= total;

  if (!lastItems.length) {
    list.innerHTML = `<div class="empty">조건에 맞는 공고가 없습니다. 나이·거주·상태를 조금 바꿔 보세요.</div>`;
    return;
  }

  list.innerHTML = lastItems.map((item) => {
    const tags = statusTags(item, lastParsed);
    const verify = needsVerify(tags);
    const desc = cleanText(item.jobContent || item.jobTarget || "지원 조건과 혜택을 확인해 보세요.").slice(0, 90);
    const saved = favoriteKeys.has(`${item.kind || "support"}:${item.jobKey}`);
    return `
      <article class="result-card" data-key="${esc(item.jobKey)}" data-kind="${esc(item.kind || "support")}">
        <div class="result-main">
          <div class="result-copy">
            <h2>${esc(item.jobTitle || "제목 없음")}</h2>
            <p class="result-description">${esc(desc)}</p>
            <div class="status-row">
              ${tags.map((tag) => `<span class="status-tag is-${tag.tone}">${esc(tag.text)}</span>`).join("")}
            </div>
          </div>
          <div class="result-side">
            <label class="favorite-toggle${saved ? " is-saved" : ""}">
              <input class="favorite-check" type="checkbox" ${saved ? "checked" : ""} aria-label="관심 공고로 저장">
              <span class="favorite-box" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6.5 4.5h11v16l-5.5-3.5-5.5 3.5v-16Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
              </span>
              <span class="favorite-label">${saved ? "저장됨" : "저장"}</span>
            </label>
            <div class="result-actions">
              <a class="source-link" href="${esc(item.jobLink || "#")}" target="_blank" rel="noopener">공고 원문 ↗</a>
              <button class="condition-btn" type="button" data-dialog="condition">조건 보기</button>
              <button class="verify-btn${verify ? " is-emphasis" : ""}" type="button" data-dialog="verify">확인 방법</button>
            </div>
          </div>
        </div>
      </article>
    `;
  }).join("");
}

function compareRows(item, parsed) {
  const blob = `${item.jobTitle || ""} ${item.jobTarget || ""} ${item.jobContent || ""}`;
  const rows = [];
  const ageCriteria = item.ageMin != null && item.ageMax != null
    ? `만 ${item.ageMin}~${item.ageMax}세`
    : (blob.match(/만?\s*(\d{1,2})\s*[~\-～]\s*(\d{1,2})\s*세/) ? `만 ${RegExp.$1}~${RegExp.$2}세` : "청년 연령");
  const myAge = parsed?.ageMin != null ? `${parsed.ageMin}세` : "미입력";
  let ageJudge = { tone: "warn", label: "확인 전" };
  if (parsed?.ageMin != null) {
    const ok = item.ageMin != null && item.ageMax != null
      ? !(item.ageMax < parsed.ageMin || item.ageMin > (parsed.ageMax ?? parsed.ageMin))
      : /청년/.test(blob) && parsed.ageMin <= 39;
    ageJudge = ok ? { tone: "ok", label: "충족" } : { tone: "bad", label: "불충족" };
  }
  rows.push({ item: "나이", criteria: ageCriteria, mine: myAge, judge: ageJudge });

  const areaCriteria = item.jobArea || "공고 지역 확인";
  const myArea = parsed?.area || "미입력";
  let areaJudge = { tone: "warn", label: "확인 전" };
  if (parsed?.area) {
    const ok = (item.jobArea || "").includes(parsed.area) || blob.includes(parsed.area);
    areaJudge = ok ? { tone: "ok", label: "충족" } : { tone: "bad", label: "불충족" };
  }
  rows.push({ item: "거주지역", criteria: areaCriteria, mine: myArea, judge: areaJudge });

  if (/소득|연소득|가구소득/.test(blob)) {
    rows.push({
      item: "본인 소득",
      criteria: "공고 소득 기준",
      mine: parsed?.income || "모름",
      judge: { tone: "warn", label: "확인 전" },
    });
  }
  return rows;
}

function showDialogToast(message) {
  const sheet = document.querySelector("#policy-dialog .policy-sheet");
  if (!sheet) {
    if (typeof utils !== "undefined" && utils.showToast) utils.showToast(message);
    else alert(message);
    return;
  }
  let toast = sheet.querySelector(".dialog-toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "dialog-toast";
    toast.setAttribute("role", "status");
    sheet.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add("is-visible");
  clearTimeout(showDialogToast._timer);
  showDialogToast._timer = setTimeout(() => {
    toast.classList.remove("is-visible");
  }, 1600);
}

function openPolicyDialog(card, type) {
  const item = lastItems.find((candidate) =>
    String(candidate.jobKey) === card.dataset.key &&
    String(candidate.kind || "support") === card.dataset.kind
  ) || {};
  const title = cleanText(item.jobTitle || "정책");
  const dialog = $("policy-dialog");
  if (!dialog) return;
  const content = $("policy-dialog-content");
  const link = $("policy-dialog-link");
  const action = $("policy-dialog-action");

  setText("dialog-kicker", `${title} · 예시`);
  if (type === "verify") {
    setText("policy-dialog-title", "본인 소득 확인 방법");
    setText("policy-dialog-description", "");
    if (content) {
      content.innerHTML = `
        <div class="verify-meta">
          <div><span>확인 대상</span><strong>본인</strong></div>
          <div><span>기준 연도</span><strong>2025년</strong></div>
        </div>
        <ol class="verify-steps">
          <li>정부24에 로그인해요.</li>
          <li>소득금액증명 또는 건강보험 납부확인서를 발급해요.</li>
          <li>확인한 금액을 채팅에 입력하면 결과에 반영돼요.</li>
        </ol>
        <div class="dialog-tip">확인한 내용을 채팅에 입력하면 결과에 반영돼요</div>
      `;
    }
    setText("policy-dialog-note", "");
    if (link) {
      link.href = "#";
      link.textContent = "확인 방법 복사";
      link.style.display = "inline-flex";
      link.removeAttribute("target");
      link.rel = "";
      link.onclick = async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const text = "1) 정부24 로그인\n2) 소득금액증명/건강보험 납부확인서 발급\n3) 확인한 금액을 채팅에 입력";
        try {
          if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
          } else {
            const area = document.createElement("textarea");
            area.value = text;
            area.setAttribute("readonly", "");
            area.style.position = "fixed";
            area.style.left = "-9999px";
            document.body.appendChild(area);
            area.select();
            document.execCommand("copy");
            area.remove();
          }
          showDialogToast("복사되었습니다");
        } catch (err) {
          showDialogToast("복사에 실패했습니다.");
        }
      };
    }
    if (action) {
      action.textContent = "정부24에서 확인 ↗";
      action.onclick = () => { window.open("https://www.gov.kr/", "_blank", "noopener"); };
    }
  } else {
    setText("policy-dialog-title", "내 조건과 비교");
    setText("policy-dialog-description", "입력한 정보로 확인한 결과예요.");
    const rows = compareRows(item, lastParsed);
    if (content) {
      content.innerHTML = `
        <div class="compare-table">
          <div class="compare-head"><span>항목</span><span>기준 예시</span><span>내 정보</span><span>판정</span></div>
          ${rows.map((row) => `
            <div class="compare-row">
              <span>${esc(row.item)}</span>
              <span>${esc(row.criteria)}</span>
              <span>${esc(row.mine)}</span>
              <span class="judge is-${row.judge.tone}">${row.judge.tone === "ok" ? "✓ " : row.judge.tone === "warn" ? "● " : "✕ "}${esc(row.judge.label)}</span>
            </div>
          `).join("")}
        </div>
      `;
    }
    setText("policy-dialog-note", rows.some((row) => row.item.includes("소득"))
      ? "소득은 금액을 확인한 뒤 판정에 반영돼요."
      : "화면 설명은 참고용입니다. 정확한 기준은 공고 원문을 확인해 주세요.");
    if (link) {
      link.href = item.jobLink || "#";
      link.textContent = "공고 원문 보기 ↗";
      link.style.display = item.jobLink ? "inline-flex" : "none";
      link.target = "_blank";
      link.rel = "noopener";
      link.onclick = null;
    }
    if (action) {
      action.textContent = "소득 확인 방법";
      action.onclick = () => openPolicyDialog(card, "verify");
    }
  }
  if (typeof dialog.showModal === "function") dialog.showModal();
}

async function loadResults() {
  const list = $("list");
  const q = currentQuery();
  if (!q) {
    setText("result-title", "공고 검색 결과");
    if (list) list.innerHTML = `<div class="empty">검색어를 입력해 주세요.</div>`;
    return;
  }
  baseQuery = q;
  list.innerHTML = `<div class="loading">불러오는 중…</div>`;
  const params = new URLSearchParams({ q, startPage: String(page), pageSize: String(PAGE_SIZE) });
  const [res] = await Promise.all([
    fetch("/api/recommend?" + params.toString()),
    loadFavorites(),
  ]);
  renderResults(await res.json());
  startBackgroundSync(q);
}

async function loadFavorites() {
  try {
    const res = await fetch("/api/favorites", { credentials: "same-origin" });
    if (!res.ok) return;
    const data = await res.json();
    favoriteKeys = new Set((data.items || []).map((item) => `${item.kind}:${item.jobKey}`));
  } catch (err) {
    favoriteKeys = new Set();
  }
}

async function toggleFavorite(card, checked) {
  const item = lastItems.find((candidate) =>
    String(candidate.jobKey) === card.dataset.key &&
    String(candidate.kind || "support") === card.dataset.kind
  ) || {};
  const payload = {
    kind: card.dataset.kind,
    jobKey: card.dataset.key,
    jobTitle: item.jobTitle || "",
    jobArea: item.jobArea || "",
    jobLink: item.jobLink || "",
  };
  const res = await fetch("/api/favorites", {
    method: checked ? "POST" : "DELETE",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(apiError(data, "로그인이 필요합니다."));
  favoriteKeys[checked ? "add" : "delete"](`${card.dataset.kind}:${card.dataset.key}`);
}

function bindHome() {
  // 홈 폼 submit 검증은 welcome.js 가 담당한다.
  document.querySelectorAll(".prompt-card").forEach((btn) => {
    btn.addEventListener("click", () => goSearch(btn.dataset.q));
  });
}

function bindResults() {
  const ask = $("ask");
  const list = $("list");
  if (!ask || !list) return;
  ask.addEventListener("submit", (event) => {
    event.preventDefault();
    const extra = $("q").value.trim();
    if (!extra) return;
    const next = extra.includes(baseQuery) ? extra : `${baseQuery} ${extra}`.trim();
    page = 1;
    goSearch(next);
  });
  const qInput = $("q");
  if (qInput?.tagName === "TEXTAREA") {
    qInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        ask.requestSubmit();
      }
    });
  }
  list.addEventListener("click", (event) => {
    if (event.target.closest(".favorite-toggle")) return;
    const dialogButton = event.target.closest("[data-dialog]");
    if (dialogButton) {
      openPolicyDialog(dialogButton.closest(".result-card"), dialogButton.dataset.dialog);
    }
  });
  list.addEventListener("change", async (event) => {
    const input = event.target.closest(".favorite-check");
    if (!input) return;
    const card = input.closest(".result-card");
    const toggle = input.closest(".favorite-toggle");
    const label = toggle?.querySelector(".favorite-label");
    input.disabled = true;
    try {
      await toggleFavorite(card, input.checked);
      if (label) label.textContent = input.checked ? "저장됨" : "저장";
      toggle?.classList.toggle("is-saved", input.checked);
      if (typeof utils !== "undefined" && utils.showToast) {
        utils.showToast(input.checked ? "관심 공고에 저장했습니다." : "관심 공고에서 해제했습니다.");
      }
    } catch (err) {
      input.checked = !input.checked;
      if (label) label.textContent = input.checked ? "저장됨" : "저장";
      toggle?.classList.toggle("is-saved", input.checked);
      if (typeof utils !== "undefined" && utils.showToast) {
        utils.showToast(err.message);
      } else {
        alert(err.message);
      }
    } finally {
      input.disabled = false;
    }
  });
  $("prev")?.addEventListener("click", () => {
    if (page > 1) { page -= 1; loadResults(); }
  });
  $("next")?.addEventListener("click", () => {
    page += 1;
    loadResults();
  });
  document.querySelectorAll("[data-close-dialog]").forEach((button) => {
    button.addEventListener("click", () => $("policy-dialog").close());
  });
  loadResults().catch((err) => {
    if (list) list.innerHTML = `<div class="error">불러오기 실패: ${err.message}</div>`;
  });
}

if (document.body.classList.contains("page-home")) bindHome();
if (document.body.classList.contains("page-results")) bindResults();
