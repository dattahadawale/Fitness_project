<?php
/**
 * Membership Plans Management Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// Fetch plans along with active subscriber counts
$plans = $db->query("
    SELECT mp.*, 
           COUNT(DISTINCT ms.id) AS total_subscriptions,
           SUM(CASE WHEN ms.status = 'active' AND ms.expiry_date >= CURRENT_DATE THEN 1 ELSE 0 END) AS active_subscribers
    FROM membership_plans mp
    LEFT JOIN memberships ms ON mp.id = ms.plan_id
    GROUP BY mp.id
    ORDER BY mp.price ASC
")->fetchAll();

$pageTitle = 'Membership Plans';
$navTitle = 'Manage Membership Plans';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 4px;">Membership Packages</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Configure subscription tiers, durations, and pricing.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addPlanModal')">
                        + Create New Plan
                    </button>
                </div>
            </div>

            <div class="plans-grid">
                <?php foreach ($plans as $p): ?>
                    <div class="pricing-card" style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h3 class="plan-name" style="margin-bottom: 4px;"><?= e($p['plan_name']) ?></h3>
                            <span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                        </div>

                        <div class="plan-price" style="margin: 12px 0 8px;">
                            <?= format_currency($p['price']) ?>
                            <span>/ <?= (int)$p['duration_days'] ?> Days</span>
                        </div>

                        <p style="color: var(--text-muted); font-size: 13px; min-height: 48px; line-height: 1.5;">
                            <?= e($p['description'] ?: 'Complete gym access package.') ?>
                        </p>

                        <div style="background: var(--bg-main); padding: 12px 14px; border-radius: var(--radius-sm); margin: 16px 0; font-size: 13px; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: var(--text-dim);">Active Subscribers:</span>
                                <strong style="color: var(--success);"><?= (int)$p['active_subscribers'] ?> members</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-dim);">Lifetime Purchases:</span>
                                <strong style="color: #ffffff;"><?= (int)$p['total_subscriptions'] ?></strong>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; margin-top: auto;">
                            <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick='editPlan(<?= json_encode($p) ?>)'>
                                Edit Plan
                            </button>
                            <form action="<?= base_url('actions/membership-actions.php') ?>" method="POST" onsubmit="return confirm('Delete or deactivate plan <?= e($p['plan_name']) ?>?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Plan">&times;</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<!-- ================= ADD PLAN MODAL ================= -->
<div class="modal-backdrop" id="addPlanModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Membership Plan</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addPlanModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/membership-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_plan">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_plan_name">Plan Title <span class="required">*</span></label>
                    <input type="text" id="add_plan_name" name="plan_name" class="form-control" placeholder="e.g. Semi-Annual VIP" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_duration">Duration (Days) <span class="required">*</span></label>
                        <input type="number" id="add_duration" name="duration_days" class="form-control" value="30" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_price">Price (INR) <span class="required">*</span></label>
                        <input type="number" step="0.01" id="add_price" name="price" class="form-control" value="49.99" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_p_desc">Plan Description & Benefits</label>
                    <textarea id="add_p_desc" name="description" class="form-control" rows="3" placeholder="List key member privileges and features included..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT PLAN MODAL ================= -->
<div class="modal-backdrop" id="editPlanModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Membership Plan</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editPlanModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/membership-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit_plan">
            <input type="hidden" id="edit_plan_id" name="plan_id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_plan_name">Plan Title <span class="required">*</span></label>
                    <input type="text" id="edit_plan_name" name="plan_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_duration">Duration (Days) <span class="required">*</span></label>
                        <input type="number" id="edit_duration" name="duration_days" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_price">Price (INR) <span class="required">*</span></label>
                        <input type="number" step="0.01" id="edit_price" name="price" class="form-control" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_plan_status">Status</label>
                    <select id="edit_plan_status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_p_desc">Plan Description</label>
                    <textarea id="edit_p_desc" name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPlan(p) {
    document.getElementById('edit_plan_id').value = p.id;
    document.getElementById('edit_plan_name').value = p.plan_name;
    document.getElementById('edit_duration').value = p.duration_days;
    document.getElementById('edit_price').value = p.price;
    document.getElementById('edit_plan_status').value = p.status;
    document.getElementById('edit_p_desc').value = p.description || '';
    openModal('editPlanModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
