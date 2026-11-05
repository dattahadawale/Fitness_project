<?php
/**
 * Global Helper Functions & Utilities
 * APEXFITNESS Gym Management System
 * Compatible with PHP 8.0+
 */

require_once __DIR__ . '/env_loader.php';



// ...the rest of your existing code stays as it is

require_once __DIR__ . '/../config/database.php';

// Start session if not already started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output for safe HTML display.
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/**
 * Sanitize simple text input.
 * Use prepared SQL statements separately for database queries.
 */
if (!function_exists('sanitize')) {
    function sanitize($data): string
    {
        return trim(strip_tags((string) $data));
    }
}

/**
 * Generate CSRF token and store in session.
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string
    {
        if (
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            $_SESSION['csrf_token'] === ''
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

/**
 * Validate CSRF token from request.
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token): bool
    {
        if (
            !is_string($token) ||
            $token === '' ||
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            $_SESSION['csrf_token'] === ''
        ) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Output hidden CSRF input field.
 */
if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="csrf_token" value="' .
            e(generate_csrf_token()) .
            '">';
    }
}

/**
 * Set flash message.
 */
if (!function_exists('set_flash')) {
    function set_flash($type, $message): void
    {
        $_SESSION['flash'] = [
            'type' => (string) $type,
            'message' => (string) $message,
        ];
    }
}

/**
 * Get and clear flash message.
 */
if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }
}

/**
 * Render flash alert banner/toast HTML.
 */
if (!function_exists('render_flash')) {
    function render_flash(): string
    {
        $flash = get_flash();

        if (!$flash) {
            return '';
        }

        $allowedTypes = ['success', 'error', 'warning', 'info'];
        $type = in_array(
            $flash['type'] ?? '',
            $allowedTypes,
            true
        ) ? $flash['type'] : 'info';

        $icons = [
            'success' => '&#10003;',
            'error' => '&#10007;',
            'warning' => '&#9888;',
            'info' => '&#9432;',
        ];

        return "
        <div class='alert-toast alert-" . e($type) . "' id='flashAlert'>
            <span class='alert-icon'>{$icons[$type]}</span>
            <span class='alert-text'>" .
            e($flash['message'] ?? '') .
            "</span>
            <button type='button' class='alert-close'
                onclick='this.parentElement.remove();'>&times;</button>
        </div>";
    }
}

/**
 * Format currency.
 */
if (!function_exists('format_currency')) {
    function format_currency($amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }
}

/**
 * Format date string.
 */
if (!function_exists('format_date')) {
    function format_date($date, $format = 'M d, Y'): string
    {
        if (!$date || $date === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime((string) $date);

        return $timestamp !== false
            ? date($format, $timestamp)
            : '-';
    }
}

/**
 * Format time string.
 */
if (!function_exists('format_time')) {
    function format_time($time, $format = 'h:i A'): string
    {
        if (!$time) {
            return '-';
        }

        $timestamp = strtotime((string) $time);

        return $timestamp !== false
            ? date($format, $timestamp)
            : '-';
    }
}

/**
 * Build a URL relative to the application base URL.
 *
 * APP_BASE_URL should contain the application's public base URL,
 * for example http://127.0.0.1:8000 or
 * http://127.0.0.1:8000/gym.
 */
if (!function_exists('base_url')) {
    function base_url($path = ''): string
    {
        $base = '';

        if (function_exists('gym_env')) {
            $base = trim(gym_env('APP_BASE_URL', ''));
        }

        if ($base === '') {
            $base = 'http://127.0.0.1:8000';
        }

        return rtrim($base, '/') . '/' . ltrim((string) $path, '/');
    }
}

/**
 * Generate next member code.
 *
 * Note: For concurrent registrations, a database uniqueness constraint
 * is also required to prevent duplicate member codes.
 */
if (!function_exists('generate_next_member_code')) {
    function generate_next_member_code(): string
    {
        $db = getDB();

        $stmt = $db->query(
            "SELECT MAX(id) AS max_id FROM members"
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextId = (
            $row && $row['max_id'] !== null
        ) ? ((int) $row['max_id'] + 1001) : 1001;

        return 'MBR-' . $nextId;
    }
}

/**
 * Calculate KPI summary stats for admin dashboard.
 */
if (!function_exists('get_admin_kpi_stats')) {
    function get_admin_kpi_stats(): array
    {
        $db = getDB();

        $totalMembers = (int) $db->query(
            "SELECT COUNT(*) FROM members"
        )->fetchColumn();

        $activeMembers = (int) $db->query("
            SELECT COUNT(DISTINCT m.id)
            FROM members m
            JOIN memberships ms ON m.id = ms.member_id
            WHERE ms.status = 'active'
              AND ms.expiry_date >= CURRENT_DATE
        ")->fetchColumn();

        $totalTrainers = (int) $db->query("
            SELECT COUNT(*)
            FROM trainers
            WHERE status = 'active'
        ")->fetchColumn();

        $monthlyRevenue = (float) $db->query("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE status = 'completed'
              AND payment_date >= DATE_FORMAT(
                  CURRENT_DATE, '%Y-%m-01'
              )
              AND payment_date < DATE_FORMAT(
                  CURRENT_DATE + INTERVAL 1 MONTH, '%Y-%m-01'
              )
        ")->fetchColumn();

        $todayAttendance = (int) $db->query("
            SELECT COUNT(*)
            FROM attendance
            WHERE attendance_date = CURRENT_DATE
        ")->fetchColumn();

        $expiredMemberships = (int) $db->query("
            SELECT COUNT(DISTINCT member_id)
            FROM memberships
            WHERE expiry_date < CURRENT_DATE
               OR status = 'expired'
        ")->fetchColumn();

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'total_trainers' => $totalTrainers,
            'monthly_revenue' => $monthlyRevenue,
            'today_attendance' => $todayAttendance,
            'expired_memberships' => $expiredMemberships,
        ];
    }
}

/**
 * Get memberships expiring within the next $days days.
 */
if (!function_exists('get_expiring_memberships_alert')) {
    function get_expiring_memberships_alert($days = 7): array
    {
        $db = getDB();
        $days = max(0, min(365, (int) $days));

        $stmt = $db->prepare("
            SELECT
                m.id AS member_id,
                m.full_name,
                m.member_code,
                m.phone,
                m.email,
                ms.expiry_date,
                ms.status,
                mp.plan_name,
                DATEDIFF(
                    ms.expiry_date, CURRENT_DATE
                ) AS days_left
            FROM memberships ms
            JOIN members m ON ms.member_id = m.id
            JOIN membership_plans mp ON ms.plan_id = mp.id
            WHERE ms.status = 'active'
              AND ms.expiry_date >= CURRENT_DATE
              AND DATEDIFF(
                  ms.expiry_date, CURRENT_DATE
              ) <= :days
            ORDER BY ms.expiry_date ASC
            LIMIT 10
        ");

        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Update expired memberships.
 */
if (!function_exists('auto_check_membership_expiry')) {
    function auto_check_membership_expiry(): void
    {
        $db = getDB();

        $db->beginTransaction();

        try {
            $db->exec("
                UPDATE memberships
                SET status = 'expired'
                WHERE status = 'active'
                  AND expiry_date < CURRENT_DATE
            ");

            $db->exec("
                UPDATE members
                SET status = 'expired'
                WHERE status = 'active'
                  AND EXISTS (
                      SELECT 1
                      FROM memberships ms
                      WHERE ms.member_id = members.id
                  )
                  AND NOT EXISTS (
                      SELECT 1
                      FROM memberships ms
                      WHERE ms.member_id = members.id
                        AND ms.status = 'active'
                        AND ms.expiry_date >= CURRENT_DATE
                  )
            ");

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Membership expiry update failed: ' .
                $e->getMessage()
            );

            throw $e;
        }
    }
}


