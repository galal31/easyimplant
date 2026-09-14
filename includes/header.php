<header id="main-header" class="site-header fixed top-0 z-50 w-full">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between gap-4">
            <a href="#home" class="brand-lockup shrink-0" aria-label="Easy Implant home">
                <span class="brand-logo-frame">
                    <img src="images/logo.png?v=<?= filemtime(__DIR__ . '/../images/logo.png') ?>" alt="Easy Implant" class="brand-logo" width="1250" height="1250">
                </span>
            </a>

            <nav class="hidden items-center gap-6 lg:flex">
                <a href="#services" class="nav-link text-sm font-semibold text-white/80 transition" data-i18n="nav_services">Services</a>
                <a href="#workflow" class="nav-link text-sm font-semibold text-white/80 transition" data-i18n="nav_workflow">How It Works</a>
                <a href="#faq" class="nav-link text-sm font-semibold text-white/80 transition" data-i18n="nav_faq">FAQ</a>
                <a href="#contact" class="nav-link text-sm font-semibold text-white/80 transition" data-i18n="nav_contact">Contact</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <div class="language-switch flex items-center rounded-full border border-white/20 bg-white/10 p-1 backdrop-blur-md">
                    <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="en" id="btn-en" data-i18n="lang_en">EN</button>
                    <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="ar" id="btn-ar" data-i18n="lang_ar">AR</button>
                </div>

                <a href="login.php" class="header-login rounded-full px-3 py-2 text-sm font-bold text-white transition hover:bg-white/10" data-i18n="nav_login">Login</a>
                <a href="register.php" class="header-cta inline-flex items-center justify-center rounded-full bg-white px-5 py-2.5 text-sm font-bold text-brand-dark transition hover:bg-sky-50" data-i18n="nav_cta">Create Clinic Account</a>
            </div>

            <button id="menu-toggle" class="menu-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white backdrop-blur-md lg:hidden" aria-expanded="false">
                <i class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
                <span class="sr-only" data-i18n="menu_toggle">Toggle menu</span>
            </button>
        </div>

        <div id="mobile-menu" class="mobile-menu hidden pb-5 lg:hidden">
            <div class="rounded-2xl border border-white/10 bg-[#0b1d2c]/95 p-3 shadow-2xl backdrop-blur-xl">
                <div class="flex flex-col gap-1">
                    <a href="#services" class="mobile-nav-link rounded-xl px-3 py-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10" data-i18n="nav_services">Services</a>
                    <a href="#workflow" class="mobile-nav-link rounded-xl px-3 py-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10" data-i18n="nav_workflow">How It Works</a>
                    <a href="#faq" class="mobile-nav-link rounded-xl px-3 py-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10" data-i18n="nav_faq">FAQ</a>
                    <a href="#contact" class="mobile-nav-link rounded-xl px-3 py-3 text-sm font-semibold text-slate-100 transition hover:bg-white/10" data-i18n="nav_contact">Contact</a>
                </div>

                <div class="my-3 h-px bg-white/10"></div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center rounded-full border border-white/10 bg-white/5 p-1">
                        <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="en" data-i18n="lang_en">EN</button>
                        <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="ar" data-i18n="lang_ar">AR</button>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="login.php" class="rounded-full px-3 py-2 text-xs font-bold text-white" data-i18n="nav_login">Login</a>
                        <a href="register.php" class="rounded-full bg-white px-4 py-2 text-xs font-bold text-brand-dark" data-i18n="nav_cta">Create Clinic Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
