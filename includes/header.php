<header id="main-header" class="site-header fixed top-0 z-50 w-full">
    <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
        <div class="header-row flex h-20 items-center justify-between gap-4">
            <a href="#home" class="brand-lockup shrink-0" aria-label="Easy Implant home" data-i18n-aria-label="home_label">
                <span class="brand-logo-frame">
                    <img src="images/logo.png?v=<?= filemtime(__DIR__ . '/../images/logo.png') ?>" alt="Easy Implant" class="brand-logo" width="1250" height="1250">
                </span>
            </a>

            <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary navigation" data-i18n-aria-label="primary_navigation">
                <a href="#services" class="nav-link text-sm font-semibold transition" data-i18n="nav_services">Services</a>
                <a href="#workflow" class="nav-link text-sm font-semibold transition" data-i18n="nav_workflow">How It Works</a>
                <a href="#faq" class="nav-link text-sm font-semibold transition" data-i18n="nav_faq">FAQ</a>
                <a href="#contact" class="nav-link text-sm font-semibold transition" data-i18n="nav_contact">Contact</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <div class="language-switch flex items-center rounded-full p-1" role="group" aria-label="Language" data-i18n-aria-label="language_label">
                    <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="en" id="btn-en" data-i18n="lang_en" type="button">EN</button>
                    <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="ar" id="btn-ar" data-i18n="lang_ar" type="button">AR</button>
                </div>

                <a href="login.php" class="header-login rounded-full px-3 py-2 text-sm font-bold transition" data-i18n="nav_login">Login</a>
                <a href="register.php" class="header-cta inline-flex min-h-11 items-center justify-center rounded-full px-5 py-2.5 text-sm font-bold transition" data-i18n="nav_cta">Create Clinic Account</a>
            </div>

            <button id="menu-toggle" class="menu-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl lg:hidden" type="button" aria-expanded="false" aria-controls="mobile-menu">
                <i id="menu-icon" class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
                <span class="sr-only" data-i18n="menu_toggle">Toggle menu</span>
            </button>
        </div>

        <div id="mobile-menu" class="mobile-menu hidden pb-4 lg:hidden">
            <div class="mobile-menu-panel">
                <nav class="flex flex-col gap-1" aria-label="Mobile navigation" data-i18n-aria-label="mobile_navigation">
                    <a href="#services" class="mobile-nav-link" data-i18n="nav_services">Services</a>
                    <a href="#workflow" class="mobile-nav-link" data-i18n="nav_workflow">How It Works</a>
                    <a href="#faq" class="mobile-nav-link" data-i18n="nav_faq">FAQ</a>
                    <a href="#contact" class="mobile-nav-link" data-i18n="nav_contact">Contact</a>
                </nav>

                <div class="mobile-menu-divider"></div>

                <div class="mobile-menu-actions">
                    <div class="mobile-language-switch flex items-center rounded-full p-1" role="group" aria-label="Language" data-i18n-aria-label="language_label">
                        <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="en" data-i18n="lang_en" type="button">EN</button>
                        <button class="lang-btn rounded-full px-3 py-1.5 text-xs font-bold transition" data-lang="ar" data-i18n="lang_ar" type="button">AR</button>
                    </div>

                    <div class="mobile-auth-actions">
                        <a href="login.php" class="mobile-login" data-i18n="nav_login">Login</a>
                        <a href="register.php" class="mobile-register" data-i18n="nav_cta">Create Clinic Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
