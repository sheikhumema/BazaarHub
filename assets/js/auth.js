const authShell = document.querySelector("[data-auth-shell]");
const tabs = document.querySelectorAll(".auth-tab");
const panels = document.querySelectorAll("[data-panel]");
const passwordToggles = document.querySelectorAll("[data-password-toggle]");
const forms = document.querySelectorAll("[data-mock-form]");
const toastHost = document.querySelector(".toast-host");

function setActiveTab(mode) {
    if (authShell) {
        authShell.dataset.mode = mode;
    }

    tabs.forEach(tab => {
        const active = tab.dataset.tabTarget === mode;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", active ? "true" : "false");
    });

    panels.forEach(panel => {
        const active = panel.dataset.panel === mode;
        panel.classList.toggle("is-active", active);
        panel.hidden = !active;
    });
}

tabs.forEach(tab => {
    tab.addEventListener("click", () => {
        setActiveTab(tab.dataset.tabTarget);
    });
});

setActiveTab(authShell?.dataset.mode || "signin");

/* ---------------- TOAST ---------------- */
function showToast(title, text) {
    if (!toastHost) return;

    const toast = document.createElement("div");
    toast.className = "toast";
    toast.innerHTML = `
        <p class="toast__title">${title}</p>
        <p class="toast__text">${text}</p>
    `;

    toastHost.appendChild(toast);

    window.setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transform = "translateY(10px)";
        toast.style.transition = "opacity 180ms ease, transform 180ms ease";

        window.setTimeout(() => toast.remove(), 200);
    }, 2600);
}

/* ---------------- TABS ---------------- */


/* ---------------- PASSWORD TOGGLE ---------------- */
passwordToggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
        const input = toggle.parentElement.querySelector("input");
        const isVisible = input.type === "text";

        input.type = isVisible ? "password" : "text";
        toggle.classList.toggle("is-visible", !isVisible);
        toggle.setAttribute(
            "aria-label",
            isVisible ? "Show password" : "Hide password"
        );
    });
});

forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
        event.preventDefault();

        if (!form.reportValidity()) return;

        const button = form.querySelector(".submit-button");
        const original = button.textContent;
        const loading = button.dataset.loadingText || original;

        if (form.dataset.mockForm === "signup") {
            const password = form.querySelector('input[name="password"]')?.value ?? "";
            const confirm = form.querySelector('input[name="confirm_password"]')?.value ?? "";

            if (password !== confirm) {
                showToast("Passwords do not match", "Please make sure both passwords are the same.");
                return;
            }
        }

        button.classList.add("is-loading");
        button.innerHTML = `<span class="submit-button__inner">${loading}</span>`;

        window.setTimeout(() => {
            button.classList.remove("is-loading");
            button.textContent = original;

            if (form.dataset.mockForm === "signin") {
                showToast("Signed in", "Welcome back to BazaarHub.");
            } else {
                showToast("Account created", "Your BazaarHub account is ready.");
                setActiveTab("signin");
            }
        }, 1000);
    });
});

/* ---------------- INIT ---------------- */
setActiveTab(authShell?.dataset.mode || "signin", 0);
