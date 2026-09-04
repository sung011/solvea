(function () {
  function initMenu() {
    const menuToggle = document.querySelector(".menu-toggle");
    const menuPanel = document.getElementById("menu-panel");
    const backdrop = document.getElementById("menu-backdrop");
    const closeBtn = document.getElementById("menu-panel-close");
    if (!menuToggle || !menuPanel) return;

    function setOpen(open) {
      menuPanel.classList.toggle("is-open", open);
      menuPanel.setAttribute("aria-hidden", String(!open));
      menuToggle.setAttribute("aria-expanded", String(open));
      menuToggle.setAttribute("aria-label", open ? "메뉴 닫기" : "메뉴 열기");
      document.body.classList.toggle("menu-open", open);
      backdrop?.classList.toggle("is-open", open);
      backdrop?.setAttribute("aria-hidden", String(!open));
    }

    menuToggle.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      setOpen(!menuPanel.classList.contains("is-open"));
    });

    closeBtn?.addEventListener("click", () => setOpen(false));
    backdrop?.addEventListener("click", () => setOpen(false));

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setOpen(false);
    });

    menuPanel.querySelectorAll(".menu-recommendation").forEach((button) => {
      button.addEventListener("click", () => {
        const query = (button.dataset.q || "").trim();
        if (!query) return;
        setOpen(false);
        location.href = "/results?q=" + encodeURIComponent(query);
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMenu);
  } else {
    initMenu();
  }
})();
