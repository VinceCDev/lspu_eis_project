@verbatim
<?php $csrfToken = $csrfToken ?? ''; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LSPU EIS</title>
    <link rel="icon" type="image/png" href="<?= asset('assets/images/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/vendor/tailwind/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/login.css') ?>">
    <style>
        [v-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-poppins" id="app" data-csrf-token="<?= htmlspecialchars($csrfToken) ?>" v-cloak>
    <header class="bg-gradient-to-r from-lspu-blue to-lspu-dark text-white shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-center gap-4 py-4 px-6 animate-slide-in">
            <img src="<?= asset('assets/images/logo.png') ?>" alt="LSPU Logo" class="h-20 w-auto" loading="lazy" width="80" height="80">
            <div class="text-center md:text-left">
                <h1 class="font-unifraktur text-2xl md:text-3xl leading-tight">Laguna State Polytechnic University</h1>
                <p class="font-semibold text-sm md:text-base">INTEGRITY • PROFESSIONALISM • INNOVATION</p>
            </div>
        </div>
    </header>
    <div class="bg-lspu-gold py-2 shadow-sm"></div>
    <div class="container mx-auto px-4 py-8 w-full max-w-[500px]">
        <div class="bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden animate-slide-in">
            <div class="flex items-center justify-center pt-5">
                <picture>
                  <source srcset="<?= asset('assets/images/alumni.png') ?>" type="image/png">
                  <img src="<?= asset('assets/images/alumni.png') ?>" alt="LSPU Logo" class="mr-0 w-[90px] h-auto" loading="lazy" width="90" height="90">
                </picture>
                <div class="border-b-2 border-lspu-blue">
                    <p class="text-[2.5rem] font-bold uppercase flex items-center m-0">
                        <span class="font-black text-gray-800">LSPU</span>
                        <span class="font-light text-lspu-blue font-sans">EIS</span>
                    </p>
                </div>
            </div>

            <div class="px-6 pb-6">
                <form @submit.prevent="submitLogin" class="space-y-4">
                <div v-show="message"
                :class="[messageType === 'error' ? 'bg-red-100 border-red-400 text-red-700' : messageType === 'info' ? 'bg-blue-100 border-blue-400 text-blue-700' : 'bg-green-100 border-green-400 text-green-700', 'border px-4 py-3 rounded relative w-full mx-auto mt-6 mb-4 flex items-center gap-2']"
                aria-live="polite" role="status" v-cloak>
                <span class="block">{{ message }}</span>
            </div>

                <div v-if="showCopyToast"
                    class="bg-yellow-100 border-yellow-400 border px-4 py-3 rounded relative w-full mx-auto mb-4 flex items-center gap-2"
                    aria-live="polite" role="alert" v-cloak>
                    <span class="block text-yellow-700">Copying password is not allowed for security reasons.</span>
                </div>

                <div v-if="showPasteToast"
                    class="bg-red-100 border-red-400 border px-4 py-3 rounded relative w-full mx-auto mb-4 flex items-center gap-2"
                    aria-live="polite" role="alert" v-cloak>
                    <span class="block text-red-700">Pasting into password field is restricted for security.</span>
                </div>

                <div v-if="showCutToast"
                    class="bg-orange-100 border-orange-400 border px-4 py-3 rounded relative w-full mx-auto mb-4 flex items-center gap-2"
                    aria-live="polite" role="alert" v-cloak>
                    <span class="block text-orange-700">Cutting password text is not permitted.</span>
                </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="relative">
                            <i class="bi bi-person absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="email" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-lspu-blue focus:border-lspu-blue transition"
                                   name="email" v-model="email" placeholder="Enter your email" required>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="relative">
                            <i class="bi bi-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-lspu-blue focus:border-lspu-blue transition"
                                v-model="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                @copy.prevent="handleCopy"
                                @paste.prevent="handlePaste"
                                @cut.prevent="handleCut">
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-gray-400 hover:text-lspu-blue transition"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                @click="togglePasswordVisibility">
                                <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <a href="forgot_password" class="text-sm text-lspu-blue hover:text-lspu-dark font-medium transition">Forgot Password?</a>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-lspu-blue hover:bg-lspu-dark text-white font-semibold rounded-lg shadow-md transition duration-300" :disabled="isLoading">
                        <span v-if="isLoading">Signing in...</span>
                        <span v-else>SIGN IN</span>
                    </button>
                </form>
                <div class="text-center mt-4 pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-sm">Don't have an account?
                        <a href="user_type" class="text-lspu-blue hover:text-lspu-dark font-medium transition">Register now</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div v-if="show2FAModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50" role="dialog" aria-modal="true">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="bi bi-shield-check text-lspu-blue"></i>
                    Two-Factor Authentication
                </h3>

                <p class="text-gray-600 mb-4">Please enter the 6-digit verification code sent to your email.</p>

                <form @submit.prevent="submit2FACode">
                    <div class="space-y-4">
                        <div
                            v-if="modalMessage"
                            :class="[modalMessageType === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-blue-100 border-blue-400 text-blue-700', 'border px-4 py-3 rounded relative flex items-center gap-2']"
                        >
                            <span>{{ modalMessage }}</span>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Verification Code</label>
                            <div class="relative">
                                <i class="bi bi-shield-check absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input
                                    type="text"
                                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-lspu-blue focus:border-lspu-blue transition"
                                    v-model="verificationCode"
                                    placeholder="Enter 6-digit code"
                                    maxlength="6"
                                    required
                                    autocomplete="one-time-code"
                                    ref="codeInput"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            @click="show2FAModal = false"
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="flex-1 px-4 py-2 bg-lspu-blue hover:bg-lspu-dark text-white font-semibold rounded-lg shadow-md transition duration-300"
                            :disabled="isVerifying"
                        >
                            <span v-if="isVerifying">Verifying...</span>
                            <span v-else>Verify</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="<?= asset('assets/vendor/vue/vue.global.prod.js') ?>" defer></script>
    <script src="<?= asset('assets/js/login.js') ?>"></script>
</body>
</html>
@endverbatim
