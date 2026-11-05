/**
 * APEXFITNESS – UPI / PhonePe QR Payment Flow
 *
 * Handles the member-facing payment modal:
 *   1. "Pay Now with PhonePe" buttons (data-upi-pay) open the modal directly.
 *   2. window.UPI_AUTO_OPEN (set by includes/upi-pay-modal.php after a POST) also opens it.
 *   3. A 30-second MM:SS countdown animates.  When it reaches 00:00 the proof
 *      form unlocks so the member can enter their UTR and upload the screenshot.
 *   4. If the member clicks the close button before paying they can reopen later
 *      and the countdown restarts safely.
 *   5. Clicking "Restart Timer" resets the countdown without creating a duplicate record.
 *
 * IMPORTANT:
 *   - The timer is a UI convenience only.
 *   - Timer expiry does NOT cancel or confirm any real payment.
 *   - Membership activation happens only after admin manual verification.
 */
(function () {
    'use strict';

    const SECONDS = 30;

    // ── DOM refs ──────────────────────────────────────────────────────────────
    let modal, dotsWrap, countdownDisplay, waitText, qrArea, expiredArea, fieldsEl, form,
        planIdInput, planNameEl, planAmountEl, fileInput, fileNameEl;

    let timer   = null;
    let elapsed = 0;

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        modal           = document.getElementById('upiPayModal');
        dotsWrap        = document.getElementById('upiPayDots');
        countdownDisplay= document.getElementById('upiCountdownDisplay');
        waitText        = document.getElementById('upiPayWaitText');
        qrArea          = document.getElementById('upiQrArea');
        expiredArea     = document.getElementById('upiTimerExpired');
        fieldsEl        = document.getElementById('upiProofFields');
        form            = document.getElementById('upiProofForm');
        planIdInput     = document.getElementById('upiPayPlanId');
        planNameEl      = document.getElementById('upiPayPlanName');
        planAmountEl    = document.getElementById('upiPayPlanAmount');
        fileInput       = document.getElementById('upiScreenshot');
        fileNameEl      = document.getElementById('upiFileName');

        if (!modal) return;  // modal not present on this page

        // Build 30 dot circles
        if (dotsWrap) {
            for (let i = 0; i < SECONDS; i++) {
                const dot = document.createElement('span');
                dot.className = 'upi-pay-dot';
                dotsWrap.appendChild(dot);
            }
        }

        // File-name preview
        if (fileInput && fileNameEl) {
            fileInput.addEventListener('change', () => {
                const f = fileInput.files[0];
                if (f) {
                    fileNameEl.textContent = '✓ ' + f.name + ' (' + (f.size / 1024 / 1024).toFixed(2) + ' MB)';
                    fileNameEl.style.color = 'var(--success)';
                    const label = document.getElementById('upiFileLabel');
                    if (label) label.style.borderColor = 'var(--success)';
                } else {
                    fileNameEl.textContent = 'Tap to select screenshot (JPG, PNG or WEBP, max 5 MB)';
                    fileNameEl.style.color = '';
                }
            });
        }

        // Pay Now buttons with data-upi-pay attribute
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-upi-pay]');
            if (!btn) return;
            e.preventDefault();
            openPayModal(
                btn.dataset.planId   || '',
                btn.dataset.planName || '',
                btn.dataset.planPrice || ''
            );
        });

        // Reset when modal is closed
        new MutationObserver(() => {
            if (modal && !modal.classList.contains('show')) {
                stopAndReset();
            }
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });

        // Block form submit while timer is running or fieldset still disabled
        if (form) {
            form.addEventListener('submit', async (e) => {
                if (timer !== null || (fieldsEl && fieldsEl.disabled)) {
                    e.preventDefault();
                    alert('Please wait for the countdown to finish before submitting.');
                    return;
                }

                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Submitting…';
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { Accept: 'application/json' }
                    });
                    let result;
                    try {
                        result = await response.json();
                    } catch {
                        throw new Error('The server returned an invalid response. Please refresh and try again.');
                    }

                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || `Payment submission failed (${response.status}).`);
                    }
                    if (!result.redirect) {
                        throw new Error('Payment was submitted, but no receipt page was provided. Open Payment Receipts to check its status.');
                    }

                    window.location.assign(result.redirect);
                } catch (error) {
                    alert(error instanceof Error ? error.message : 'Could not submit payment. Please try again.');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                }
            });
        }

        // Auto-open if set by PHP (after existing Pay Now form POST)
        if (window.UPI_AUTO_OPEN) {
            const a = window.UPI_AUTO_OPEN;
            openPayModal(String(a.planId), String(a.planName), String(a.planPrice));
        }
    });

    // ── Public API (called by HTML onclick) ───────────────────────────────────
    window.restartUpiPayment = function () {
        if (expiredArea) expiredArea.style.display = 'none';
        if (qrArea)      qrArea.style.display      = '';
        startTimer();
    };

    // ── Internal helpers ──────────────────────────────────────────────────────

    function openPayModal(planId, planName, planPrice) {
        if (planIdInput)   planIdInput.value       = planId;
        if (planNameEl)    planNameEl.textContent   = planName;
        if (planAmountEl)  planAmountEl.textContent = planPrice;
        if (form)          form.reset();
        if (fileNameEl)    fileNameEl.textContent   = 'Tap to select screenshot (JPG, PNG or WEBP, max 5 MB)';
        if (fileNameEl)    fileNameEl.style.color   = '';
        const fileLabel = document.getElementById('upiFileLabel');
        if (fileLabel)  fileLabel.style.borderColor = '';

        // Reset plan ID hidden field after form.reset() cleared it
        if (planIdInput)   planIdInput.value = planId;

        openModal('upiPayModal');
        startTimer();
    }

    function padTwo(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function renderClock(secondsLeft) {
        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        if (countdownDisplay) countdownDisplay.textContent = padTwo(mins) + ':' + padTwo(secs);
    }

    function startTimer() {
        stopAndReset();

        // Show QR area, hide expired
        if (qrArea)     qrArea.style.display     = '';
        if (expiredArea) expiredArea.style.display = 'none';

        // Lock the proof fieldset
        if (fieldsEl) fieldsEl.disabled = true;

        renderClock(SECONDS);
        if (waitText) waitText.textContent = 'Scan the QR code and pay. Proof form unlocks in ' + SECONDS + 's.';

        // Animate countdown
        timer = setInterval(() => {
            elapsed++;
            const left = SECONDS - elapsed;

            // Light up dots
            const dots = dotsWrap ? dotsWrap.querySelectorAll('.upi-pay-dot') : [];
            if (dots[elapsed - 1]) dots[elapsed - 1].classList.add('on');

            if (left > 0) {
                renderClock(left);
                if (waitText) waitText.textContent = 'Waiting for payment… ' + left + 's remaining.';
            } else {
                // Countdown finished
                clearInterval(timer);
                timer = null;

                renderClock(0);
                if (dotsWrap) dotsWrap.classList.add('done');

                // Show expired state — proof form stays locked
                // (We do NOT auto-unlock here; expired means member must restart or scroll down)
                if (qrArea)      qrArea.style.display      = 'none';
                if (expiredArea) expiredArea.style.display  = '';
                if (waitText)    waitText.textContent       = '';

                // Unlock proof fieldset — member can now submit their details
                if (fieldsEl) fieldsEl.disabled = false;
            }
        }, 1000);
    }

    function stopAndReset() {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
        elapsed = 0;

        if (dotsWrap) {
            dotsWrap.classList.remove('done');
            dotsWrap.querySelectorAll('.upi-pay-dot').forEach(d => d.classList.remove('on'));
        }
        renderClock(SECONDS);
        if (waitText)    waitText.textContent    = 'Waiting…';
        if (fieldsEl)    fieldsEl.disabled       = true;
        if (qrArea)      qrArea.style.display    = '';
        if (expiredArea) expiredArea.style.display = 'none';
    }

})();

// ─────────────────────────────────────────────────────────────────────────────
//  Admin "Record Payment" modal  –  UPI 30-second dot timer (separate widget)
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const SECS = 30;
    const LOCK = true;

    const modal   = document.getElementById('recordPaymentModal');
    const form    = document.getElementById('recordPaymentForm');
    const method  = document.getElementById('pay_method');
    const saveBtn = document.getElementById('savePaymentBtn');
    const box     = document.getElementById('upiWait');
    const wrap    = document.getElementById('upiWaitDots');
    const text    = document.getElementById('upiWaitText');
    if (!modal || !form || !method || !box || !wrap || !text) return;

    let t = null, n = 0;

    for (let i = 0; i < SECS; i++) {
        const d = document.createElement('span');
        d.className = 'upi-dot';
        wrap.appendChild(d);
    }
    const dots = wrap.querySelectorAll('.upi-dot');

    function unlock()  { if (saveBtn && LOCK) { saveBtn.disabled = false; saveBtn.style.opacity = ''; } }
    function stop()    { if (t) { clearInterval(t); t = null; } }
    function reset()   {
        stop(); n = 0; wrap.classList.remove('done');
        dots.forEach(d => d.classList.remove('on'));
        box.style.display = 'none'; unlock();
    }
    function start()   {
        reset(); box.style.display = 'block';
        if (saveBtn && LOCK) { saveBtn.disabled = true; saveBtn.style.opacity = '.5'; }
        text.textContent = 'Waiting for UPI payment… ' + SECS + 's';
        t = setInterval(() => {
            n++;
            if (dots[n - 1]) dots[n - 1].classList.add('on');
            const left = SECS - n;
            if (left > 0) {
                text.textContent = 'Waiting for UPI payment… ' + left + 's';
            } else {
                stop(); wrap.classList.add('done');
                text.textContent = 'Check your PhonePe / bank app to confirm funds arrived, then save.';
                unlock();
            }
        }, 1000);
    }
    function sync() {
        if (modal.classList.contains('show') && method.value === 'UPI') {
            if (!t && n === 0) start();
        } else {
            reset();
        }
    }

    method.addEventListener('change', () => { reset(); sync(); });
    form.addEventListener('reset', reset);
    new MutationObserver(sync).observe(modal, { attributes: true, attributeFilter: ['class'] });
    form.addEventListener('submit', e => { if (t && LOCK) e.preventDefault(); });
    sync();
});
