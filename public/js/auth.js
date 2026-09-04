function apiError(data, fallback) {
  const detail = data && data.detail;
  if (typeof detail === "string") return detail;
  if (Array.isArray(detail) && detail[0] && detail[0].msg) return detail[0].msg;
  return fallback;
}

function bindSignup() {
  const form = document.getElementById("signup");
  const msg = document.getElementById("msg");
  const btn = form.querySelector("button");
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    msg.className = "auth-msg";
    msg.textContent = "";
    const data = new FormData(form);
    const password = String(data.get("password") || "");
    const password2 = String(data.get("password2") || "");
    if (password !== password2) {
      msg.classList.add("is-error");
      msg.textContent = "비밀번호가 서로 다릅니다.";
      return;
    }
    const ageRaw = String(data.get("age") || "").trim();
    const body = {
      user_id: String(data.get("user_id") || "").trim(),
      password,
      name: String(data.get("name") || "").trim() || null,
      age: ageRaw ? Number(ageRaw) : null,
      address: String(data.get("address") || "").trim() || null,
    };
    btn.disabled = true;
    try {
      const res = await fetch("/api/auth/signup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok) {
        msg.classList.add("is-error");
        msg.textContent = apiError(json, "회원가입에 실패했습니다.");
        return;
      }
      msg.classList.add("is-ok");
      msg.textContent = `${json.user_id} 님, 가입되었습니다. 로그인 화면으로 이동합니다.`;
      setTimeout(() => { location.href = "/login"; }, 900);
    } catch (err) {
      msg.classList.add("is-error");
      msg.textContent = "서버에 연결하지 못했습니다. 잠시 후 다시 시도해 주세요.";
    } finally {
      btn.disabled = false;
    }
  });
}

function bindLogin() {
  const form = document.getElementById("login");
  const msg = document.getElementById("msg");
  const btn = form.querySelector("button");
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    msg.className = "auth-msg";
    msg.textContent = "";
    const data = new FormData(form);
    btn.disabled = true;
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          user_id: String(data.get("user_id") || "").trim(),
          password: String(data.get("password") || ""),
        }),
      });
      const json = await res.json();
      if (!res.ok) {
        msg.classList.add("is-error");
        msg.textContent = apiError(json, "로그인에 실패했습니다.");
        return;
      }
      msg.classList.add("is-ok");
      msg.textContent = "로그인되었습니다. 검색 화면으로 이동합니다.";
      setTimeout(() => { location.href = "/"; }, 700);
    } catch (err) {
      msg.classList.add("is-error");
      msg.textContent = "서버에 연결하지 못했습니다. 잠시 후 다시 시도해 주세요.";
    } finally {
      btn.disabled = false;
    }
  });
}

if (document.getElementById("signup")) bindSignup();
if (document.getElementById("login")) bindLogin();
