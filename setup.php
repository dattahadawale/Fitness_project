<?php
/**
 * Interactive System Setup & First-Admin Installer Wizard
 * Gym Management System
 */

require_once __DIR__ . '/includes/functions.php';

$message = '';
$messageType = '';
$dbConnected = false;
$tablesExist = false;

// 1. Test Database Connectivity
try {
    $db = getDB(false);
    $dbConnected = true;
    $chk = $db->query("SHOW TABLES LIKE 'users'");
    $tablesExist = (bool)$chk->fetch();
} catch (Exception $e) {
    $message = "Database connection error: " . $e->getMessage();
    $messageType = 'error';
}

function importSqlFile(PDO $db, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Could not read SQL file: {$sqlFile}");
    }

    $delimiter = ';';
    $statement = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches)) {
            $delimiter = $matches[1];
            continue;
        }
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }

        $statement .= $line . "\n";
        $trimmed = rtrim($statement);
        if (substr($trimmed, -strlen($delimiter)) === $delimiter) {
            $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
            if ($statement !== '') {
                $db->exec($statement);
            }
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        throw new RuntimeException("SQL file contains an unterminated statement: {$sqlFile}");
    }
}

// 2. Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$dbConnected) {
        $message = 'Database actions are unavailable until the database connection is configured.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];

        if ($action === 'import_schema') {
            try {
                $sqlFile = __DIR__ . '/database/gym_management.sql';
                if (!file_exists($sqlFile)) {
                    throw new Exception("SQL file not found at: {$sqlFile}");
                }
                importSqlFile($db, $sqlFile);
                $migrationFiles = glob(__DIR__ . '/database/migrations/*.sql');
                if (!$migrationFiles) {
                    throw new Exception('No database migration files were found.');
                }
                sort($migrationFiles);
                foreach ($migrationFiles as $migrationFile) {
                    importSqlFile($db, $migrationFile);
                }
                $message = "Database schema and sample data successfully imported!";
                $messageType = 'success';
                $tablesExist = true;
            } catch (Exception $e) {
                $message = "Schema import failed: " . $e->getMessage();
                $messageType = 'error';
            }
        }

        if ($action === 'create_admin') {
            $name = trim($_POST['admin_name'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $password = $_POST['admin_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                $message = "All administrator fields are required.";
                $messageType = 'error';
            } elseif ($password !== $confirmPassword) {
                $message = "Administrator passwords do not match.";
                $messageType = 'error';
            } elseif (strlen($password) < 6) {
                $message = "Password must be at least 6 characters long.";
                $messageType = 'error';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    // Check if admin with this email exists
                    $chkStmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                    $chkStmt->execute([':email' => $email]);
                    $existing = $chkStmt->fetch();

                    if ($existing) {
                        $upd = $db->prepare("
                            UPDATE users SET name = :name, password = :password, role = 'admin', status = 'active'
                            WHERE id = :id
                        ");
                        $upd->execute([':name' => $name, ':password' => $hash, ':id' => $existing['id']]);
                    } else {
                        $ins = $db->prepare("
                            INSERT INTO users (name, email, password, role, status)
                            VALUES (:name, :email, :password, 'admin', 'active')
                        ");
                        $ins->execute([':name' => $name, ':email' => $email, ':password' => $hash]);
                    }

                    $message = "Administrator account '{$email}' configured successfully! You can now sign in.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Failed to create administrator: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }
}

$pageTitle = 'System Setup Wizard';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card" style="max-width: 640px;">
    <div class="auth-header">
        <div class="auth-brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">APEX<span>FITNESS</span></div>
        </div>
        <h2 class="auth-title">Setup & Deployment Wizard</h2>
        <p class="auth-subtitle">Verify system requirements, initialize the database, and create your administrator.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert-toast alert-<?= $messageType ?>" style="position: static; margin-bottom: 20px; animation: none;">
            <span class="alert-icon"><?= $messageType === 'success' ? '&#10003;' : '&#9888;' ?></span>
            <span class="alert-text"><?= e($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Step 1: Environment & Database Status -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3 class="card-title" style="font-size: 15px;">1. System & Database Health</h3>
        </div>
        <div class="card-body" style="padding: 16px;">
            <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                <li style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">PHP Version (>= 7.4):</span>
                    <strong style="color: var(--success);">&#10003; <?= PHP_VERSION ?></strong>
                </li>
                <li style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">PDO MySQL Driver:</span>
                    <strong style="color: var(--success);">&#10003; Enabled</strong>
                </li>
                <li style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Database Server (<?= e(DB_HOST) ?>:<?= e(DB_PORT) ?>):</span>
                    <strong style="color: <?= $dbConnected ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $dbConnected ? '&#10003; Connected' : '&#10007; Disconnected' ?>
                    </strong>
                </li>
                <li style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Database Schema Tables:</span>
                    <strong style="color: <?= $tablesExist ? 'var(--success)' : 'var(--warning)' ?>;">
                        <?= $tablesExist ? '&#10003; Tables Present' : '&#9888; Not Initialized' ?>
                    </strong>
                </li>
            </ul>

            <?php if (!$dbConnected): ?>
                <div class="alert-toast alert-error" style="position: static; margin-top: 16px; animation: none;">
                    <span class="alert-icon">&#9888;</span>
                    <span class="alert-text">
                        MySQL is not accepting the configured credentials. Copy <code>.env.example</code> to
                        <code>.env</code> in the project root and set <code>DB_USER</code> and <code>DB_PASS</code>
                        to your local MySQL account. If the error above says “using password: NO”, PHP is sending an
                        empty password. Keep the password in the ignored <code>.env</code> file; do not put it in
                        source code or share it. Reload this page after saving.
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($dbConnected): ?>
                <form method="POST" style="margin-top: 16px;">
                    <input type="hidden" name="action" value="import_schema">
                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('This will import the database schema and sample records. Proceed?');">
                        🔄 <?= $tablesExist ? 'Re-import / Reset Schema' : 'Import gym_management.sql' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Step 2: Configure Initial Administrator -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3 class="card-title" style="font-size: 15px;">2. Create / Update Administrator Account</h3>
        </div>
        <div class="card-body" style="padding: 20px;">
            <?php if ($dbConnected && $tablesExist): ?>
            <form method="POST">
                <input type="hidden" name="action" value="create_admin">

                <div class="form-group">
                    <label class="form-label" for="admin_name">Admin Full Name <span class="required">*</span></label>
                    <input type="text" id="admin_name" name="admin_name" class="form-control" value="Gym Administrator" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="admin_email">Admin Email <span class="required">*</span></label>
                    <input type="email" id="admin_email" name="admin_email" class="form-control" value="admin@gym.com" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="admin_password">Password <span class="required">*</span></label>
                        <input type="password" id="admin_password" name="admin_password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Save Administrator Account &rarr;
                </button>
            </form>
            <?php else: ?>
                <p style="color: var(--text-dim);">
                    Configure and connect the database, then initialize its schema before creating the administrator.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="<?= base_url('login.php') ?>" class="btn btn-secondary">
            Go to Login Portal &rarr;
        </a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
