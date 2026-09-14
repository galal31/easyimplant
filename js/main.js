const doctors = [
            {
                name_en: 'Dr. Ahmed Hany',
                name_ar: 'د. أحمد هاني',
                specialty_en: 'Implant Surgeon',
                specialty_ar: 'جراح زراعة أسنان',
                image: 'images/doctors/hany.jpeg',
                experience: '+10',
                cases: '+850',
                rating: '4.9'
            },
            {
                name_en: 'Dr. Ahmed Arafa',
                name_ar: 'د. أحمد عرفة',
                specialty_en: 'Oral Surgery Consultant',
                specialty_ar: 'استشاري جراحة الفم',
                image: 'images/doctors/arafa.jpeg',
                experience: '+15',
                cases: '+1200',
                rating: '4.8'
            },
            {
                name_en: 'Dr. Sara Nabil',
                name_ar: 'د. سارة نبيل',
                specialty_en: 'Guided Surgery Specialist',
                specialty_ar: 'أخصائية الجراحة الموجهة',
                image: 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=800&q=80',
                experience: '+8',
                cases: '+400',
                rating: '5.0'
            },
            {
                name_en: 'Dr. Karim Essam',
                name_ar: 'د. كريم عصام',
                specialty_en: 'Implantology Consultant',
                specialty_ar: 'استشاري زراعة الأسنان',
                image: 'https://images.unsplash.com/photo-1614608682850-e0d6ed316d47?auto=format&fit=crop&w=800&q=80',
                experience: '+12',
                cases: '+950',
                rating: '4.9'
            }
        ];

        

        let currentLang = 'en';

        function applyLanguage(lang) {
            currentLang = lang;
            document.documentElement.lang = lang;
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';

            document.querySelectorAll('[data-i18n]').forEach((element) => {
                const key = element.getAttribute('data-i18n');
                if (translations[lang][key]) {
                    element.textContent = translations[lang][key];
                }
            });

            document.querySelectorAll('.lang-btn').forEach((btn) => {
                const isActive = btn.dataset.lang === lang;
                btn.classList.toggle('bg-brand-blue', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('text-brand-text', !isActive);
                btn.classList.toggle('hover:text-brand-blue', !isActive);
            });

            renderDoctors();
        }

        function renderDoctors() {
            const container = document.getElementById('doctors-container');
            if (!container) return;

            container.innerHTML = doctors.map((doctor) => `
                <article class="reveal overflow-hidden rounded-4xl border border-slate-200 bg-white shadow-card group">
                    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                        <img src="${doctor.image}" alt="${currentLang === 'ar' ? doctor.name_ar : doctor.name_en}"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                        <div class="absolute inset-0 bg-gradient-to-t from-brand-dark/70 via-brand-dark/10 to-transparent opacity-100 transition-opacity duration-500 group-hover:opacity-80"></div>
                    </div>
                    <div class="p-6">
                        <div class="mb-4 inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-brand-blue">
                            ${translations[currentLang].doctor_badge}
                        </div>
                        <h3 class="text-xl font-bold text-brand-navy">${currentLang === 'ar' ? doctor.name_ar : doctor.name_en}</h3>
                        <p class="mt-2 text-sm font-medium text-brand-text">${currentLang === 'ar' ? doctor.specialty_ar : doctor.specialty_en}</p>
                        
                        <div class="mt-6 grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 pt-5 rtl:divide-x-reverse">
                            <div class="text-center px-1">
                                <span class="block text-lg font-extrabold text-brand-navy">${doctor.experience}</span>
                                <span class="block mt-1 text-[11px] font-semibold uppercase tracking-wider text-brand-text">
                                    ${currentLang === 'ar' ? 'سنوات خبرة' : 'Years Exp.'}
                                </span>
                            </div>
                            <div class="text-center px-1">
                                <span class="block text-lg font-extrabold text-brand-navy">${doctor.cases}</span>
                                <span class="block mt-1 text-[11px] font-semibold uppercase tracking-wider text-brand-text">
                                    ${currentLang === 'ar' ? 'حالة' : 'Cases'}
                                </span>
                            </div>
                            <div class="text-center px-1">
                                <span class="block text-lg font-extrabold text-brand-navy flex items-center justify-center gap-1">
                                    ${doctor.rating}
                                    <i class="fa-solid fa-star text-amber-400 text-[12px] mb-0.5"></i>
                                </span>
                                <span class="block mt-1 text-[11px] font-semibold uppercase tracking-wider text-brand-text">
                                    ${currentLang === 'ar' ? 'تقييم' : 'Rating'}
                                </span>
                            </div>
                        </div>

                    </div>
                </article>
            `).join('');

            observeReveal();
        }

        function observeReveal() {
            const reveals = document.querySelectorAll('.reveal:not(.active)');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.12
            });

            reveals.forEach((item) => observer.observe(item));
        }

        function setupMenu() {
            const toggle = document.getElementById('menu-toggle');
            const menu = document.getElementById('mobile-menu');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', () => {
                menu.classList.toggle('hidden');
                const expanded = !menu.classList.contains('hidden');
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggle.innerHTML = expanded
                    ? '<i class="fa-solid fa-xmark text-lg"></i>'
                    : '<i class="fa-solid fa-bars text-lg"></i>';
            });

            menu.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    menu.classList.add('hidden');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.innerHTML = '<i class="fa-solid fa-bars text-lg"></i>';
                });
            });
        }

        function setupLanguageButtons() {
            document.querySelectorAll('.lang-btn').forEach((btn) => {
                btn.addEventListener('click', () => applyLanguage(btn.dataset.lang));
            });
        }

        function setupFaq() {
            document.querySelectorAll('.faq-toggle').forEach((button) => {
                button.addEventListener('click', () => {
                    const item = button.closest('.faq-item');
                    item.classList.toggle('faq-open');
                });
            });
        }

        function setupScrollTop() {
            const button = document.getElementById('scroll-top');
            if (!button) return;

            window.addEventListener('scroll', () => {
                if (window.scrollY > 500) {
                    button.classList.add('show');
                } else {
                    button.classList.remove('show');
                }
            });

            button.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        function setupActiveNav() {
            const links = document.querySelectorAll('a[href^="#"]');
            const sections = [...document.querySelectorAll('main section[id]')];

            const activate = () => {
                let currentId = '';
                sections.forEach((section) => {
                    const sectionTop = section.offsetTop - 120;
                    if (window.scrollY >= sectionTop) currentId = section.getAttribute('id');
                });

                links.forEach((link) => {
                    link.classList.toggle('active-link', link.getAttribute('href') === `#${currentId}`);
                });
            };

            window.addEventListener('scroll', activate);
            activate();
        }

        setupMenu();
        setupLanguageButtons();
        setupFaq();
        setupScrollTop();
        setupActiveNav();
        observeReveal();
        applyLanguage('en');