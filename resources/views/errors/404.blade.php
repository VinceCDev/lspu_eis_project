<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | LSPU EIS</title>
    <link rel="icon" type="image/png" href="<?= asset('assets/images/logo.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/tailwind/tailwind.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-blue-900 font-[Poppins]">
    <div class="bg-white/10 backdrop-blur-md border border-white/20 shadow-2xl rounded-3xl p-6 sm:p-8 md:p-10 mx-4 w-full max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg xl:max-w-xl 2xl:max-w-2xl text-center animate-fadeIn">
        <div class="relative inline-block mb-4 sm:mb-6">
            <img src="<?= asset('assets/images/logo.png') ?>"
                alt="LSPU Logo"
                class="mx-auto w-20 h-20 sm:w-24 sm:h-24 drop-shadow-lg object-contain">
            <div class="absolute inset-0 rounded-full bg-blue-500/20 blur-md -z-10"></div>
        </div>

        <div class="relative mb-6 sm:mb-8">
            <h1 class="text-7xl sm:text-8xl md:text-9xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-blue-600 animate-pulse">404</h1>
            <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-3/4 h-1 bg-gradient-to-r from-transparent via-blue-400 to-transparent rounded-full"></div>
        </div>

        <h2 class="text-2xl sm:text-3xl font-bold text-white mt-3 sm:mt-4">Lost in the Digital Space</h2>
        <p class="text-white/80 mt-3 sm:mt-4 mb-6 sm:mb-8 leading-relaxed text-sm sm:text-base">
            The page you're searching for seems to have drifted into the digital void. Let's get you back on track.
        </p>

        <a href="landing"
           class="relative inline-flex items-center px-6 py-3 sm:px-8 sm:py-4 bg-gradient-to-r from-blue-600 to-slate-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 group overflow-hidden text-sm sm:text-base">
            <span class="relative z-10 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5 mr-2 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Return to LSPU EIS
            </span>
            <span class="absolute inset-0 bg-gradient-to-r from-blue-500 to-slate-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-0"></span>
        </a>

        <div class="absolute -top-16 sm:-top-20 -left-16 sm:-left-20 w-32 h-32 sm:w-40 sm:h-40 rounded-full bg-blue-500/10 blur-xl"></div>
        <div class="absolute -bottom-8 sm:-bottom-10 -right-8 sm:-right-10 w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-blue-400/10 blur-xl"></div>
    </div>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-fadeIn {
            animation: fadeIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</body>
</html>
