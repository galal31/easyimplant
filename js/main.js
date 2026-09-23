let currentLang = 'en';
const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

function applyLanguage(lang) {
    if (!translations || !translations[lang]) return;

    currentLang = lang;
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';

    document.querySelectorAll('[data-i18n]').forEach((element) => {
        const key = element.getAttribute('data-i18n');
        if (Object.prototype.hasOwnProperty.call(translations[lang], key)) {
            element.textContent = translations[lang][key];
        }
    });

    document.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const key = element.getAttribute('data-i18n-aria-label');
        if (Object.prototype.hasOwnProperty.call(translations[lang], key)) {
            element.setAttribute('aria-label', translations[lang][key]);
        }
    });

    document.querySelectorAll('.lang-btn').forEach((button) => {
        const isActive = button.dataset.lang === lang;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function setupLanguageButtons() {
    document.querySelectorAll('.lang-btn').forEach((button) => {
        button.addEventListener('click', () => applyLanguage(button.dataset.lang));
    });
}

function setupMenu() {
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('mobile-menu');
    const icon = document.getElementById('menu-icon');
    if (!toggle || !menu || !icon) return;

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        icon.classList.toggle('fa-bars', !open);
        icon.classList.toggle('fa-xmark', open);
    };

    toggle.addEventListener('click', () => {
        setOpen(menu.classList.contains('hidden'));
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (menu.classList.contains('hidden')) return;
        if (!menu.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) setOpen(false);
    }, { passive: true });
}

function setupFaq() {
    document.querySelectorAll('.faq-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('.faq-item');
            if (!item) return;

            const isOpen = item.classList.toggle('faq-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });
}

function setupReveal() {
    const reveals = document.querySelectorAll('.reveal:not(.active)');
    if (!reveals.length) return;

    if (motionQuery.matches || !('IntersectionObserver' in window)) {
        reveals.forEach((item) => item.classList.add('active'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

    reveals.forEach((item) => observer.observe(item));
}

function setupScrollTop() {
    const button = document.getElementById('scroll-top');
    if (!button) return;

    const update = () => {
        button.classList.toggle('show', window.scrollY > 520);
    };

    window.addEventListener('scroll', update, { passive: true });
    update();

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: motionQuery.matches ? 'auto' : 'smooth' });
    });
}

function setupActiveNav() {
    const links = [...document.querySelectorAll('.nav-link, .mobile-nav-link')];
    const sections = [...document.querySelectorAll('main section[id]')];
    if (!links.length || !sections.length) return;

    let ticking = false;
    const activate = () => {
        const marker = window.scrollY + 150;
        let currentId = '';

        sections.forEach((section) => {
            if (marker >= section.offsetTop) currentId = section.id;
        });

        links.forEach((link) => {
            const active = link.getAttribute('href') === `#${currentId}`;
            link.classList.toggle('active-link', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });

        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(activate);
        }
    }, { passive: true });

    window.addEventListener('resize', activate, { passive: true });
    activate();
}

function setupHeaderScrollState() {
    const header = document.getElementById('main-header');
    if (!header) return;

    const update = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    window.addEventListener('scroll', update, { passive: true });
    update();
}

function setupHeroMotion() {
    const video = document.getElementById('hero-video');
    if (!video) return;

    const update = () => {
        if (motionQuery.matches) {
            video.pause();
            return;
        }

        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {});
        }
    };

    update();
    if (typeof motionQuery.addEventListener === 'function') {
        motionQuery.addEventListener('change', update);
    }
}

function setupJourneyProgress() {
    const journey = document.getElementById('case-journey-map');
    const path = document.getElementById('case-journey-path');
    if (!journey || !path) return;

    path.style.strokeDasharray = '1';

    let ticking = false;
    const update = () => {
        if (motionQuery.matches) {
            path.style.strokeDashoffset = '0';
            ticking = false;
            return;
        }

        const rect = journey.getBoundingClientRect();
        const journeyTop = window.scrollY + rect.top;
        const journeyHeight = journey.offsetHeight;
        const viewportLead = window.innerHeight * 0.42;
        const traveled = window.scrollY + viewportLead - journeyTop;
        const usable = Math.max(journeyHeight - window.innerHeight * 0.28, 1);
        const progress = Math.min(Math.max(traveled / usable, 0), 1);

        path.style.strokeDashoffset = String(1 - progress);
        ticking = false;
    };

    const requestUpdate = () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    };

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    if (typeof motionQuery.addEventListener === 'function') {
        motionQuery.addEventListener('change', requestUpdate);
    }
    update();
}

setupMenu();
setupLanguageButtons();
setupFaq();
setupScrollTop();
setupActiveNav();
setupHeaderScrollState();
setupHeroMotion();
setupJourneyProgress();
setupReveal();
applyLanguage('en');
