<?php
/**
 * UPI / PhonePe QR Payment Modal
 * Included at the bottom of member/dashboard.php
 *
 * Shows the gym's PhonePe QR code, a 30-second MM:SS countdown,
 * and – once the countdown ends – a proof submission form (transaction ID + screenshot).
 *
 * The 30-second timer is a UI convenience only.  It does NOT expire a real
 * payment and does NOT proof that money was received.  After the countdown
 * the proof form simply becomes interactive so the member can submit details.
 * The admin must manually verify funds before activating any membership.
 */

// ── Merchant details: always read from server-side config, never from the browser ──
$upiMerchantId   = (string)(getenv('UPI_MERCHANT_ID')   ?: 'dattatukaramhadavale@upi');
$upiMerchantName = (string)(getenv('UPI_MERCHANT_NAME') ?: 'DATTATUKARAMHADAVALE TUKARAM HADAVALE');

// ── Auto-open after form POST in QR mode ──
//    phonepe-actions.php stores the selected plan id in $_SESSION['open_upi_qr']
$autoOpenPlanId = 0;
$autoOpenPlan   = null;
if (!empty($_SESSION['open_upi_qr'])) {
    $autoOpenPlanId = (int)$_SESSION['open_upi_qr'];
    unset($_SESSION['open_upi_qr']);
    if ($autoOpenPlanId > 0) {
        $db ??= getDB();
        $apStmt = $db->prepare("SELECT id, plan_name, price FROM membership_plans WHERE id = :id AND status = 'active' LIMIT 1");
        $apStmt->execute([':id' => $autoOpenPlanId]);
        $autoOpenPlan = $apStmt->fetch();
    }
}
?>

<!-- ======================================================================
     UPI Pay Modal
     CSS: assets/css/style.css (modal-backdrop, modal-content, etc.)
     JS:  assets/js/Upi_pay.js
     ====================================================================== -->
<div class="modal-backdrop" id="upiPayModal" role="dialog" aria-modal="true" aria-labelledby="upiPayModalTitle">
    <div class="modal-content" style="max-width: 520px; width: 95%;">

        <!-- ── Modal Header ── -->
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
            <div style="display:flex; align-items:center; gap:10px;">
                <!-- PhonePe purple logo pill -->
                <span style="
                    background:#5f259f; color:#fff; font-weight:800; font-size:12px;
                    padding:4px 10px; border-radius:20px; letter-spacing:0.5px;">
                    &#x2117; PhonePe
                </span>
                <h3 id="upiPayModalTitle" style="margin:0; font-size:16px; color:#fff;">
                    Pay with PhonePe / UPI
                </h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('upiPayModal')" aria-label="Close">&times;</button>
        </div>

        <!-- ── Modal Body ── -->
        <div class="modal-body" style="padding:20px 24px;">

            <!-- Plan + Amount banner -->
            <div id="upiPayPlanBanner" style="
                background: var(--bg-input); border:1px solid var(--border-color);
                border-radius:var(--radius-sm); padding:12px 16px;
                display:flex; justify-content:space-between; align-items:center;
                margin-bottom:18px;">
                <div>
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-dim);">Selected Plan</div>
                    <div id="upiPayPlanName" style="font-weight:700; color:#fff; font-size:15px; margin-top:2px;"></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-dim);">Amount</div>
                    <div id="upiPayPlanAmount" style="font-weight:800; color:var(--primary); font-size:22px; margin-top:2px; font-family:monospace;"></div>
                </div>
            </div>

            <!-- ── QR + Countdown Area ── -->
            <div id="upiQrArea">
                <!-- Instruction -->
                <p style="text-align:center; color:var(--text-muted); font-size:13px; margin-bottom:14px;">
                    Scan this QR code using PhonePe, Google Pay, Paytm, or any UPI app.
                </p>

                <!-- QR Image (PhonePe merchant QR) -->
                <div style="text-align:center; margin-bottom:14px;">
                    <div style="
                        display:inline-block; background:#fff; padding:10px;
                        border-radius:12px; border:3px solid #5f259f;
                        box-shadow:0 4px 20px rgba(95,37,159,0.3);">
                        <img src="<?= base_url('assets/images/phonepe-payment-qr.png') ?>"
                             alt="PhonePe QR Code – scan to pay"
                             style="width:180px; height:180px; display:block; object-fit:contain;">
                    </div>
                    <div style="margin-top:10px;">
                        <div style="font-weight:700; color:#fff; font-size:13px; letter-spacing:0.3px;">
                            <?= e($upiMerchantName) ?>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:3px; font-family:monospace;">
                            UPI: <?= e($upiMerchantId) ?>
                        </div>
                    </div>
                </div>

                <!-- 30-second digital countdown -->
                <div style="text-align:center; margin-bottom:10px;">
                    <div id="upiCountdownDisplay" style="
                        font-size:52px; font-weight:800; font-family:monospace;
                        color:var(--primary); line-height:1; letter-spacing:2px;">
                        00:30
                    </div>
                    <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">
                        Proof form unlocks when timer reaches 00:00
                    </div>
                </div>

                <!-- 30 dot progress bar -->
                <div id="upiPayDots" style="
                    display:flex; flex-wrap:wrap; justify-content:center;
                    gap:5px; margin:10px 0 14px;">
                </div>
                <p id="upiPayWaitText" style="
                    text-align:center; font-size:13px; color:var(--text-muted);
                    min-height:18px; margin-bottom:0;">
                    Waiting…
                </p>
            </div><!-- /#upiQrArea -->

            <!-- ── Timer Expired State ── -->
            <div id="upiTimerExpired" style="display:none; text-align:center; padding:20px 0;">
                <div style="font-size:40px; margin-bottom:10px;">⏱</div>
                <h4 style="color:var(--warning); margin-bottom:8px;">Time expired</h4>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">
                    The 30-second timer has finished. If you have already paid, enter your
                    transaction details below. Otherwise scan the QR again.
                </p>
                <button type="button" class="btn btn-secondary btn-sm" onclick="restartUpiPayment()">
                    ↺ &nbsp;Restart Timer
                </button>
            </div>

            <!-- ── Proof Submission Form (locked until countdown ends) ── -->
            <form id="upiProofForm"
                  action="<?= base_url('payments/Payment_prof_actions.php') ?>"
                  method="POST"
                  enctype="multipart/form-data"
                  style="margin-top:16px;">

                <?= csrf_input() ?>
                <input type="hidden" id="upiPayPlanId" name="plan_id" value="">

                <fieldset id="upiProofFields" disabled style="border:none; padding:0; margin:0;">
                    <div style="
                        border-top:1px solid var(--border-color); padding-top:16px;
                        display:flex; flex-direction:column; gap:14px;">

                        <div style="
                            background:var(--success-bg); border:1px solid var(--success);
                            border-radius:var(--radius-sm); padding:10px 14px;
                            font-size:13px; color:var(--success);">
                            ✓ &nbsp;Paid? Enter your transaction details below and submit for verification.
                        </div>

                        <!-- UPI Transaction ID / UTR -->
                        <div>
                            <label for="upiTxnId" style="
                                display:block; font-size:12px; font-weight:600;
                                color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">
                                UPI Transaction ID / UTR Number <span style="color:var(--danger);">*</span>
                            </label>
                            <input type="text"
                                   id="upiTxnId"
                                   name="utr"
                                   class="form-control"
                                   placeholder="e.g. 318570196321 or T2409231234XXXXXXXXX"
                                   pattern="[A-Za-z0-9]{10,30}"
                                   minlength="10"
                                   maxlength="30"
                                   required
                                   autocomplete="off"
                                   style="font-family:monospace; letter-spacing:0.5px;">
                            <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">
                                Find this in your UPI app → Transaction History → check the payment.
                            </div>
                        </div>

                        <!-- Screenshot Upload -->
                        <div>
                            <label style="
                                display:block; font-size:12px; font-weight:600;
                                color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">
                                Payment Screenshot <span style="color:var(--danger);">*</span>
                            </label>
                            <label id="upiFileLabel" for="upiScreenshot" style="
                                display:flex; align-items:center; gap:10px; cursor:pointer;
                                background:var(--bg-input); border:2px dashed var(--border-color);
                                border-radius:var(--radius-sm); padding:12px 14px;
                                transition:border-color 0.2s;">
                                <span style="font-size:22px;">📷</span>
                                <div>
                                    <div id="upiFileName" style="font-size:13px; color:var(--text-muted);">
                                        Tap to select screenshot (JPG, PNG or WEBP, max 5 MB)
                                    </div>
                                    <div style="font-size:11px; color:var(--text-dim); margin-top:2px;">
                                        Take a screenshot of the "Payment Successful" screen in your UPI app.
                                    </div>
                                </div>
                            </label>
                            <input type="file"
                                   id="upiScreenshot"
                                   name="screenshot"
                                   accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                   required
                                   style="display:none;">
                        </div>

                        <!-- Submit button -->
                        <button type="submit" id="upiSubmitBtn" class="btn btn-primary"
                                style="width:100%; font-size:15px; padding:12px 0; font-weight:700;">
                            Submit Payment for Verification
                        </button>

                        <p style="text-align:center; font-size:11px; color:var(--text-dim);">
                            Your membership activates only after the gym administrator
                            verifies the transaction in PhonePe.
                        </p>
                    </div>
                </fieldset>
            </form>

        </div><!-- /.modal-body -->
    </div>
</div>

<?php if ($autoOpenPlan): ?>
<script>
/* Auto-open modal after QR-mode Pay Now form submission */
window.UPI_AUTO_OPEN = {
    planId    : <?= (int)$autoOpenPlan['id'] ?>,
    planName  : <?= json_encode($autoOpenPlan['plan_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    planPrice : '₹<?= number_format((float)$autoOpenPlan['price'], 2) ?>'
};
</script>
<?php endif; ?>
