window.addEventListener("DOMContentLoaded", function () {
  // Check if Vue is available
  if (typeof Vue === "undefined") {
    console.error("Vue is not loaded. Please check your Vue.js script tag.");
    return;
  }

  const { createApp, ref } = Vue;

  // Create the Vue app
  const app = createApp({
    setup() {
      // Existing properties
      const password = ref("");
      const email = ref("");
      const message = ref("");
      const messageType = ref("");
      const isLoading = ref(false);
      
      // New property for password visibility
      const showPassword = ref(false);

      // Toast notification properties
      const showCopyToast = ref(false);
      const showPasteToast = ref(false);
      const showCutToast = ref(false);

      // New properties for 2FA modal
      const show2FAModal = ref(false);
      const verificationCode = ref("");
      const modalMessage = ref("");
      const modalMessageType = ref("");
      const isVerifying = ref(false);
      const codeInput = ref(null);

      // Get CSRF token from data attribute
      const appElement = document.getElementById("app");
      const csrfToken = ref(appElement ? appElement.dataset.csrfToken : "");

      // Method to get CSRF token
      const getCsrfToken = () => {
        return csrfToken.value;
      };

      // Method to toggle password visibility
      const togglePasswordVisibility = () => {
        showPassword.value = !showPassword.value;
      };

      // Method to show toast notification
      const showToast = (type, duration = 3000) => {
        // Reset all toasts first
        showCopyToast.value = false;
        showPasteToast.value = false;
        showCutToast.value = false;
        
        // Show the requested toast
        if (type === 'copy') {
          showCopyToast.value = true;
        } else if (type === 'paste') {
          showPasteToast.value = true;
        } else if (type === 'cut') {
          showCutToast.value = true;
        }
        
        // Auto-hide after duration
        setTimeout(() => {
          showCopyToast.value = false;
          showPasteToast.value = false;
          showCutToast.value = false;
        }, duration);
      };

      // Method to open 2FA modal
      const open2FAModal = () => {
        show2FAModal.value = true;
        // Focus on input when modal opens
        setTimeout(() => {
          if (codeInput.value) {
            codeInput.value.focus();
          }
        }, 100);
      };

      // Method to submit 2FA code
      const submit2FACode = async () => {
        isVerifying.value = true;
        modalMessage.value = "";
        modalMessageType.value = "";

        const formData = new FormData();
        formData.append("csrf_token", getCsrfToken());
        formData.append("verification_code", verificationCode.value);

        try {
          const res = await fetch("/login?action=verifyTwoFactor", {
            method: "POST",
            body: formData,
            credentials: "include",
            headers: {
              "X-XSRF-TOKEN": getCsrfToken(),
            },
          });

          const result = await res.json();

          if (result.success) {
            if (result.redirect) {
              // Marks this as a genuine login in this browser session, so
              // session_guard.php (loaded on every authenticated page)
              // knows not to force a logout on the next page load.
              sessionStorage.setItem("lspu_session_active", "1");
              window.location.href = "index.php?page=" + result.redirect;
            }
          } else {
            modalMessage.value = result.message || "Invalid verification code.";
            modalMessageType.value = "error";
          }
        } catch (err) {
          modalMessage.value = "Verification error. Please try again.";
          modalMessageType.value = "error";
        }

        isVerifying.value = false;
      };

      // Submit login method
      const submitLogin = async (e) => {
        e.preventDefault();
        isLoading.value = true;
        message.value = "";
        messageType.value = "";

        const formData = new FormData(e.target);

        try {
          const res = await fetch("/login?action=login", {
            method: "POST",
            body: formData,
            credentials: "include",
            headers: {
              "X-XSRF-TOKEN": getCsrfToken(),
            },
          });

          const result = await res.json();

          if (result.success) {
            if (result.requires_2fa) {
              // Show 2FA modal
              message.value =
                result.message || "Verification code sent to your email.";
              messageType.value = "info";
              open2FAModal();
            } else if (result.redirect) {
              sessionStorage.setItem("lspu_session_active", "1");
              window.location.href = result.redirect;
            }
          } else {
            message.value = result.message || "Login failed.";
            messageType.value = "error";
          }
        } catch (err) {
          message.value = "Login error. Please try again.";
          messageType.value = "error";
        }

        isLoading.value = false;
      };

      // Copy/paste/cut handlers with toast notifications
      const handleCopy = (e) => {
        e.preventDefault();
        showToast('copy');
      };

      const handlePaste = (e) => {
        e.preventDefault();
        showToast('paste');
      };

      const handleCut = (e) => {
        e.preventDefault();
        showToast('cut');
      };

      // Return all properties and methods
      return {
        password,
        email,
        message,
        messageType,
        isLoading,
        showPassword,
        showCopyToast,
        showPasteToast,
        showCutToast,
        show2FAModal,
        verificationCode,
        modalMessage,
        modalMessageType,
        isVerifying,
        codeInput,
        submitLogin,
        submit2FACode,
        open2FAModal,
        togglePasswordVisibility,
        handleCopy,
        handlePaste,
        handleCut,
      };
    },
  });

  // Mount the app
  app.mount("#app");
});