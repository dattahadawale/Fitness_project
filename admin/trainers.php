<?php
/**
 * Trainer Management Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// Fetch all trainers
$trainers = $db->query("SELECT * FROM trainers ORDER BY id DESC")->fetchAll();

$pageTitle = 'Trainer Management';
$navTitle = 'Manage Coaching Staff';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div class="search-input-wrapper" style="max-width: 320px;">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="form-control" placeholder="Quick search trainers..." data-table-search="trainersTable">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addTrainerModal')">
                        + Add New Trainer
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-trainer"></use></svg>
                        All Gym Coaches (<?= count($trainers) ?>)
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="trainersTable">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Specialization</th>
                                <th>Experience</th>
                                <th>Monthly Salary</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trainers)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No trainers registered yet. Click "+ Add New Trainer" to create one.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trainers as $t): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= e($t['name']) ?></div>
                                            <div style="font-size: 12px; color: var(--text-dim);"><?= e($t['email']) ?> &bull; <?= e($t['phone']) ?></div>
                                        </td>
                                        <td><span class="badge badge-orange"><?= e($t['specialization']) ?></span></td>
                                        <td><?= (int)$t['experience_years'] ?> Years</td>
                                        <td style="font-weight: 700; color: var(--success);"><?= format_currency($t['salary']) ?></td>
                                        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
                                        <td style="text-align: right;">
                                            <div class="table-actions" style="justify-content: flex-end;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick='editTrainer(<?= json_encode($t) ?>)' title="Edit Trainer">✏️</button>
                                                <form action="<?= base_url('actions/trainer-actions.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Remove trainer <?= e($t['name']) ?>?');">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="delete_trainer">
                                                    <input type="hidden" name="trainer_id" value="<?= $t['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Trainer">&times;</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= ADD TRAINER MODAL ================= -->
<div class="modal-backdrop" id="addTrainerModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add Certified Trainer</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addTrainerModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/trainer-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_trainer">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_t_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="add_t_name" name="name" class="form-control" placeholder="e.g. Marcus Steel" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_t_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="add_t_email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_t_phone">Phone Number <span class="required">*</span></label>
                        <input type="text" id="add_t_phone" name="phone" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_t_gender">Gender</label>
                        <select id="add_t_gender" name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_t_exp">Experience (Years)</label>
                        <input type="number" id="add_t_exp" name="experience_years" class="form-control" value="3" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_t_salary">Monthly Salary ($)</label>
                        <input type="number" step="0.01" id="add_t_salary" name="salary" class="form-control" value="3500.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_t_spec">Specialization <span class="required">*</span></label>
                    <input type="text" id="add_t_spec" name="specialization" class="form-control" placeholder="e.g. Strength & Conditioning, CrossFit, Yoga" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_t_bio">Coach Bio & Achievements</label>
                    <textarea id="add_t_bio" name="bio" class="form-control" rows="3" placeholder="Background, certifications, coaching style..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTrainerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Trainer</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT TRAINER MODAL ================= -->
<div class="modal-backdrop" id="editTrainerModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Trainer Profile</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editTrainerModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/trainer-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit_trainer">
            <input type="hidden" id="edit_trainer_id" name="trainer_id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_t_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="edit_t_name" name="name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_t_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="edit_t_email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_t_phone">Phone Number <span class="required">*</span></label>
                        <input type="text" id="edit_t_phone" name="phone" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_t_gender">Gender</label>
                        <select id="edit_t_gender" name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_t_exp">Experience (Years)</label>
                        <input type="number" id="edit_t_exp" name="experience_years" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_t_salary">Monthly Salary ($)</label>
                        <input type="number" step="0.01" id="edit_t_salary" name="salary" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_t_spec">Specialization <span class="required">*</span></label>
                        <input type="text" id="edit_t_spec" name="specialization" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_t_status">Status</label>
                        <select id="edit_t_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_t_bio">Coach Bio</label>
                    <textarea id="edit_t_bio" name="bio" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTrainerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Trainer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTrainer(t) {
    document.getElementById('edit_trainer_id').value = t.id;
    document.getElementById('edit_t_name').value = t.name;
    document.getElementById('edit_t_email').value = t.email;
    document.getElementById('edit_t_phone').value = t.phone;
    document.getElementById('edit_t_gender').value = t.gender;
    document.getElementById('edit_t_exp').value = t.experience_years;
    document.getElementById('edit_t_salary').value = t.salary;
    document.getElementById('edit_t_spec').value = t.specialization;
    document.getElementById('edit_t_status').value = t.status;
    document.getElementById('edit_t_bio').value = t.bio || '';
    openModal('editTrainerModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
