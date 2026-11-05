<?php
/**
 * Database Configuration & Connection Handler
 * Gym Management System
 */

// Prevent direct script execution if necessary, or ensure safe inclusion
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

/**
 * Load local configuration without overriding variables provided by the server.
 */
function load_app_environment(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        error_log('Unable to read the application .env file.');
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name) || getenv($name) !== false) {
            continue;
        }

        $quote = substr($value, 0, 1);
        if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

load_app_environment();

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gym_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

/**
 * Returns a singleton PDO connection instance
 * 
 * @return PDO
 */
function getDB(bool $renderErrorPage = true) {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (!$renderErrorPage) {
                throw $e;
            }

            // Friendly error screen for database connection failure
            die("
            <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; background: #0f1115; color: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;'>
                <div style='max-width: 520px; background: #181b22; border: 1px solid #ff6b00; border-radius: 12px; padding: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);'>
                    <h2 style='color: #ff6b00; margin-top: 0; font-size: 24px; display: flex; align-items: center; gap: 10px;'>
                        <span>&#9888;</span> Database Connection Error
                    </h2>
                    <p style='color: #9ca3af; line-height: 1.6;'>Could not connect to the database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
                    <div style='background: #0d0f12; padding: 12px 16px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #f87171; margin-bottom: 20px;'>
                        " . htmlspecialchars($e->getMessage()) . "
                    </div>
                    <h4 style='color: #ffffff; margin-bottom: 8px;'>How to resolve:</h4>
                    <ol style='color: #9ca3af; line-height: 1.6; padding-left: 20px; font-size: 14px;'>
                        <li>Make sure MySQL is running and accepting connections on the configured host and port.</li>
                        <li>Copy <code>.env.example</code> to <code>.env</code> in the project root and set DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS to your local MySQL values.</li>
                        <li>Restart PHP or Apache after changing server environment variables. The local <code>.env</code> file is read automatically.</li>
                        <li>Import <code>database/gym_management.sql</code> via phpMyAdmin or MySQL CLI.</li>
                        <li>Or visit <a href='setup.php' style='color: #ff6b00; font-weight: bold;'>setup.php</a> to run the automated installer.</li>
                    </ol>
                </div>
            </div>
            ");
        }
    }

    return $pdo;
}
