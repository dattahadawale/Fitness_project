<?php
/**
 * PhonePe QR payment popup (member portal)
 *
 * Include ONCE on member/dashboard.php, just before the footer include:
 *   <?php include __DIR__ . '/../includes/upi-pay-modal.php'; ?>
 *
 * It opens in two ways:
 *   1. a button with data-upi-pay (data-plan-id / data-plan-name / data-plan-price), or
 *   2. automatically after the existing Pay Now form posts to actions/phonepe-actions.php
 *      (QR mode), which stores the chosen plan id in $_SESSION['open_upi_qr'].
 *
 * Needs: includes/functions.php helpers base_url(), e(), csrf_input(), format_currency()
 */

// Plan chosen by the existing Pay Now form (read once, then cleared)
$upiAutoOpen = null;
if (!empty($_SESSION['open_upi_qr'])) {
  $autoPlanId = (int) $_SESSION['open_upi_qr'];
  unset($_SESSION['open_upi_qr']);
  try {
    $autoStmt = getDB()->prepare("SELECT id, plan_name, price FROM membership_plans WHERE id = :id AND status = 'active'");
    $autoStmt->execute([':id' => $autoPlanId]);
    if ($autoPlan = $autoStmt->fetch()) {
      $upiAutoOpen = [
        'planId' => (int) $autoPlan['id'],
        'planName' => (string) $autoPlan['plan_name'],
        'planPrice' => html_entity_decode(strip_tags((string) format_currency($autoPlan['price'])), ENT_QUOTES, 'UTF-8'),
      ];
    }
  } catch (Throwable $e) {
    error_log('upi-pay-modal auto open: ' . $e->getMessage());
  }
}
?>
<style>
  #upiPayDots {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    max-width: 420px;
    margin: 0 auto;
  }

  #upiPayDots .upi-pay-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    background: transparent;
    transition: background .25s, border-color .25s, transform .25s;
  }

  #upiPayDots .upi-pay-dot.on {
    background: var(--primary);
    border-color: var(--primary);
    transform: scale(1.1);
  }

  #upiPayDots.done .upi-pay-dot.on {
    background: var(--success);
    border-color: var(--success);
  }

  .upi-pay-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    background: var(--primary-light);
    border: 1px solid var(--primary);
    border-radius: var(--radius-sm);
  }

  .upi-pay-summary strong {
    color: #fff;
  }

  .upi-pay-amount {
    font-size: 22px;
    font-weight: 800;
    color: var(--primary);
  }

  .upi-proof-fields {
    border: 0;
    min-width: 0;
    padding: 0;
    margin: 0;
  }

  .upi-proof-fields:disabled {
    opacity: .45;
  }
</style>

<div class="modal-backdrop" id="upiPayModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Pay with PhonePe</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('upiPayModal')"
        aria-label="Close">&times;</button>
    </div>

    <div class="modal-body">
      <div class="upi-pay-summary">
        <div>
          <div style="font-size:12px; color:var(--text-muted);">Selected plan</div>
          <strong id="upiPayPlanName">-</strong>
        </div>
        <div class="upi-pay-amount" id="upiPayPlanAmount">-</div>
      </div>

      <!-- Step 1: scan the QR -->
      <div class="upi-payment-panel">
        <img src="<?= e(base_url('assets/images/phonepe-payment-qr.png')) ?>" alt="PhonePe payment QR code">
        <div>
          <strong>1. Scan &amp; pay the exact amount</strong>
          <p>Open PhonePe (or any UPI app), scan this QR and pay the amount shown above. Keep the payment
            screenshot and note the transaction ID.</p>
        </div>
      </div>

      <!-- Step 2: 30-second wait -->
      <div id="upiPayWait" style="text-align:center; margin-bottom:18px;" aria-live="polite">
        <div id="upiPayDots"></div>
        <div id="upiPayWaitText" style="margin-top:10px; font-size:13px; color:var(--text-muted);"></div>
      </div>

      <!-- Step 3: send proof (unlocks after the wait) -->
      <form id="upiProofForm" action="<?= e(base_url('actions/payment-proof-actions.php')) ?>" method="POST"
        enctype="multipart/form-data" data-loading="Sending...">
        <?= csrf_input() ?>
        <input type="hidden" name="plan_id" id="upiPayPlanId" value="">

        <fieldset class="upi-proof-fields" id="upiProofFields" disabled>
          <strong style="display:block; margin-bottom:10px;">2. Send your payment proof</strong>

          <div class="form-group">
            <label class="form-label" for="upiTxnId">Transaction ID / UTR <span class="required">*</span></label>
            <input type="text" id="upiTxnId" name="transaction_id" class="form-control"
              placeholder="e.g. T2410261234567890 or 412345678901" pattern="[A-Za-z0-9]{8,35}" minlength="8"
              maxlength="35" required autocomplete="off">
            <span class="form-hint">Letters and numbers only, 8-35 characters.</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="upiScreenshot">Payment screenshot <span class="required">*</span></label>
            <input type="file" id="upiScreenshot" name="screenshot" class="form-control"
              accept="image/jpeg,image/png,image/webp" required>
            <span class="form-hint">JPG, PNG or WEBP, up to 5 MB.</span>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;">Submit Payment Proof &rarr;</button>
        </fieldset>
      </form>

      <p style="margin-top:14px; font-size:12px; color:var(--text-dim);">
        Your membership is activated after our team verifies the payment in the gym's PhonePe account.
      </p>
    </div>
  </div>
</div>

<?php if ($upiAutoOpen): ?>
  <script>
    window.UPI_AUTO_OPEN = <?= json_encode($upiAutoOpen, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
<?php endif; ?>
<script src="<?= e(base_url('assets/js/upi-pay.js')) ?>"></script>