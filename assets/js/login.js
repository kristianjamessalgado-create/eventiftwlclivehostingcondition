document.addEventListener("DOMContentLoaded", () => {
    // ==============================
    // Toggle between login & register
    // ==============================
    const container = document.querySelector(".container");
    const registerBtn = document.querySelector(".register-btn");
    const loginBtn = document.querySelector(".login-btn");

    if (registerBtn) registerBtn.addEventListener("click", () => container.classList.add("active"));
    if (loginBtn) loginBtn.addEventListener("click", () => container.classList.remove("active"));

    // ========================================
    // Auto-show register if redirected w/ errors
    // ========================================
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("form") === "register" && container) {
        container.classList.add("active");
    }

    // =====================
    // Submit register form
    // =====================
    window.submitRegister = function () {
        const form = document.getElementById("registerForm");
        if (form) form.submit();
    };

    // =================================
    // Open register confirmation modal
    // =================================
    window.openRegisterModal = function () {
        const form = document.getElementById("registerForm");
        if (!form) return;

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const passwordInput = form.querySelector('input[name="password"]');
        const confirmInput  = form.querySelector('input[name="confirm_password"]');
        const errorText     = document.getElementById("passwordError");

        if (passwordInput && confirmInput && errorText && passwordInput.value !== confirmInput.value) {
            errorText.style.display = "block";
            confirmInput.focus();
            return;
        }

        if (errorText) errorText.style.display = "none";

        const modalEl = document.getElementById("registerConfirmModal");
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    };

    // =================================
    // Hide password error while typing
    // =================================
    const confirmInputField = document.querySelector('input[name="confirm_password"]');
    if (confirmInputField) {
        confirmInputField.addEventListener("input", () => {
            const errorText = document.getElementById("passwordError");
            if (errorText) errorText.style.display = "none";
        });
    }

    // =================================
    // Show / hide PASSWORD ONLY (eye icon)
    // =================================
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("registerPassword");

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", () => {
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                togglePassword.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                passwordInput.type = "password";
                togglePassword.classList.replace("fa-eye-slash", "fa-eye");
            }
        });
    }
});
