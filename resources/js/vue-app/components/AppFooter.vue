<template>
    <footer class="app-footer d-flex align-items-center bg-white border-top border-light-subtle py-1 py-md-2 px-2 px-md-3">
        <div class="d-flex flex-column flex-md-row justify-content-center justify-content-md-between align-items-center w-100 text-center text-md-start gap-1 gap-md-2 gap-xl-3 small flex-md-wrap flex-xl-nowrap">
            <!-- 1. Left Brand -->
            <div class="footer-brand d-inline-flex align-items-center justify-content-center justify-content-md-start text-nowrap flex-shrink-0">
                <span class="fw-bold text-dark">TruckBill</span>
                <span class="fw-semibold mx-1 mx-md-2 text-muted">—</span>
                <span class="d-inline-flex align-items-center text-secondary">
                    A Product of&nbsp;
                    <a
                        href="https://www.taldartechconsultancy.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-success text-decoration-none fw-semibold company-link"
                    >
                        Taldar Tech Consultancy
                    </a>
                </span>
            </div>

            <!-- 2. Footer Links: Help & Support, Privacy Policy, Terms & Conditions, Contact Us -->
            <nav class="d-inline-flex flex-nowrap align-items-center justify-content-center text-nowrap mx-auto" aria-label="Footer Navigation">
                <a
                    href="javascript:void(0)"
                    class="text-decoration-none text-secondary px-1 fw-medium"
                    @click.prevent="isHelpModalOpen = true"
                >
                    Help &amp; Support
                </a>
                <span class="text-muted mx-1" aria-hidden="true">•</span>
                <a
                    href="https://www.truckbill.in/policies/privacy-policy.html"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none text-secondary px-1 fw-medium"
                >
                    Privacy Policy
                </a>
                <span class="text-muted mx-1" aria-hidden="true">•</span>
                <a
                    href="https://www.truckbill.in/policies/terms-and-conditions.html"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none text-secondary px-1 fw-medium"
                >
                    Terms &amp; Conditions
                </a>
                <span class="text-muted mx-1" aria-hidden="true">•</span>
                <a
                    href="https://www.truckbill.in/#contact"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none text-secondary px-1 fw-medium"
                >
                    Contact Us
                </a>
            </nav>

            <!-- 3. Copyright & Dynamic Application Version -->
            <div class="footer-meta d-inline-flex align-items-center justify-content-center justify-content-md-end text-nowrap gap-1 gap-md-2 ms-md-auto flex-shrink-0">
                <span class="text-secondary">© TruckBill. All rights reserved.</span>
                <span class="badge rounded-pill text-white footer-version-pill" style="background-color: #2fb36f;">
                    {{ appVersion }}
                </span>
            </div>
        </div>

        <!-- Help & Support Modal (Bootstrap Structure) -->
        <Teleport to="body">
            <div
                v-if="isHelpModalOpen"
                class="modal fade show d-block"
                tabindex="-1"
                style="background-color: rgba(15, 23, 42, 0.45); backdrop-filter: blur(4px); z-index: 9999;"
                @click.self="isHelpModalOpen = false"
            >
                <div class="modal-dialog modal-dialog-centered" style="max-width: 530px;">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <!-- Modal Header -->
                        <div
                            class="modal-header border-0 px-4 pt-4 pb-3 align-items-start position-relative text-white"
                            style="background-color: #2fb36f;"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm bg-white"
                                    style="width: 48px; height: 48px;"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#2fb36f" viewBox="0 0 16 16">
                                        <path d="M8 1a5 5 0 0 0-5 5v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a6 6 0 1 1 12 0v6a2.5 2.5 0 0 1-2.5 2.5H9.366a1 1 0 0 1-.866.5h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 .866.5H11.5A1.5 1.5 0 0 0 13 12h-1a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h1V6a5 5 0 0 0-5-5"/>
                                    </svg>
                                </div>
                                <div>
                                    <h5 class="modal-title fw-bold text-white mb-0 fs-5">Help &amp; Support</h5>
                                    <div class="mt-1 small fw-medium" style="color: rgba(255, 255, 255, 0.95);">TruckBill Technical &amp; Operational Assistance</div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn-close btn-close-white ms-auto shadow-none"
                                aria-label="Close"
                                @click="isHelpModalOpen = false"
                            ></button>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body px-4 py-3">
                            <p class="text-secondary mb-3 small" style="line-height: 1.55;">
                                Need assistance with billing, lorry receipts (LR), GST compliance, or fleet operations? Contact our support team:
                            </p>

                            <!-- Email Support Card -->
                            <div
                                class="rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between"
                                style="background-color: #dcfce7; border: 1px solid #86efac;"
                            >
                                <div class="d-flex align-items-center gap-3">
                                    <a
                                        href="https://mail.google.com/mail/?view=cm&fs=1&to=support@truckbill.in"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 text-decoration-none shadow-sm"
                                        style="width: 42px; height: 42px; background-color: #15803d; font-size: 1.15rem;"
                                        title="Compose email on Gmail (support@truckbill.in)"
                                    >
                                        <i class="bi bi-envelope-fill"></i>
                                    </a>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em; color: #4b5563;">EMAIL SUPPORT</div>
                                        <a
                                            href="https://mail.google.com/mail/?view=cm&fs=1&to=support@truckbill.in"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="fw-bold fs-6 text-decoration-none d-inline-block"
                                            style="color: #15803d;"
                                            title="Compose email on Gmail (support@truckbill.in)"
                                        >
                                            support@truckbill.in
                                        </a>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-sm bg-white rounded-3 px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2"
                                    style="border: 1px solid #16a34a; color: #15803d;"
                                    @click="copyText('support@truckbill.in', 'email')"
                                    title="Copy email address"
                                >
                                    <i :class="emailCopied ? 'bi bi-check2 text-success' : 'bi bi-copy'" style="color: #15803d;"></i>
                                    <span class="fw-medium small" style="color: #15803d;">{{ emailCopied ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>

                            <!-- Helpline & Contact Card -->
                            <div
                                class="rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between"
                                style="background-color: #dbeafe; border: 1px solid #93c5fd;"
                            >
                                <div class="d-flex align-items-center gap-3 flex-grow-1">
                                    <a
                                        href="tel:+919784451256"
                                        class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm text-decoration-none"
                                        style="width: 42px; height: 42px; background-color: #2563eb; font-size: 1.15rem;"
                                        title="Call +91 97844 51256"
                                    >
                                        <i class="bi bi-telephone-fill"></i>
                                    </a>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em; color: #4b5563;">HELPLINE &amp; CONTACT</div>
                                        <div class="fw-bold" style="font-size: 0.95rem; color: #1d4ed8;">
                                            <a
                                                href="tel:+919784451256"
                                                class="text-decoration-none phone-link"
                                                style="color: #1d4ed8;"
                                                title="Click to call +91 97844 51256"
                                            >
                                                +91 97844 51256
                                            </a>
                                            <span class="mx-1 opacity-75">•</span>
                                            <a
                                                href="tel:+919784946004"
                                                class="text-decoration-none phone-link"
                                                style="color: #1d4ed8;"
                                                title="Click to call +91 97849 46004"
                                            >
                                                +91 97849 46004
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-sm bg-white rounded-3 px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 flex-shrink-0 ms-2"
                                    style="border: 1px solid #2563eb; color: #2563eb;"
                                    @click="copyText('+91 97844 51256, +91 97849 46004', 'phone')"
                                    title="Copy helpline numbers"
                                >
                                    <i :class="phoneCopied ? 'bi bi-check2 text-primary' : 'bi bi-copy'" style="color: #2563eb;"></i>
                                    <span class="fw-medium small" style="color: #2563eb;">{{ phoneCopied ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>

                            <!-- Meta Info Rows -->
                            <div class="pt-2 d-flex flex-column gap-2 small">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2 text-secondary">
                                        <i class="bi bi-clock-fill text-warning fs-6"></i>
                                        <span>Working Hours:</span>
                                    </div>
                                    <span class="fw-bold text-dark">Mon – Sat: 10:00 AM – 7:00 PM IST</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2 text-secondary">
                                        <i class="bi bi-building text-secondary fs-6"></i>
                                        <span>Product by:</span>
                                    </div>
                                    <a
                                        href="https://www.taldartechconsultancy.com/"
                                        target="_blank"
                                        rel="noopener"
                                        class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                                        style="color: #2fb36f;"
                                    >
                                        Taldar Tech Consultancy
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer border-0 px-4 pb-4 pt-1 justify-content-end">
                            <button
                                type="button"
                                class="btn btn-sm px-3 py-1 rounded-2 shadow-sm fw-semibold"
                                style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 0.8125rem;"
                                @click="isHelpModalOpen = false"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </footer>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';

const isHelpModalOpen = ref(false);
const emailCopied = ref(false);
const phoneCopied = ref(false);

const appVersion = computed(() => {
    const raw = (typeof window !== 'undefined' && window.__APP_VERSION__) || '1.0.0';
    return raw.startsWith('v') ? raw : `v${raw}`;
});

async function copyText(text, type) {
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        if (type === 'email') {
            emailCopied.value = true;
            setTimeout(() => { emailCopied.value = false; }, 2000);
        } else {
            phoneCopied.value = true;
            setTimeout(() => { phoneCopied.value = false; }, 2000);
        }
    } catch (e) {
        console.error('Failed to copy', e);
    }
}

function handleKeydown(e) {
    if (e.key === 'Escape' && isHelpModalOpen.value) {
        isHelpModalOpen.value = false;
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<style scoped>
.app-footer {
    min-height: 44px;
    padding: 5.25px 15px !important;
}

.truckbill-footer-content {
    font-size: 0.8125rem;
    color: #475569;
    line-height: 1.45;
}

.footer-dash {
    color: #475569 !important;
    user-select: none;
}

.footer-separator {
    color: #cbd5e1 !important;
    user-select: none;
    margin: 0 0.5rem;
}

.company-link:hover {
    opacity: 0.9;
}

.phone-link:hover {
    text-decoration: underline !important;
}

/* Ensure mobile footer 3 lines are balanced, proportionate and clean */
@media (max-width: 767.98px) {
    .app-footer {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .app-footer > div {
        gap: 0.35rem !important;
    }

    /* Line 1: Brand */
    .footer-brand {
        font-size: clamp(0.72rem, 2.9vw, 0.78rem);
        line-height: 1.25;
    }

    /* Line 2: Links nav */
    .app-footer nav a {
        font-size: clamp(0.62rem, 2.4vw, 0.7rem);
        padding: 0 0.12rem !important;
    }

    .app-footer nav span {
        margin: 0 0.15rem !important;
    }

    /* Line 3: Copyright & Version */
    .footer-meta {
        font-size: clamp(0.68rem, 2.6vw, 0.74rem);
        line-height: 1.25;
    }

    .footer-version-pill {
        font-size: 0.6rem !important;
        padding: 0.1rem 0.38rem !important;
    }
}

/* Tablet / Middle screen (768px to 1199.98px) - 1 line with clean spacing between the 3 sections */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .app-footer {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        max-width: 100% !important;
        overflow-x: clip !important;
    }

    .app-footer > div {
        gap: 0.25rem 0.5rem !important;
    }

    .footer-brand {
        font-size: 0.68rem !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }

    .footer-brand .company-link {
        font-size: 0.68rem !important;
    }

    .app-footer nav {
        flex-shrink: 1 !important;
        margin: 0 auto !important;
    }

    .app-footer nav a {
        font-size: 0.66rem !important;
        padding: 0 0.12rem !important;
        white-space: nowrap !important;
    }

    .app-footer nav span {
        margin: 0 0.12rem !important;
        font-size: 0.66rem !important;
    }

    .footer-meta {
        font-size: 0.68rem !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
        gap: 0.25rem !important;
    }

    .footer-version-pill {
        font-size: 0.58rem !important;
        padding: 0.08rem 0.35rem !important;
    }
}
</style>
