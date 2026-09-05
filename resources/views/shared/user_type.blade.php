<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | LSPU EIS</title>
    <link rel="icon" type="image/png" href="<?= asset('assets/images/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/vendor/tailwind/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <style>
        [v-cloak] {
            display: none !important;
        }
        .option-card {
            transition: all 0.3s ease;
        }
        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .option-card.selected {
            border-color: #00A0E9;
            background-color: #f0f9ff;
        }
    </style>
</head>
<body class="bg-gray-50 font-poppins" id="app">
    <!-- Header Section -->
    <header class="bg-gradient-to-r from-lspu-blue to-lspu-dark text-white shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-center gap-4 py-4 px-6 animate-slide-in">
            <img src="<?= asset('assets/images/logo.png') ?>" alt="LSPU Logo" class="h-20 w-auto" loading="lazy" width="80" height="80">
            <div class="text-center md:text-left">
                <h1 class="font-unifraktur text-2xl md:text-3xl leading-tight">Laguna State Polytechnic University</h1>
                <p class="font-semibold text-sm md:text-base">INTEGRITY • PROFESSIONALISM • INNOVATION</p>
            </div>
        </div>
    </header>
    <!-- Menu Bar -->
    <div class="bg-lspu-gold py-2 shadow-sm"></div>
    <!-- Main Content - Facebook-style container width -->
    <div class="container mx-auto px-4 py-8 w-full max-w-4xl">
        <!-- White Container Box -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden animate-slide-in">
            <!-- LSPU EIS Header -->
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
            
            <!-- Signup Options -->
            <div class="px-6 pb-6">
                <h2 class="text-2xl font-bold text-center text-gray-800 mt-6 mb-8">Create Your Account</h2>
                <p class="text-center text-gray-600 mb-8">Select your account type to get started</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Alumni Option -->
                    <div 
                        :class="['option-card border-2 rounded-xl p-6 text-center cursor-pointer', selectedOption === 'alumni' ? 'selected' : 'border-gray-200']"
                        @click="selectOption('alumni')"
                    >
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                            <i class="bi bi-mortarboard-fill text-3xl text-lspu-blue"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Alumni</h3>
                        <p class="text-gray-600 text-sm">I am a graduate of Laguna State Polytechnic University</p>
                        <div class="mt-4">
                            <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <i class="bi bi-check-circle-fill mr-1"></i> Recommended for graduates
                            </span>
                        </div>
                    </div>
                    
                    <!-- Employer Option -->
                    <div 
                        :class="['option-card border-2 rounded-xl p-6 text-center cursor-pointer', selectedOption === 'employer' ? 'selected' : 'border-gray-200']"
                        @click="selectOption('employer')"
                    >
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="bi bi-briefcase-fill text-3xl text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Employer</h3>
                        <p class="text-gray-600 text-sm">I represent a company looking to hire LSPU graduates</p>
                        <div class="mt-4">
                            <span class="inline-flex items-center bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <i class="bi bi-building mr-1"></i> For companies & organizations
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Continue Button -->
                <div class="text-center mt-8">
                    <button 
                        @click="continueToSignup" 
                        :disabled="!selectedOption"
                        :class="['w-full md:w-1/2 px-4 py-3 rounded-lg font-semibold transition duration-300', selectedOption ? 'bg-lspu-blue hover:bg-lspu-dark text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed']"
                    >
                        Continue to Registration
                    </button>
                </div>
                
                <!-- Login Link -->
                <div class="text-center mt-6 pt-6 border-t border-gray-200">
                    <p class="text-gray-600 text-sm">Already have an account? 
                        <a href="login" class="text-lspu-blue hover:text-lspu-dark font-medium transition">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/vendor/vue/vue.global.prod.js') ?>"></script>
    <script>
        const { createApp, ref } = Vue;
        
        createApp({
            setup() {
                const selectedOption = ref(null);
                
                const selectOption = (option) => {
                    selectedOption.value = option;
                };
                
                const continueToSignup = () => {
                    if (selectedOption.value === 'alumni') {
                        window.location.href = 'signup';
                    } else if (selectedOption.value === 'employer') {
                        window.location.href = 'employer_signup';
                    }
                };
                
                return {
                    selectedOption,
                    selectOption,
                    continueToSignup
                };
            }
        }).mount('#app');
    </script>
</body>
</html>