(function () {
  function ensureModal() {
    let overlay = document.getElementById("cf-modal-overlay");
    if (overlay) return overlay;

    overlay = document.createElement("div");
    overlay.id = "cf-modal-overlay";
    overlay.style.cssText =
      "position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:10000";

    const modal = document.createElement("div");
    modal.id = "cf-modal";
    modal.style.cssText =
      "background:#fff;min-width:320px;max-width:720px;width:90vw;max-height:80vh;overflow:auto;border-radius:8px;padding:16px";

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    function close() {
      overlay.style.display = "none";
      modal.innerHTML = "";
    }

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) close();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });

    overlay.addEventListener("click", (e) => {
      const el = e.target;
      if (el && el.matches && el.matches("[data-cf-close]")) close();
    });

    overlay._cfOpenWithHtml = (html) => {
      modal.innerHTML = html;
      overlay.style.display = "flex";
    };

    return overlay;
  }

  async function openContactFormModal() {
    const overlay = ensureModal();
    const res = await fetch("/contact_form", {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });

    const html = await res.text();
    overlay._cfOpenWithHtml(html);
  }

  // Intercept clicks on links/buttons that should open the modal
  document.addEventListener("click", (e) => {
    const link = e.target.closest("[data-contact-form]");
    if (!link) return;

    e.preventDefault();
    openContactFormModal();
  });

  // Optional: intercept *any* click to /contact_form
  document.addEventListener("click", (e) => {
    const a = e.target.closest('a[href="/contact_form"]');
    if (!a) return;

    e.preventDefault();
    openContactFormModal();
  });
})();
