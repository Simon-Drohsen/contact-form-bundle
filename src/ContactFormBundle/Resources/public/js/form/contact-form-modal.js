(function () {
  function ensureModal() {
    const overlay = document.createElement("div");
    overlay.id = "cf-modal-overlay";

    const modal = document.createElement("div");
    modal.id = "cf-modal";

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // remove modal on close
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) {
        overlay.remove();
      }
    });

    overlay._cfOpenWithHtml = (html) => {
      modal.innerHTML = html;
      overlay.style.display = "flex";
    };

    return overlay;
  }

  async function openContactFormModal() {
    const overlay = ensureModal();
    const locale = window.location.pathname.split("/")[1];

    const res = await fetch(`/${locale}/contact_form`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });

    const html = await res.text();
    overlay._cfOpenWithHtml(html);
  }

  document.addEventListener("click", (e) => {
    const a = e.target.closest('a[href="/contact_form"]');
    if (!a) return;

    e.preventDefault();
    openContactFormModal();
  });
})();
