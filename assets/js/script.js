/**
 * Apex Fitness - Gym Management System
 * Vanilla JavaScript Interactivity & Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('appSidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // 2. Auto-dismiss Flash Alerts
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(() => {
            flashAlert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateX(100%)';
            setTimeout(() => flashAlert.remove(), 400);
        }, 4500);
    }

    // 3. Live Clock in Top Navbar
    const liveClock = document.getElementById('liveClock');
    if (liveClock) {
        function updateClock() {
            const now = new Date();
            liveClock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    document.querySelectorAll('form[data-loading]').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = form.getAttribute('data-loading');
        });
    });

    document.querySelectorAll('select option[data-upi-qr-select]').forEach(option => {
        const select = option.closest('select');
        const panel = document.getElementById(option.dataset.upiQrSelect);
        if (!select || !panel) return;

        const updateUpiQr = () => {
            panel.hidden = select.value !== option.value;
        };

        select.addEventListener('change', updateUpiQr);
        updateUpiQr();
    });

    // 4. Modal Backdrop Click and ESC key
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('show');
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.show').forEach(m => m.classList.remove('show'));
        }
    });

    // 5. Client-Side Table Search Filtering
    const tableSearchInputs = document.querySelectorAll('[data-table-search]');
    tableSearchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('input', () => {
            const query = input.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // 6. Dynamic Membership Expiry Date Calculator on Plan Selection
    const planSelect = document.getElementById('planSelect');
    const startDateInput = document.getElementById('startDateInput');
    const expiryDateInput = document.getElementById('expiryDateInput');
    const amountInput = document.getElementById('amountInput');

    function updateMembershipDates() {
        if (!planSelect || !startDateInput || !expiryDateInput) return;
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const durationDays = parseInt(selectedOption.getAttribute('data-duration') || '30', 10);
        const price = selectedOption.getAttribute('data-price');
        
        if (amountInput && price) {
            amountInput.value = price;
        }

        const startDateVal = startDateInput.value ? new Date(startDateInput.value) : new Date();
        const expiryDate = new Date(startDateVal);
        expiryDate.setDate(expiryDate.getDate() + durationDays);

        // Format to YYYY-MM-DD
        const year = expiryDate.getFullYear();
        const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
        const day = String(expiryDate.getDate()).padStart(2, '0');
        expiryDateInput.value = `${year}-${month}-${day}`;
    }

    if (planSelect) {
        planSelect.addEventListener('change', updateMembershipDates);
    }
    if (startDateInput) {
        startDateInput.addEventListener('change', updateMembershipDates);
    }
});

// Modal Functions exposed globally
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea');
        if (firstInput) firstInput.focus();
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// Global Delete Confirmation
function confirmDelete(message, formOrUrl) {
    if (confirm(message || 'Are you sure you want to delete this record? This action cannot be undone.')) {
        if (typeof formOrUrl === 'string') {
            window.location.href = formOrUrl;
        } else if (formOrUrl && formOrUrl.submit) {
            formOrUrl.submit();
        }
    }
    return false;
}

// Dynamic Workout Exercise Row Builder
function addExerciseRow() {
    const container = document.getElementById('exerciseRowsContainer');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'exercise-row';
    row.innerHTML = `
        <select name="exercise_day[]" class="form-control" required>
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
            <option value="Friday">Friday</option>
            <option value="Saturday">Saturday</option>
            <option value="Sunday">Sunday</option>
        </select>
        <input type="text" name="exercise_name[]" class="form-control" placeholder="Exercise Name (e.g. Bench Press)" required>
        <input type="number" name="exercise_sets[]" class="form-control" value="3" min="1" max="20" title="Sets" required>
        <input type="text" name="exercise_reps[]" class="form-control" value="10-12" placeholder="Reps (e.g. 10-12)" required>
        <input type="number" name="exercise_rest[]" class="form-control" value="60" min="0" step="15" title="Rest (sec)" required>
        <input type="text" name="exercise_instructions[]" class="form-control" placeholder="Key cue or instructions">
        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.exercise-row').remove();" title="Remove">&times;</button>
    `;
    container.appendChild(row);
}

// UPI 30-second wait timer (30 circles) - Record Payment modal (admin/payments.php)
document.addEventListener('DOMContentLoaded', () => {
    const SECONDS = 30;            // wait time / number of circles
    const LOCK_SAVE_BUTTON = true; // false = animation only, Save never locked

    const modal    = document.getElementById('recordPaymentModal');
    const form     = document.getElementById('recordPaymentForm');
    const method   = document.getElementById('pay_method');
    const saveBtn  = document.getElementById('savePaymentBtn');
    const box      = document.getElementById('upiWait');
    const dotsWrap = document.getElementById('upiWaitDots');
    const text     = document.getElementById('upiWaitText');
    if (!modal || !form || !method || !box || !dotsWrap || !text) return; // not on the payments page

    let timer = null;
    let elapsed = 0;

    for (let i = 0; i < SECONDS; i++) {
        const dot = document.createElement('span');
        dot.className = 'upi-dot';
        dotsWrap.appendChild(dot);
    }
    const dots = dotsWrap.querySelectorAll('.upi-dot');

    function unlockSave() {
        if (saveBtn && LOCK_SAVE_BUTTON) {
            saveBtn.disabled = false;
            saveBtn.style.opacity = '';
        }
    }

    function stopTimer() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function resetTimer() {
        stopTimer();
        elapsed = 0;
        dotsWrap.classList.remove('done');
        dots.forEach(dot => dot.classList.remove('on'));
        box.style.display = 'none';
        unlockSave();
    }

    function startTimer() {
        resetTimer();
        box.style.display = 'block';
        if (saveBtn && LOCK_SAVE_BUTTON) {
            saveBtn.disabled = true;
            saveBtn.style.opacity = '.5';
        }
        text.textContent = 'Waiting for payment… ' + SECONDS + 's';
        timer = setInterval(() => {
            elapsed++;
            if (dots[elapsed - 1]) dots[elapsed - 1].classList.add('on');
            const left = SECONDS - elapsed;
            if (left > 0) {
                text.textContent = 'Waiting for payment… ' + left + 's';
            } else {
                stopTimer();
                dotsWrap.classList.add('done');
                text.textContent = 'Check your bank/UPI app to confirm the money arrived, then save.';
                unlockSave();
            }
        }, 1000);
    }

    function syncTimer() {
        if (modal.classList.contains('show') && method.value === 'UPI') {
            // Don't restart a countdown that is already running
            if (!timer && elapsed === 0) startTimer();
        } else {
            resetTimer();
        }
    }

    method.addEventListener('change', () => { resetTimer(); syncTimer(); });
    form.addEventListener('reset', resetTimer);

    // Start when the modal opens, reset when it closes
    new MutationObserver(syncTimer).observe(modal, { attributes: true, attributeFilter: ['class'] });

    // Safety: never submit while the countdown is still running
    form.addEventListener('submit', (e) => {
        if (timer && LOCK_SAVE_BUTTON) e.preventDefault();
    });

    syncTimer();
});