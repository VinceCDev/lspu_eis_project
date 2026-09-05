window.addEventListener('DOMContentLoaded', function() {
    const { createApp, ref } = Vue;
    createApp({
        setup() {
            const email = ref('');
            const message = ref('');
            const messageType = ref('');
            const isLoading = ref(false);
            const submitForgot = async (e) => {
                e.preventDefault();
                isLoading.value = true;
                message.value = '';
                messageType.value = '';
                const formData = new FormData(e.target);
                try {
                    const res = await fetch('/forgot_password?action=sendResetLink', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });
                    // Try to get message from session or fallback
                    if (res.redirected) {
                        window.location.href = res.url;
                        return;
                    }
                    const text = await res.text();
                    if (text.includes('Too many reset requests')) {
                        message.value = 'Too many reset requests. Please try again in 15 minutes.';
                        messageType.value = 'error';
                    } else {
                        message.value = 'If that email address is registered, a password reset link has been sent.';
                        messageType.value = 'success';
                    }
                } catch (err) {
                    message.value = 'Error sending request. Please try again.';
                    messageType.value = 'error';
                }
                isLoading.value = false;
            };
            return { email, message, messageType, isLoading, submitForgot };
        }
    }).mount('body');
});