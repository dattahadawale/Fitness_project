
/* Submits the payment modal as multipart/form-data and shows the REAL server response.
   Required markup inside your modal:
   <form id="paymentProofForm" enctype="multipart/form-data" novalidate>
     <?= csrf_input() ?>   
     <input type="hidden" name="plan_id" value="">     <- set to the clicked plan's id
     <input type="text" name="utr" maxlength="30" required>
     <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" required>
     <div id="paymentProofError" role="alert" style="display:none;color:#ff6b6b"></div>
     <button type="submit" id="paymentProofBtn">Submit Payment for Verification</button>
   </form>
   Do NOT set a Content-Type header yourself: the browser must add the multipart boundary. */
(function () {
  const form = document.getElementById('paymentProofForm');
  if (!form) return;
  const btn = document.getElementById('paymentProofBtn');
  const err = document.getElementById('paymentProofError');
  let busy = false;

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    if (busy) return;                       // blocks double clicks
    err.style.display = 'none';
    const file = form.screenshot.files[0];
    if (!form.utr.value.trim()) return show('Enter your UPI transaction ID / UTR number.');
    if (!file) return show('Attach your payment screenshot.');
    if (file.size > 5 * 1024 * 1024) return show('Screenshot must be 5 MB or smaller.');

    busy = true; btn.disabled = true;
    const label = btn.textContent; btn.textContent = 'Submitting...';
    try {
      const res = await fetch(form.dataset.action || 'actions/payment-proof-actions.php', {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch (e) { throw new Error('Server returned an unexpected response (HTTP ' + res.status + '). Check the PHP error log.'); }
      if (!data.ok) throw new Error(data.error || 'Submission failed.');
      window.location.href = data.redirect;          // only on confirmed save
    } catch (e) {
      show(e.message);
      busy = false; btn.disabled = false; btn.textContent = label;   // form values are kept
    }
  });
  function show(msg) { err.textContent = msg; err.style.display = 'block'; }
})();

