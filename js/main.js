let currentLang = 'en';
const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
const autoI18nOriginalText = new WeakMap();
const autoI18nOriginalAttributes = new WeakMap();

function i18nText(key, fallback = '', replacements = {}) {
    const catalog = typeof translations !== 'undefined' && translations[currentLang] ? translations[currentLang] : {};
    let text = Object.prototype.hasOwnProperty.call(catalog, key) ? catalog[key] : fallback;
    Object.entries(replacements).forEach(([name, value]) => {
        text = text.replaceAll(`{${name}}`, String(value));
    });
    return text;
}

function i18nApiMessage(message, fallbackKey = 'generic_error') {
    const refreshMatch = String(message || '').match(/^Please wait (\d+) second\(s\) before refreshing messages again\.$/);
    if (refreshMatch) return i18nText('wait_refresh_seconds', 'Please wait {seconds} seconds before refreshing messages again.', {seconds: refreshMatch[1]});
    const lengthMatch = String(message || '').match(/^Messages cannot exceed (\d+) characters\.$/);
    if (lengthMatch) return i18nText('message_length_limit', 'Messages cannot exceed {count} characters.', {count: lengthMatch[1]});
    const messageKeys = {
        'Email and password are required.': 'error_email_password_required',
        'Invalid email or password.': 'error_invalid_credentials',
        'Your account is pending approval by the administration.': 'error_account_pending',
        'Your account has been temporarily paused by the administration. Please contact support.': 'error_account_paused',
        'Your account application was rejected.': 'error_account_rejected',
        'Login failed due to a server error.': 'error_login_server',
        'All fields are required.': 'error_all_fields',
        'Invalid email format.': 'error_invalid_email',
        'Password must be at least 8 characters long.': 'error_password_length',
        'Invalid clinic location selection.': 'error_invalid_location',
        'This governorate is not currently available. Please select another governorate.': 'error_governorate_unavailable',
        'Please enter a valid country name.': 'error_invalid_country',
        'Email is already registered.': 'error_email_registered',
        'Registration failed due to a server error.': 'error_registration_server'
        ,'Method not allowed.': 'error_method_not_allowed'
        ,'Unauthorized access.': 'error_unauthorized'
        ,'Unauthorized. Please log in as a clinic.': 'error_unauthorized'
        ,'Your form session expired. Refresh the page and try again.': 'error_session_expired'
        ,'Your session expired. Please refresh the page and try again.': 'error_session_expired'
        ,'Please fill in all required fields correctly.': 'error_required_fields'
        ,'Please enter a valid patient age.': 'error_patient_age'
        ,'Please choose a valid proposed date at least 3 calendar days from today (Cairo time).': 'error_surgeon_date'
        ,'Please complete the required guide details.': 'error_required_guide_details'
        ,'The operation date must be at least two days after submitting the request.': 'error_guide_date'
        ,'Each implant location must be a whole number between 0 and 32.': 'error_implant_count_range'
        ,'Please select an available guided kit to rent.': 'error_rental_kit'
        ,'The selected guided kit price changed. Please refresh and review the new price.': 'error_kit_price_changed'
        ,'Please enter the kit name and select Sleeved or Sleeveless.': 'error_owned_kit_details'
        ,'Please choose whether you will rent a guided kit or use your own kit.': 'error_kit_source'
        ,'Please enter at least one implant location.': 'error_implant_location'
        ,'Please write the implant type name.': 'error_implant_type_name'
        ,'Selected implant type is not available.': 'error_implant_type_unavailable'
        ,'Pricing changed while this page was open. Please refresh the page and review the new price before submitting.': 'error_pricing_changed'
        ,'Please choose valid implant details.': 'error_implant_details'
        ,'The selected implant type is no longer available.': 'error_implant_type_unavailable'
        ,'Please choose a valid All-on package.': 'error_all_on_package'
        ,'Complete the implant type and provider for each selected arch.': 'error_arch_details'
        ,'An implant type selected for an arch is no longer available.': 'error_implant_type_unavailable'
        ,'Choose at least one arch for the All-on treatment.': 'error_choose_arch'
        ,'The selected surgical service is no longer available.': 'error_service_unavailable'
        ,'Please choose a valid surgical service.': 'error_valid_service'
        ,'Failed to submit request due to a server error.': 'error_submit_server'
        ,'The plan was approved. You can now complete payment online.': 'review_approved_payment'
        ,'The plan approval could not be saved. Try again.': 'error_plan_approval'
    };
    return i18nText(messageKeys[message] || fallbackKey, message || i18nText(fallbackKey));
}

function applyUserPagePhrases(lang, root = document.body) {
    if (!root || typeof userPagePhrases === 'undefined') return;

    const arabicPhrases = userPagePhrases.ar || {};
    const translateTextNode = (node) => {
        let original = autoI18nOriginalText.get(node);
        const current = node.nodeValue || '';
        if (original === undefined) {
            const trimmed = current.trim();
            if (!Object.prototype.hasOwnProperty.call(arabicPhrases, trimmed)) return;
            original = current;
            autoI18nOriginalText.set(node, original);
        }
        const source = original.trim();
        const leading = original.match(/^\s*/)?.[0] || '';
        const trailing = original.match(/\s*$/)?.[0] || '';
        node.nodeValue = leading + (lang === 'ar' ? arabicPhrases[source] : source) + trailing;
    };

    if (root.nodeType === Node.TEXT_NODE) translateTextNode(root);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (!parent || parent.closest('script, style, template, [data-no-auto-i18n], [dir="auto"]')) return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
        }
    });
    while (walker.nextNode()) translateTextNode(walker.currentNode);

    const elements = root.nodeType === Node.ELEMENT_NODE ? [root, ...root.querySelectorAll('[placeholder], [aria-label], [title]')] : [];
    elements.forEach((element) => {
        if (element.closest('[data-no-auto-i18n], [dir="auto"]')) return;
        const originals = autoI18nOriginalAttributes.get(element) || {};
        ['placeholder', 'aria-label', 'title'].forEach((attribute) => {
            if (!element.hasAttribute(attribute)) return;
            if (!Object.prototype.hasOwnProperty.call(originals, attribute)) {
                const value = element.getAttribute(attribute);
                if (!Object.prototype.hasOwnProperty.call(arabicPhrases, value)) return;
                originals[attribute] = value;
            }
            element.setAttribute(attribute, lang === 'ar' ? arabicPhrases[originals[attribute]] : originals[attribute]);
        });
        if (Object.keys(originals).length) autoI18nOriginalAttributes.set(element, originals);
    });
}

function applyLanguage(lang) {
    if (!translations || !translations[lang]) return;

    currentLang = lang;
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    try {
        localStorage.setItem('easyimplant_language', lang);
        document.cookie = `easyimplant_language=${lang}; path=/; max-age=31536000; SameSite=Lax`;
    } catch (_) {}

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

    document.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
        const key = element.getAttribute('data-i18n-placeholder');
        if (Object.prototype.hasOwnProperty.call(translations[lang], key)) element.setAttribute('placeholder', translations[lang][key]);
    });

    document.querySelectorAll('[data-i18n-title]').forEach((element) => {
        const key = element.getAttribute('data-i18n-title');
        if (Object.prototype.hasOwnProperty.call(translations[lang], key)) element.setAttribute('title', translations[lang][key]);
    });

    applyUserPagePhrases(lang);

    const titleKey = document.documentElement.dataset.i18nTitle;
    if (titleKey && Object.prototype.hasOwnProperty.call(translations[lang], titleKey)) document.title = translations[lang][titleKey];

    document.querySelectorAll('time[data-localized-date][datetime]').forEach((element) => {
        const date = new Date(element.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) element.textContent = new Intl.DateTimeFormat(lang === 'ar' ? 'ar-EG' : 'en-US', {dateStyle: 'long'}).format(date);
    });
    document.querySelectorAll('time[data-localized-datetime][datetime]').forEach((element) => {
        const date = new Date(element.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) element.textContent = new Intl.DateTimeFormat(lang === 'ar' ? 'ar-EG' : 'en-US', {dateStyle: 'medium', timeStyle: 'short'}).format(date);
    });

    document.querySelectorAll('.lang-btn').forEach((button) => {
        const isActive = button.dataset.lang === lang;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    document.dispatchEvent(new CustomEvent('easyimplant:languagechange', { detail: { language: lang } }));
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
let savedLanguage = 'en';
try {
    savedLanguage = localStorage.getItem('easyimplant_language') || document.documentElement.lang || 'en';
} catch (_) {
    savedLanguage = document.documentElement.lang || 'en';
}
applyLanguage(['en', 'ar'].includes(savedLanguage) ? savedLanguage : 'en');

if (document.body && typeof MutationObserver !== 'undefined') {
    const autoI18nObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => applyUserPagePhrases(currentLang, node)));
    });
    autoI18nObserver.observe(document.body, { childList: true, subtree: true });
}
