<!DOCTYPE html>
<html lang="en" dir="ltr" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Easy Implant | Dental Services for Clinics</title>
    <meta name="description" content="Submit surgical guide and implant surgeon requests, upload case files, and track clinic requests through Easy Implant." />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'Cairo', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            navy: '#102c44',
                            blue: '#216f9f',
                            ice: '#9ed8f0',
                            silver: '#d9e4eb',
                            mist: '#f5f9fc',
                            text: '#52697a',
                            dark: '#07121f',
                            line: '#dce7ee'
                        }
                    },
                    boxShadow: {
                        soft: '0 18px 50px rgba(7, 18, 31, 0.10)'
                    }
                }
            }
        };
    </script>

    <link rel="stylesheet" href="css/style.css" />
</head>

<body class="home-page bg-white text-brand-navy selection:bg-brand-blue selection:text-white">
    <button id="scroll-top" class="scroll-top">
        <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
        <span class="sr-only" data-i18n="scroll_top">Scroll to top</span>
    </button>

    <?php include 'includes/header.php'; ?>

    <main id="home">
        <section class="hero-section relative min-h-screen overflow-hidden" aria-labelledby="hero-title">
            <div class="absolute inset-0">
                <video id="hero-video" autoplay loop muted playsinline class="hero-video h-full w-full object-cover">
                    <source src="v.mp4?v=<?= filemtime(__DIR__ . '/v.mp4') ?>" type="video/mp4" />
                </video>
                <div class="hero-overlay absolute inset-0" aria-hidden="true"></div>
                <div class="hero-grid-lines absolute inset-0" aria-hidden="true"></div>
            </div>

            <div class="hero-content-shell relative z-10 mx-auto flex min-h-screen w-full max-w-7xl items-center px-5 pb-10 pt-28 sm:px-6 lg:px-8">
                <div class="hero-copy reveal max-w-xl">
                    <p class="hero-kicker mb-5 text-sm font-semibold tracking-[0.18em] text-brand-ice" data-i18n="hero_kicker">For dental clinics</p>
                    <h1 id="hero-title" class="mb-5 max-w-xl text-4xl font-extrabold leading-[1.08] text-white sm:text-5xl lg:text-[3.5rem]" data-i18n="hero_title">Implant dentistry services for your clinic in one place</h1>
                    <p class="mb-8 max-w-lg text-base leading-8 text-slate-200 sm:text-lg" data-i18n="hero_text">Send case details and files, track the request, and coordinate a surgical guide or implant surgeon service through your clinic account.</p>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="register.php" class="primary-hero-btn inline-flex items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-bold text-brand-dark transition hover:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-dark" data-i18n="hero_btn_register">Create Clinic Account</a>
                        <a href="login.php" class="secondary-hero-btn inline-flex items-center justify-center rounded-full border border-white/25 bg-white/10 px-6 py-3.5 text-sm font-bold text-white backdrop-blur-md transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white" data-i18n="hero_btn_login">Login</a>
                    </div>

                    <p class="mt-5 max-w-lg text-sm leading-6 text-slate-300" data-i18n="hero_note">New accounts require admin approval before requests can be submitted.</p>
                </div>
            </div>
        </section>

        <section id="services" class="medical-section bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                <div class="section-heading reveal mb-12 max-w-2xl">
                    <p class="section-eyebrow" data-i18n="services_badge">Services</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-navy sm:text-4xl" data-i18n="services_title">Two services available through your clinic account</h2>
                    <p class="mt-4 text-base leading-7 text-brand-text" data-i18n="services_intro">Choose the service required for the case, submit its data, and follow the request from the same account.</p>
                </div>

                <div class="services-frame reveal">
                    <article class="service-panel">
                        <div class="service-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></div>
                        <div>
                            <h3 class="text-2xl font-bold text-brand-navy" data-i18n="service1_title">Surgical Guide</h3>
                            <p class="mt-3 max-w-xl leading-7 text-brand-text" data-i18n="service1_text">Send case data, scans, and design files, then specify the implants and required guide delivery method.</p>
                            <ul class="service-list mt-7 space-y-3 text-sm leading-6 text-brand-text">
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service1_point1">Upload case files.</span></li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service1_point2">Specify implant data.</span></li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service1_point3">Track design, production, and delivery.</span></li>
                            </ul>
                        </div>
                    </article>

                    <article class="service-panel">
                        <div class="service-icon service-icon-alt" aria-hidden="true"><i class="fa-solid fa-user-doctor"></i></div>
                        <div>
                            <h3 class="text-2xl font-bold text-brand-navy" data-i18n="service2_title">Request an Implant Surgeon</h3>
                            <p class="mt-3 max-w-xl leading-7 text-brand-text" data-i18n="service2_text">Send the patient details and proposed appointment so the administration can review the request and coordinate the appropriate surgeon.</p>
                            <ul class="service-list mt-7 space-y-3 text-sm leading-6 text-brand-text">
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service2_point1">Enter case details.</span></li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service2_point2">Choose a proposed appointment.</span></li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i><span data-i18n="service2_point3">Track the request and surgeon assignment.</span></li>
                            </ul>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="workflow" class="medical-section border-y border-brand-line bg-brand-mist py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                <div class="section-heading reveal mb-12 max-w-2xl">
                    <p class="section-eyebrow" data-i18n="workflow_badge">How It Works</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-navy sm:text-4xl" data-i18n="workflow_title">From clinic registration to completion in four steps</h2>
                    <p class="mt-4 text-base leading-7 text-brand-text" data-i18n="workflow_intro">The account, request, review, payment when required, and follow-up all happen through one clear path.</p>
                </div>

                <div class="workflow-line reveal">
                    <article class="workflow-step">
                        <span class="step-number">1</span>
                        <h3 class="mt-5 text-lg font-bold text-brand-navy" data-i18n="step1_title">Create the clinic account</h3>
                        <p class="mt-2 text-sm leading-7 text-brand-text" data-i18n="step1_text">Register the clinic details and wait for admin approval.</p>
                    </article>
                    <article class="workflow-step">
                        <span class="step-number">2</span>
                        <h3 class="mt-5 text-lg font-bold text-brand-navy" data-i18n="step2_title">Submit the request</h3>
                        <p class="mt-2 text-sm leading-7 text-brand-text" data-i18n="step2_text">Choose the service, enter the case, and upload the required files.</p>
                    </article>
                    <article class="workflow-step">
                        <span class="step-number">3</span>
                        <h3 class="mt-5 text-lg font-bold text-brand-navy" data-i18n="step3_title">Review and payment</h3>
                        <p class="mt-2 text-sm leading-7 text-brand-text" data-i18n="step3_text">The administration reviews the request, and payment or receipt upload appears when required.</p>
                    </article>
                    <article class="workflow-step">
                        <span class="step-number">4</span>
                        <h3 class="mt-5 text-lg font-bold text-brand-navy" data-i18n="step4_title">Track and receive</h3>
                        <p class="mt-2 text-sm leading-7 text-brand-text" data-i18n="step4_text">Follow status changes until execution and receipt of files or final service details.</p>
                    </article>
                </div>

                <div class="status-strip reveal mt-10">
                    <span data-i18n="status_review">Under Review</span>
                    <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                    <span data-i18n="status_payment">Awaiting Payment</span>
                    <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                    <span data-i18n="status_progress">In Progress</span>
                    <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                    <span data-i18n="status_complete">Completed</span>
                </div>
            </div>
        </section>

        <section id="portal" class="medical-section bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                <div class="clinic-portal reveal">
                    <div class="portal-copy">
                        <p class="section-eyebrow" data-i18n="portal_badge">Clinic Account</p>
                        <h2 class="mt-3 max-w-xl text-3xl font-bold tracking-tight text-brand-navy sm:text-4xl" data-i18n="portal_title">One place to send and follow your clinic requests</h2>
                        <p class="mt-4 max-w-xl leading-7 text-brand-text" data-i18n="portal_intro">Your clinic account keeps service requests, case files, payment receipts, and final information in one place.</p>

                        <ul class="portal-feature-list mt-8 space-y-4">
                            <li><span class="feature-dot"></span><span data-i18n="portal_point1">Submit service requests.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point2">Upload case files and payment receipts.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point3">Track the status of each request.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point4">Receive final files and service information.</span></li>
                        </ul>
                    </div>

                    <div class="portal-preview" aria-hidden="true">
                        <div class="portal-preview-top">
                            <span class="preview-dot"></span>
                            <span class="preview-dot"></span>
                            <span class="preview-dot"></span>
                            <span class="preview-title-bar"></span>
                        </div>
                        <div class="portal-preview-body">
                            <div class="preview-sidebar">
                                <span></span><span></span><span></span><span></span>
                            </div>
                            <div class="preview-main">
                                <div class="preview-heading-row"><span></span><span></span></div>
                                <div class="preview-request-row"><span class="preview-icon"></span><span class="preview-line long"></span><span class="preview-pill"></span></div>
                                <div class="preview-request-row"><span class="preview-icon"></span><span class="preview-line medium"></span><span class="preview-pill"></span></div>
                                <div class="preview-request-row"><span class="preview-icon"></span><span class="preview-line short"></span><span class="preview-pill"></span></div>
                                <div class="preview-footer-bars"><span></span><span></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="medical-section border-t border-brand-line bg-brand-mist py-20 sm:py-24">
            <div class="mx-auto max-w-4xl px-5 sm:px-6 lg:px-8">
                <div class="section-heading reveal mb-10 max-w-2xl">
                    <p class="section-eyebrow" data-i18n="faq_badge">FAQ</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-navy sm:text-4xl" data-i18n="faq_title">Common questions before submitting a request</h2>
                    <p class="mt-4 text-base leading-7 text-brand-text" data-i18n="faq_intro">Short answers about account approval, required files, request tracking, and surgeon assignment.</p>
                </div>

                <div class="faq-list reveal divide-y divide-brand-line border-y border-brand-line">
                    <div class="faq-item">
                        <button class="faq-toggle flex w-full items-center justify-between gap-6 py-6 text-start" type="button">
                            <span class="text-base font-bold text-brand-navy sm:text-lg" data-i18n="faq1_q">Can I submit a request immediately after creating an account?</span>
                            <i class="faq-icon fa-solid fa-plus shrink-0 text-brand-blue" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer"><div><p class="pb-6 text-sm leading-7 text-brand-text" data-i18n="faq1_a">No. New clinic accounts require admin approval before requests can be submitted.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle flex w-full items-center justify-between gap-6 py-6 text-start" type="button">
                            <span class="text-base font-bold text-brand-navy sm:text-lg" data-i18n="faq2_q">What files are required for the surgical guide service?</span>
                            <i class="faq-icon fa-solid fa-plus shrink-0 text-brand-blue" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer"><div><p class="pb-6 text-sm leading-7 text-brand-text" data-i18n="faq2_a">Upload the case scans and design-related files requested in the surgical guide form, along with the required case and implant details.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle flex w-full items-center justify-between gap-6 py-6 text-start" type="button">
                            <span class="text-base font-bold text-brand-navy sm:text-lg" data-i18n="faq3_q">How do I follow the request status?</span>
                            <i class="faq-icon fa-solid fa-plus shrink-0 text-brand-blue" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer"><div><p class="pb-6 text-sm leading-7 text-brand-text" data-i18n="faq3_a">Open your clinic account to view each request and follow its status as it moves through review, payment when required, execution, and completion.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle flex w-full items-center justify-between gap-6 py-6 text-start" type="button">
                            <span class="text-base font-bold text-brand-navy sm:text-lg" data-i18n="faq4_q">How is the implant surgeon assigned?</span>
                            <i class="faq-icon fa-solid fa-plus shrink-0 text-brand-blue" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer"><div><p class="pb-6 text-sm leading-7 text-brand-text" data-i18n="faq4_a">The administration reviews the request and proposed appointment, then coordinates and assigns the appropriate surgeon. The clinic does not select the surgeon directly.</p></div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="final-cta relative overflow-hidden bg-brand-dark py-20 text-white sm:py-24">
            <div class="cta-glow absolute inset-0" aria-hidden="true"></div>
            <div class="relative z-10 mx-auto max-w-5xl px-5 text-center sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-3xl">
                    <p class="section-eyebrow section-eyebrow-dark" data-i18n="cta_badge">Get Started</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl" data-i18n="cta_title">Start sending your clinic requests from one place</h2>
                    <p class="mx-auto mt-4 max-w-2xl leading-8 text-slate-300" data-i18n="cta_text">Create the clinic account, wait for approval, then choose the service you need and submit the case details.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="register.php" class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-bold text-brand-dark transition hover:bg-sky-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white" data-i18n="cta_btn_primary">Create Clinic Account</a>
                        <a href="login.php" class="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white" data-i18n="cta_btn_secondary">I Already Have an Account</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="doctors-container" class="hidden" hidden aria-hidden="true"></div>

    <?php include 'includes/footer.php'; ?>

    <section class="border-t border-slate-200 bg-white py-5" aria-label="بيانات التواصل" lang="ar" dir="rtl">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-2 px-6 text-center text-sm text-brand-text">
            <address class="not-italic">
                <span class="font-bold text-brand-navy">العنوان:</span>
                الحي 5، الهضبة الوسطى، المقطم، القاهرة
            </address>
            <p>
                <span class="font-bold text-brand-navy">رقم التليفون:</span>
                <a href="tel:+201125979394" class="hover:text-brand-blue" dir="ltr">01125979394</a>
            </p>
        </div>
    </section>

    <script src="js/translations.js"></script>
    <script src="js/main.js"></script>
    <script>
        (function () {
            const header = document.getElementById('main-header');
            const video = document.getElementById('hero-video');
            const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

            const updateHeader = () => {
                if (header) header.classList.toggle('is-scrolled', window.scrollY > 24);
            };

            const updateMotion = () => {
                if (!video) return;
                if (motionQuery.matches) {
                    video.pause();
                } else {
                    const playPromise = video.play();
                    if (playPromise && typeof playPromise.catch === 'function') playPromise.catch(() => {});
                }
            };

            updateHeader();
            updateMotion();
            window.addEventListener('scroll', updateHeader, { passive: true });
            if (typeof motionQuery.addEventListener === 'function') motionQuery.addEventListener('change', updateMotion);
        })();
    </script>
</body>

</html>
