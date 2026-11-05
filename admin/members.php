<?php
/**
 * Member Management Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Filtering & Search Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$planFilter = (int)($_GET['plan_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Base query builder
$whereClauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(m.full_name LIKE :s1 OR m.email LIKE :s2 OR m.phone LIKE :s3 OR m.member_code LIKE :s4)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
}
if (!empty($statusFilter)) {
    $whereClauses[] = "m.status = :status";
    $params[':status'] = $statusFilter;
}
if ($planFilter > 0) {
    $whereClauses[] = "ms.plan_id = :plan_id";
    $params[':plan_id'] = $planFilter;
}

$whereSQL = implode(" AND ", $whereClauses);

// Count total records for pagination
$countStmt = $db->prepare("
    SELECT COUNT(m.id)
    FROM members m
    LEFT JOIN (
        SELECT member_id,
               COALESCE(
                   MAX(CASE WHEN status = 'active' AND expiry_date >= CURRENT_DATE THEN id END),
                   MAX(id)
               ) AS latest_id
        FROM memberships
        GROUP BY member_id
    ) latest_ms ON m.id = latest_ms.member_id
    LEFT JOIN memberships ms ON ms.id = latest_ms.latest_id
    LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
    WHERE {$whereSQL}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch members with latest/active membership details
$query = "
    SELECT m.*, 
           ms.id AS membership_id, ms.start_date, ms.expiry_date, ms.status AS membership_status,
           mp.plan_name, mp.price AS plan_price,
           DATEDIFF(ms.expiry_date, CURRENT_DATE) AS days_remaining
    FROM members m
    LEFT JOIN (
        SELECT member_id,
               COALESCE(
                   MAX(CASE WHEN status = 'active' AND expiry_date >= CURRENT_DATE THEN id END),
                   MAX(id)
               ) AS latest_id
        FROM memberships
        GROUP BY member_id
    ) latest_ms ON m.id = latest_ms.member_id
    LEFT JOIN memberships ms ON ms.id = latest_ms.latest_id
    LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
    WHERE {$whereSQL}
    ORDER BY m.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll();

// Fetch plans for dropdowns
$plans = $db->query("SELECT * FROM membership_plans WHERE status = 'active' ORDER BY price ASC")->fetchAll();

$pageTitle = 'Member Management';
$navTitle = 'Manage Gym Members';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Filter & Action Bar -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <form method="GET" class="filter-bar" style="margin-bottom: 0;">
                        <div class="search-input-wrapper">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, code, email, phone..." value="<?= e($search) ?>">
                        </div>

                        <select name="status" class="form-control" style="width: auto; min-width: 130px;">
                            <option value="">All Statuses</option>
                            <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="expired" <?= ($statusFilter === 'expired') ? 'selected' : '' ?>>Expired</option>
                        </select>

                        <select name="plan_id" class="form-control" style="width: auto; min-width: 160px;">
                            <option value="">All Plans</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($planFilter == $p['id']) ? 'selected' : '' ?>>
                                    <?= e($p['plan_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                        <?php if (!empty($search) || !empty($statusFilter) || $planFilter > 0): ?>
                            <a href="<?= base_url('admin/members.php') ?>" class="btn btn-secondary btn-sm" title="Clear filters">&times; Reset</a>
                        <?php endif; ?>

                        <div style="margin-left: auto;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addMemberModal')">
                                + Add New Member
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Members Data Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-users"></use></svg>
                        All Members (<?= number_format($totalRecords) ?>)
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="membersTable">
                        <thead>
                            <tr>
                                <th>Code / Name</th>
                                <th>Contact Details</th>
                                <th>Active Plan</th>
                                <th>Plan Expiry</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No members found matching your search criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $m): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= e($m['full_name']) ?></div>
                                            <div style="font-size: 11px; font-family: monospace; color: var(--primary);">
                                                <?= e($m['member_code']) ?> &bull; <?= e($m['gender']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 13px; color: var(--text-main);"><?= e($m['phone']) ?></div>
                                            <div style="font-size: 12px; color: var(--text-dim);"><?= e($m['email']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($m['plan_name']): ?>
                                                <span class="badge badge-orange"><?= e($m['plan_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No Plan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($m['expiry_date']): ?>
                                                <div><?= format_date($m['expiry_date']) ?></div>
                                                <?php if ((int)$m['days_remaining'] < 0): ?>
                                                    <span style="font-size: 11px; color: var(--danger);">Expired <?= abs((int)$m['days_remaining']) ?>d ago</span>
                                                <?php elseif ((int)$m['days_remaining'] <= 7): ?>
                                                    <span style="font-size: 11px; color: var(--warning); font-weight: bold;"><?= (int)$m['days_remaining'] ?> days left</span>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: var(--success);"><?= (int)$m['days_remaining'] ?> days left</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-dim);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="table-actions" style="justify-content: flex-end;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick='viewMember(<?= json_encode($m) ?>)' title="View Profile">
                                                    👁️
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick='editMember(<?= json_encode($m) ?>)' title="Edit Member">
                                                    ✏️
                                                </button>
                                                <form action="<?= base_url('actions/member-actions.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Delete member <?= e($m['full_name']) ?> permanently?');">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="delete_member">
                                                    <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Member">&times;</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 13px; color: var(--text-dim);">
                            Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> items)
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&plan_id=<?= $planFilter ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&plan_id=<?= $planFilter ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- ================= ADD MEMBER MODAL ================= -->
<div class="modal-backdrop" id="addMemberModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Register New Member</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addMemberModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/member-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_member">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="add_full_name" name="full_name" class="form-control" placeholder="e.g. Marcus Carter" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="add_email" name="email" class="form-control" placeholder="marcus@example.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_phone">Phone Number <span class="required">*</span></label>
                        <input type="text" id="add_phone" name="phone" class="form-control" placeholder="+1 (555) 000-1122" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_gender">Gender</label>
                        <select id="add_gender" name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_blood">Blood Group</label>
                        <input type="text" id="add_blood" name="blood_group" class="form-control" placeholder="e.g. O+, A+, B-">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_dob">Date of Birth</label>
                        <input type="date" id="add_dob" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_emergency">Emergency Contact</label>
                        <input type="text" id="add_emergency" name="emergency_contact" class="form-control" placeholder="Name & Phone">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_address">Residential Address</label>
                    <textarea id="add_address" name="address" class="form-control" rows="2" placeholder="Street, City, Postal Code"></textarea>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                <h4 style="color: var(--primary); font-size: 15px; margin-bottom: 14px;">Initial Membership Plan</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="planSelect">Choose Plan</label>
                        <select id="planSelect" name="plan_id" class="form-control">
                            <option value="0">-- Assign Plan Later --</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>" data-duration="<?= $p['duration_days'] ?>" data-price="<?= $p['price'] ?>">
                                    <?= e($p['plan_name']) ?> (<?= format_currency($p['price']) ?> / <?= $p['duration_days'] ?> days)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="startDateInput">Membership Start Date</label>
                        <input type="date" id="startDateInput" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expiryDateInput">Estimated Expiry Date</label>
                        <input type="date" id="expiryDateInput" class="form-control" readonly style="background: var(--bg-main);">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-main); font-size: 14px;">
                        <input type="checkbox" name="create_account" value="1" checked onchange="document.getElementById('pwdRow').style.display = this.checked ? 'grid' : 'none';">
                        Create Member Portal Login Account
                    </label>
                </div>

                <div class="form-row" id="pwdRow">
                    <div class="form-group">
                        <label class="form-label" for="account_password">Initial Account Password</label>
                        <input type="password" id="account_password" name="account_password" class="form-control" value="Member@123">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMemberModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Member Record</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT MEMBER MODAL ================= -->
<div class="modal-backdrop" id="editMemberModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Edit Member Profile</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editMemberModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/member-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit_member">
            <input type="hidden" id="edit_member_id" name="member_id">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="edit_email" name="email" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_phone">Phone Number <span class="required">*</span></label>
                        <input type="text" id="edit_phone" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_gender">Gender</label>
                        <select id="edit_gender" name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_status">Account Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_dob">Date of Birth</label>
                        <input type="date" id="edit_dob" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_blood">Blood Group</label>
                        <input type="text" id="edit_blood" name="blood_group" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_emergency">Emergency Contact</label>
                        <input type="text" id="edit_emergency" name="emergency_contact" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editMemberModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= VIEW MEMBER MODAL ================= -->
<div class="modal-backdrop" id="viewMemberModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Member Details</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('viewMemberModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <div class="user-avatar" id="view_avatar" style="width: 70px; height: 70px; font-size: 28px; margin: 0 auto 10px;"></div>
                <h3 id="view_name" style="color: #ffffff; margin-bottom: 2px;"></h3>
                <span id="view_code" class="card-code"></span>
            </div>

            <div style="background: var(--bg-input); padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Email:</span> <strong id="view_email" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Phone:</span> <strong id="view_phone" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Gender:</span> <strong id="view_gender" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Blood Group:</span> <strong id="view_blood" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Emergency:</span> <strong id="view_emergency" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Active Plan:</span> <strong id="view_plan" style="color: var(--primary);"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Plan Expiry:</span> <strong id="view_expiry" style="color: #ffffff;"></strong></div>
                <div style="display: flex; justify-content: space-between;"><span style="color: var(--text-dim);">Address:</span> <strong id="view_address" style="color: #ffffff;"></strong></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewMemberModal')">Close</button>
        </div>
    </div>
</div>

<script>
// Check if URL has ?action=new to open modal automatically
if (window.location.search.includes('action=new')) {
    openModal('addMemberModal');
}

function editMember(m) {
    document.getElementById('edit_member_id').value = m.id;
    document.getElementById('edit_full_name').value = m.full_name;
    document.getElementById('edit_email').value = m.email;
    document.getElementById('edit_phone').value = m.phone;
    document.getElementById('edit_gender').value = m.gender;
    document.getElementById('edit_status').value = m.status;
    document.getElementById('edit_dob').value = m.dob || '';
    document.getElementById('edit_blood').value = m.blood_group || '';
    document.getElementById('edit_emergency').value = m.emergency_contact || '';
    document.getElementById('edit_address').value = m.address || '';
    openModal('editMemberModal');
}

function viewMember(m) {
    document.getElementById('view_avatar').textContent = m.full_name.charAt(0).toUpperCase();
    document.getElementById('view_name').textContent = m.full_name;
    document.getElementById('view_code').textContent = m.member_code;
    document.getElementById('view_email').textContent = m.email;
    document.getElementById('view_phone').textContent = m.phone;
    document.getElementById('view_gender').textContent = m.gender;
    document.getElementById('view_blood').textContent = m.blood_group || 'N/A';
    document.getElementById('view_emergency').textContent = m.emergency_contact || 'N/A';
    document.getElementById('view_plan').textContent = m.plan_name || 'No Active Plan';
    document.getElementById('view_expiry').textContent = m.expiry_date || 'N/A';
    document.getElementById('view_address').textContent = m.address || 'N/A';
    openModal('viewMemberModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
