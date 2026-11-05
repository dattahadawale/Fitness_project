<?php
/**
 * Member Profile Management
 * Gym Management System
 */

require_once __DIR__ . '/../includes/member_auth.php';

$db = getDB();
$userId = $_SESSION['user']['id'];

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token mismatch. Please try again.');
        header('Location: ' . base_url('member/profile.php'));
        exit;
    }

    $phone = trim($_POST['phone'] ?? '');
    $emergency = trim($_POST['emergency_contact'] ?? '');
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE members SET 
                phone = :phone, 
                emergency_contact = :emergency, 
                blood_group = :blood, 
                address = :address
            WHERE user_id = :uid
        ");
        $stmt->execute([
            ':phone'     => $phone,
            ':emergency' => $emergency,
            ':blood'     => $bloodGroup,
            ':address'   => $address,
            ':uid'       => $userId
        ]);
        set_flash('success', 'Profile contact details updated successfully.');
    } catch (Exception $e) {
        set_flash('error', 'Failed to update profile: ' . $e->getMessage());
    }

    header('Location: ' . base_url('member/profile.php'));
    exit;
}

// Fetch member data
$mStmt = $db->prepare("SELECT * FROM members WHERE user_id = :uid LIMIT 1");
$mStmt->execute([':uid' => $userId]);
$member = $mStmt->fetch();

$pageTitle = 'My Profile';
$navTitle = 'Personal Profile Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                <!-- Contact Details Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-users"></use></svg>
                            Personal Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?= e($member['full_name']) ?>" disabled style="background: var(--bg-main);">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Member Code</label>
                                    <input type="text" class="form-control" value="<?= e($member['member_code']) ?>" disabled style="background: var(--bg-main); font-family: monospace; color: var(--primary);">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Registered Email</label>
                                    <input type="email" class="form-control" value="<?= e($member['email']) ?>" disabled style="background: var(--bg-main);">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                                    <input type="text" id="phone" name="phone" class="form-control" value="<?= e($member['phone']) ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="blood_group">Blood Group</label>
                                    <input type="text" id="blood_group" name="blood_group" class="form-control" value="<?= e($member['blood_group']) ?>" placeholder="e.g. O+, A+, B-">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="emergency_contact">Emergency Contact</label>
                                    <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" value="<?= e($member['emergency_contact']) ?>" placeholder="Name & Phone">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="address">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?= e($member['address']) ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                                Save Contact Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Password Change Form -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            Security & Password
                        </h2>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('actions/auth-actions.php') ?>" method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                                <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                            </div>

                            <button type="submit" class="btn btn-secondary" style="margin-top: 10px;">
                                Update Password &rarr;
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
