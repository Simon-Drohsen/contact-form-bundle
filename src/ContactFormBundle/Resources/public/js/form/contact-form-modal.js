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
    let locale = `/${window.location.pathname.split("/")[1]}`;
    if (locale === "/") locale = "";
    const url = `${window.location.origin}${locale}/contact_form`;

    const res = await fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });

    const html = await res.text();
    overlay._cfOpenWithHtml(html);
    const inputs = overlay.querySelectorAll("input");
    const isValidEmail = (value) =>
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());

    inputs.forEach((input) => {
      input.addEventListener("blur", (e) => {
        const input = e.currentTarget;
        const value = String(input.value).trim();

        let message = "";
        if (!value) {
          message = "Please fill out this field.";
        }

        if (input.id === "contact_form_email") {
          if (!isValidEmail(value)) {
            message = "Please enter a valid email address.";
          }
        }

        input.setCustomValidity(message);
        input.classList.toggle("is-invalid", Boolean(message));

        input.reportValidity();
      });
    });
  }

  document.addEventListener("click", (e) => {
    const a = e.target.closest('a[href*="contact_form"]');
    if (!a) return;

    const href = a.getAttribute("href");
    if (!href) return;

    let pathname;
    try {
      pathname = new URL(href, window.location.origin).pathname;
    } catch {
      return;
    }

    if (!/^\/(?:[a-z]{2}\/)?contact_form\/?$/.test(pathname)) return;

    e.preventDefault();
    openContactFormModal();
  });
})();
