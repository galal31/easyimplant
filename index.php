<!DOCTYPE html>
<html lang="en" dir="ltr" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Easy Implant | Dental Services for Clinics</title>
    <meta name="description" content="Submit surgical guide and implant surgeon requests, upload case files, and track clinic requests through Easy Implant." />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'IBM Plex Sans Arabic', 'sans-serif'],
                        display: ['Space Grotesk', 'IBM Plex Sans Arabic', 'sans-serif'],
                        mono: ['IBM Plex Mono', 'monospace'],
                    },
                    colors: {
                        brand: {
                            navy: '#071C2A',
                            enamel: '#123E55',
                            blue: '#24AFCB',
                            ice: '#77D4E8',
                            mist: '#F3F8FB',
                            text: '#526B78',
                            dark: '#071C2A',
                            line: '#D9E8EE',
                            success: '#35B89A'
                        }
                    }
                }
            }
        };
    </script>

    <link rel="stylesheet" href="css/style.css" />
</head>

<body class="home-page bg-white text-brand-navy selection:bg-brand-blue selection:text-white">
    <button id="scroll-top" class="scroll-top" type="button">
        <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
        <span class="sr-only" data-i18n="scroll_top">Scroll to top</span>
    </button>

    <?php include 'includes/header.php'; ?>

    <main id="home">
        <div class="case-journey-map" id="case-journey-map">
            <svg class="case-journey-rail" viewBox="0 0 1440 2200" preserveAspectRatio="none" aria-hidden="true">
                <path class="case-journey-shadow" d="M1115 150 C1275 330 1265 600 1070 760 C900 900 735 955 775 1190 C815 1435 1135 1485 1045 1745 C990 1905 750 1940 675 2100" />
                <path id="case-journey-path" class="case-journey-path" pathLength="1" d="M1115 150 C1275 330 1265 600 1070 760 C900 900 735 955 775 1190 C815 1435 1135 1485 1045 1745 C990 1905 750 1940 675 2100" />
            </svg>

            <section class="hero-section" aria-labelledby="hero-title">
                <div class="hero-video-layer" aria-hidden="true">
                    <video id="hero-video" autoplay loop muted playsinline preload="metadata" class="hero-video">
                        <source src="v.mp4?v=<?= filemtime(__DIR__ . '/v.mp4') ?>" type="video/mp4" />
                    </video>
                </div>
                <div class="hero-video-overlay" aria-hidden="true"></div>
                <div class="hero-atmosphere" aria-hidden="true"></div>
                <div class="hero-layout mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
                    <div class="hero-copy reveal">
                        <p class="hero-kicker" data-i18n="hero_kicker">For dental clinics</p>
                        <h1 id="hero-title" data-i18n="hero_title">Implant dentistry services for your clinic in one place</h1>
                        <p class="hero-text" data-i18n="hero_text">Send case details and files, track the request, and coordinate a surgical guide or implant surgeon service through your clinic account.</p>

                        <div class="hero-actions">
                            <a href="register.php" class="primary-hero-btn" data-i18n="hero_btn_register">Create Clinic Account</a>
                            <a href="login.php" class="secondary-hero-btn" data-i18n="hero_btn_login">Login</a>
                        </div>

                        <div class="hero-note">
                            <span class="hero-note-dot" aria-hidden="true"></span>
                            <p data-i18n="hero_note">New accounts require admin approval before requests can be submitted.</p>
                        </div>
                    </div>

                    <div class="hero-visual reveal delay-100">
                        <div class="case-planning-frame">
                            <div class="case-frame-topline">
                                <span class="case-frame-title" data-i18n="hero_case_label">Case planning path</span>
                                <span class="case-frame-signal" aria-hidden="true"><span></span><span></span><span></span></span>
                            </div>

                            <div class="case-media">
                                <div class="case-media-fallback" aria-hidden="true">
                                    <span class="scan-orbit scan-orbit-one"></span>
                                    <span class="scan-orbit scan-orbit-two"></span>
                                    <span class="scan-axis scan-axis-a"></span>
                                    <span class="scan-axis scan-axis-b"></span>
                                </div>
                                <div class="case-media-tint" aria-hidden="true"></div>
                                <svg class="hero-trajectory" viewBox="0 0 640 420" aria-hidden="true">
                                    <path d="M88 318 C160 278 190 176 292 169 C389 163 407 250 532 91" />
                                    <circle cx="88" cy="318" r="7" />
                                    <circle cx="292" cy="169" r="7" />
                                    <circle cx="532" cy="91" r="7" />
                                </svg>
                            </div>

                            <div class="planning-stages" aria-hidden="true">
                                <span><i class="fa-solid fa-wave-square"></i><b data-i18n="stage_cbct">CBCT</b></span>
                                <i class="fa-solid fa-arrow-right stage-arrow"></i>
                                <span><i class="fa-solid fa-compass-drafting"></i><b data-i18n="stage_planning">Planning</b></span>
                                <i class="fa-solid fa-arrow-right stage-arrow"></i>
                                <span><i class="fa-solid fa-layer-group"></i><b data-i18n="stage_guide">Surgical Guide</b></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="services" class="journey-section services-section" aria-labelledby="services-title">
                <div class="section-shell mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                    <div class="section-heading reveal">
                        <p class="section-eyebrow" data-i18n="services_badge">Services</p>
                        <h2 id="services-title" data-i18n="services_title">Two services available through your clinic account</h2>
                        <p data-i18n="services_intro">Choose the service required for the case, submit its data, and follow the request from the same account.</p>
                    </div>

                    <div class="service-routes">
                        <article class="service-route service-route-guide reveal">
                            <div class="service-route-visual guide-route-visual" aria-hidden="true">
                                <div class="route-file-stack">
                                    <span><i class="fa-solid fa-file-medical"></i></span>
                                    <span><i class="fa-solid fa-cube"></i></span>
                                    <span><i class="fa-solid fa-layer-group"></i></span>
                                </div>
                                <svg viewBox="0 0 420 180" preserveAspectRatio="none">
                                    <path d="M34 118 C118 118 114 54 202 54 C289 54 291 126 386 91" />
                                    <circle cx="34" cy="118" r="5" />
                                    <circle cx="202" cy="54" r="5" />
                                    <circle cx="386" cy="91" r="5" />
                                </svg>
                            </div>
                            <div class="service-route-copy">
                                <div class="service-route-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></div>
                                <h3 data-i18n="service1_title">Surgical Guide</h3>
                                <p data-i18n="service1_text">Send case data, scans, and design files, then specify the implants and required guide delivery method.</p>
                                <ul class="service-list">
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service1_point1">Upload case files.</span></li>
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service1_point2">Specify implant data.</span></li>
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service1_point3">Track design, production, and delivery.</span></li>
                                </ul>
                            </div>
                        </article>

                        <article class="service-route service-route-surgeon reveal delay-100">
                            <div class="service-route-copy">
                                <div class="service-route-icon"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i></div>
                                <h3 data-i18n="service2_title">Request an Implant Surgeon</h3>
                                <p data-i18n="service2_text">Send the patient details and proposed appointment so the administration can review the request and coordinate the appropriate surgeon.</p>
                                <ul class="service-list">
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service2_point1">Enter case details.</span></li>
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service2_point2">Choose a proposed appointment.</span></li>
                                    <li><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-i18n="service2_point3">Track the request and surgeon assignment.</span></li>
                                </ul>
                            </div>
                            <div class="service-route-visual surgeon-route-visual" aria-hidden="true">
                                <div class="surgeon-route-node"><i class="fa-regular fa-clipboard"></i></div>
                                <span></span>
                                <div class="surgeon-route-node"><i class="fa-regular fa-calendar"></i></div>
                                <span></span>
                                <div class="surgeon-route-node surgeon-route-node-final"><i class="fa-solid fa-user-doctor"></i></div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="workflow" class="journey-section workflow-section" aria-labelledby="workflow-title">
                <div class="section-shell mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                    <div class="section-heading reveal">
                        <p class="section-eyebrow" data-i18n="workflow_badge">How It Works</p>
                        <h2 id="workflow-title" data-i18n="workflow_title">From clinic registration to completion in four steps</h2>
                        <p data-i18n="workflow_intro">The account, request, review, payment when required, and follow-up all happen through one clear path.</p>
                    </div>

                    <div class="workflow-track reveal">
                        <div class="workflow-track-line" aria-hidden="true"></div>
                        <article class="workflow-step">
                            <span class="step-number">1</span>
                            <div>
                                <h3 data-i18n="step1_title">Create the clinic account</h3>
                                <p data-i18n="step1_text">Register the clinic details and wait for admin approval.</p>
                            </div>
                        </article>
                        <article class="workflow-step">
                            <span class="step-number">2</span>
                            <div>
                                <h3 data-i18n="step2_title">Submit the request</h3>
                                <p data-i18n="step2_text">Choose the service, enter the case, and upload the required files.</p>
                            </div>
                        </article>
                        <article class="workflow-step">
                            <span class="step-number">3</span>
                            <div>
                                <h3 data-i18n="step3_title">Review and payment</h3>
                                <p data-i18n="step3_text">The administration reviews the request, and payment or receipt upload appears when required.</p>
                            </div>
                        </article>
                        <article class="workflow-step">
                            <span class="step-number">4</span>
                            <div>
                                <h3 data-i18n="step4_title">Track and receive</h3>
                                <p data-i18n="step4_text">Follow status changes until execution and receipt of files or final service details.</p>
                            </div>
                        </article>
                    </div>

                    <div class="status-journey reveal" aria-label="Request status journey" data-i18n-aria-label="status_journey_label">
                        <span class="status-pill status-review"><i class="fa-regular fa-eye" aria-hidden="true"></i><b data-i18n="status_review">Under Review</b></span>
                        <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                        <span class="status-pill status-payment"><i class="fa-regular fa-credit-card" aria-hidden="true"></i><b data-i18n="status_payment">Awaiting Payment</b></span>
                        <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                        <span class="status-pill status-progress"><i class="fa-solid fa-rotate" aria-hidden="true"></i><b data-i18n="status_progress">In Progress</b></span>
                        <i class="fa-solid fa-arrow-right status-arrow" aria-hidden="true"></i>
                        <span class="status-pill status-complete"><i class="fa-solid fa-check" aria-hidden="true"></i><b data-i18n="status_complete">Completed</b></span>
                    </div>
                </div>
            </section>
        </div>

        <section id="portal" class="portal-section" aria-labelledby="portal-title">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                <div class="clinic-portal reveal">
                    <div class="portal-copy">
                        <p class="section-eyebrow" data-i18n="portal_badge">Clinic Account</p>
                        <h2 id="portal-title" data-i18n="portal_title">One place to send and follow your clinic requests</h2>
                        <p data-i18n="portal_intro">Your clinic account keeps service requests, case files, payment receipts, and final information in one place.</p>

                        <ul class="portal-feature-list">
                            <li><span class="feature-dot"></span><span data-i18n="portal_point1">Submit service requests.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point2">Upload case files and payment receipts.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point3">Track the status of each request.</span></li>
                            <li><span class="feature-dot"></span><span data-i18n="portal_point4">Receive final files and service information.</span></li>
                        </ul>
                    </div>

                    <div class="case-desk" aria-hidden="true">
                        <div class="case-desk-header">
                            <div>
                                <span class="case-desk-kicker" data-i18n="portal_preview_kicker">Clinic portal</span>
                                <strong data-i18n="portal_preview_title">Case Desk</strong>
                            </div>
                            <span class="case-desk-control"><i class="fa-solid fa-ellipsis"></i></span>
                        </div>
                        <div class="case-desk-tabs">
                            <span class="active" data-i18n="portal_preview_requests">Requests</span>
                            <span data-i18n="portal_preview_files">Files</span>
                            <span data-i18n="portal_preview_followup">Follow-up</span>
                        </div>
                        <div class="case-desk-list">
                            <div class="case-row">
                                <span class="case-row-icon"><i class="fa-solid fa-layer-group"></i></span>
                                <div class="case-row-copy">
                                    <strong data-i18n="service1_title">Surgical Guide</strong>
                                    <span data-i18n="portal_case_files">Case files and delivery</span>
                                </div>
                                <span class="case-status case-status-review" data-i18n="status_review">Under Review</span>
                            </div>
                            <div class="case-row">
                                <span class="case-row-icon"><i class="fa-solid fa-user-doctor"></i></span>
                                <div class="case-row-copy">
                                    <strong data-i18n="service2_title">Request an Implant Surgeon</strong>
                                    <span data-i18n="portal_case_coordination">Appointment and coordination</span>
                                </div>
                                <span class="case-status case-status-progress" data-i18n="status_progress">In Progress</span>
                            </div>
                        </div>
                        <div class="case-desk-footer">
                            <span><i class="fa-regular fa-folder-open"></i><b data-i18n="portal_preview_files">Files</b></span>
                            <span><i class="fa-solid fa-route"></i><b data-i18n="portal_preview_followup">Follow-up</b></span>
                            <span><i class="fa-solid fa-box-archive"></i><b data-i18n="portal_preview_delivery">Final delivery</b></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="faq-section" aria-labelledby="faq-title">
            <div class="mx-auto max-w-4xl px-5 sm:px-6 lg:px-8">
                <div class="section-heading reveal">
                    <p class="section-eyebrow" data-i18n="faq_badge">FAQ</p>
                    <h2 id="faq-title" data-i18n="faq_title">Common questions before submitting a request</h2>
                    <p data-i18n="faq_intro">Short answers about account approval, required files, request tracking, and surgeon assignment.</p>
                </div>

                <div class="faq-list reveal">
                    <div class="faq-item">
                        <button class="faq-toggle" id="faq-toggle-1" type="button" aria-expanded="false" aria-controls="faq-answer-1">
                            <span data-i18n="faq1_q">Can I submit a request immediately after creating an account?</span>
                            <i class="faq-icon fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer" id="faq-answer-1" role="region" aria-labelledby="faq-toggle-1"><div><p data-i18n="faq1_a">No. New clinic accounts require admin approval before requests can be submitted.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle" id="faq-toggle-2" type="button" aria-expanded="false" aria-controls="faq-answer-2">
                            <span data-i18n="faq2_q">What files are required for the surgical guide service?</span>
                            <i class="faq-icon fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer" id="faq-answer-2" role="region" aria-labelledby="faq-toggle-2"><div><p data-i18n="faq2_a">Upload the case scans and design-related files requested in the surgical guide form, along with the required case and implant details.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle" id="faq-toggle-3" type="button" aria-expanded="false" aria-controls="faq-answer-3">
                            <span data-i18n="faq3_q">How do I follow the request status?</span>
                            <i class="faq-icon fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer" id="faq-answer-3" role="region" aria-labelledby="faq-toggle-3"><div><p data-i18n="faq3_a">Open your clinic account to view each request and follow its status as it moves through review, payment when required, execution, and completion.</p></div></div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-toggle" id="faq-toggle-4" type="button" aria-expanded="false" aria-controls="faq-answer-4">
                            <span data-i18n="faq4_q">How is the implant surgeon assigned?</span>
                            <i class="faq-icon fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="faq-answer" id="faq-answer-4" role="region" aria-labelledby="faq-toggle-4"><div><p data-i18n="faq4_a">The administration reviews the request and proposed appointment, then coordinates and assigns the appropriate surgeon. The clinic does not select the surgeon directly.</p></div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="final-cta" aria-labelledby="cta-title">
            <div class="cta-route" aria-hidden="true"><span></span></div>
            <div class="relative z-10 mx-auto max-w-5xl px-5 sm:px-6 lg:px-8">
                <div class="cta-panel reveal">
                    <p class="section-eyebrow" data-i18n="cta_badge">Get Started</p>
                    <h2 id="cta-title" data-i18n="cta_title">Start sending your clinic requests from one place</h2>
                    <p data-i18n="cta_text">Create the clinic account, wait for approval, then choose the service you need and submit the case details.</p>
                    <div class="cta-actions">
                        <a href="register.php" class="cta-primary" data-i18n="cta_btn_primary">Create Clinic Account</a>
                        <a href="login.php" class="cta-secondary" data-i18n="cta_btn_secondary">I Already Have an Account</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="js/translations.js"></script>
    <script src="js/main.js"></script>
</body>

</html>
