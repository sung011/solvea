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

const $ = (id) => document.getElementById(id);

function esc(text) {
  return String(text || "").replace(/[&<>"']/g, (ch) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[ch]));
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

function renderResults(data) {
  total = data.totalCount || 0;
  lastItems = data.items || [];
  lastParsed = data.parsed || null;
  const list = $("list");
  $("result-title").textContent = `${total.toLocaleString()}개의 공고 검색 결과`;
  $("result-sub").textContent = lastParsed?.summary
    ? `${lastParsed.summary} 기준으로 확인된 공고입니다.`
    : "입력하신 정보를 바탕으로 확인된 공고입니다.";
  $("prev").disabled = page <= 1;
  $("next").disabled = page * PAGE_SIZE >= total;

  if (!lastItems.length) {
    list.innerHTML = `<div class="empty">조건에 맞는 공고가 없습니다. 나이·거주·상태를 조금 바꿔 보세요.</div>`;
    return;
  }

  list.innerHTML = lastItems.map((item, index) => {
    const logo = logoMeta(item);
    const level = matchLevel(item, lastParsed);
    const chips = conditionChips(item, lastParsed);
    const quote = item.jobTarget || item.jobContent || "공고 근거를 확인하려면 카드를 열어 주세요.";
    return `
      <article class="result-card${index === 0 ? " is-open" : ""}" data-key="${esc(item.jobKey)}" data-kind="${esc(item.kind || "support")}">
        <button class="result-top" type="button">
          <span class="org-logo" style="background:${logo.color}">${esc(logo.letter)}</span>
          <span class="result-copy">
            <h2>${esc(item.jobTitle || "제목 없음")}</h2>
            <p>${esc(item.jobArea || KIND_LABEL[item.kind] || "운영기관 미상")}</p>
          </span>
          <span class="result-meta">
            ${badgeHtml(level)}
            <span class="chevron">${chevron(index === 0)}</span>
          </span>
        </button>
        <div class="result-body">
          <p class="cond-label">지원 대상 조건</p>
          <div class="cond-row">
            ${chips.map((chip) => `<span class="cond">${checkIcon()}${esc(chip)}</span>`).join("")}
          </div>
          <div class="quote">공고 근거 · ${esc(String(quote).replace(/<[^>]+>/g, " ").slice(0, 180))}</div>
          <div class="detail-extra"></div>
        </div>
      </article>
    `;
  }).join("");

  if (lastItems[0]) loadDetail(list.querySelector(".result-card"));
}

async function loadDetail(card) {
  if (!card || card.dataset.loaded === "1") return;
  const extra = card.querySelector(".detail-extra");
  extra.textContent = "상세 내용을 불러오는 중…";
  try {
    const res = await fetch(`/api/detail?kind=${encodeURIComponent(card.dataset.kind)}&jobKey=${encodeURIComponent(card.dataset.key)}`);
    const data = await res.json();
    const item = (data.items || [])[0];
    if (!item) {
      extra.textContent = "상세 내용을 찾지 못했습니다.";
      return;
    }
    const quote = card.querySelector(".quote");
    const basis = String(item.jobContent || item.jobTarget || "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
    if (basis) quote.textContent = `공고 근거 · ${basis.slice(0, 220)}`;
    const files = data.files || [];
    extra.innerHTML = [
      item.jobManager || item.jobWriter ? `<div>${esc(item.jobManager || item.jobWriter)} ${esc(item.jobManagerTel || "")}</div>` : "",
      item.jobLink ? `<p><a href="${esc(item.jobLink)}" target="_blank" rel="noopener">홈페이지 / 신청</a></p>` : "",
      files.length
        ? `<div class="files">${files.map((file) => `<a href="${esc(file.jobFileUrl || "#")}" target="_blank" rel="noopener">${esc(file.jobFileNm || "첨부파일")}</a>`).join("")}</div>`
        : "",
    ].join("");
    card.dataset.loaded = "1";
  } catch (err) {
    extra.textContent = `상세를 불러오지 못했습니다. ${err.message}`;
  }
}

async function loadResults() {
  const list = $("list");
  const q = currentQuery();
  if (!q) {
    $("result-title").textContent = "공고 검색 결과";
    list.innerHTML = `<div class="empty">검색어를 입력해 주세요.</div>`;
    return;
  }
  baseQuery = q;
  list.innerHTML = `<div class="loading">불러오는 중…</div>`;
  const params = new URLSearchParams({ q, startPage: String(page), pageSize: String(PAGE_SIZE) });
  const res = await fetch("/api/recommend?" + params.toString());
  renderResults(await res.json());
}

function bindHome() {
  // 홈 폼 submit 검증은 welcome.js 가 담당한다.
  document.querySelectorAll(".prompt-card").forEach((btn) => {
    btn.addEventListener("click", () => goSearch(btn.dataset.q));
  });
}

function bindResults() {
  $("ask").addEventListener("submit", (event) => {
    event.preventDefault();
    const extra = $("q").value.trim();
    if (!extra) return;
    const next = extra.includes(baseQuery) ? extra : `${baseQuery} ${extra}`.trim();
    page = 1;
    goSearch(next);
  });
  $("list").addEventListener("click", (event) => {
    const top = event.target.closest(".result-top");
    if (!top) return;
    const card = top.closest(".result-card");
    const open = card.classList.contains("is-open");
    document.querySelectorAll(".result-card.is-open").forEach((node) => {
      node.classList.remove("is-open");
      node.querySelector(".chevron").innerHTML = chevron(false);
    });
    if (!open) {
      card.classList.add("is-open");
      card.querySelector(".chevron").innerHTML = chevron(true);
      loadDetail(card);
    }
  });
  $("prev").addEventListener("click", () => {
    if (page > 1) { page -= 1; loadResults(); }
  });
  $("next").addEventListener("click", () => {
    page += 1;
    loadResults();
  });
  $("summary-btn").addEventListener("click", () => {
    $("summary-text").textContent = lastParsed?.summary
      ? `${lastParsed.summary} 조건으로 ${total.toLocaleString()}건을 찾았습니다. 자격 확정이 아니며 최종 심사는 운영기관이 합니다.`
      : "검색 결과가 없습니다.";
    $("summary-list").innerHTML = lastItems.slice(0, 5).map((item) => `<li>${esc(item.jobTitle)}</li>`).join("");
    $("summary").showModal();
  });
  $("summary-close").addEventListener("click", () => $("summary").close());
  loadResults().catch((err) => {
    $("list").innerHTML = `<div class="error">불러오기 실패: ${err.message}</div>`;
  });
}

if (document.body.classList.contains("page-home")) bindHome();
if (document.body.classList.contains("page-results")) bindResults();
