<?php
declare(strict_types=1);

/**
 * APEXFITNESS GYM database configuration.
 * Compatible with PHP 8.0 and XAMPP.
 */

if (!defined('GYM_PROJECT_ROOT')) {
  define('GYM_PROJECT_ROOT', dirname(__DIR__));
}

/**
 * Load .env without overwriting environment variables
 * already supplied by PHP, Apache, or PowerShell.
 */
if (!defined('GYM_ENV_LOADED')) {
  define('GYM_ENV_LOADED', true);

  $envFile = GYM_PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env';

  if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);

    foreach ($lines as $line) {
      if ($line === false) {
        continue;
      }

      $line = trim($line);

      if ($line === '' || strpos($line, '#') === 0) {
        continue;
      }

      if (strpos($line, '=') === false) {
        continue;
      }

      [$key, $value] = explode('=', $line, 2);

      $key = trim($key);
      $value = trim($value);

      if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
        continue;
      }

      // Remove matching surrounding quotes.
      if (
        strlen($value) >= 2 &&
        (
          ($value[0] === '"' &&
            substr($value, -1) === '"') ||
          ($value[0] === "'" &&
            substr($value, -1) === "'")
        )
      ) {
        $value = substr($value, 1, -1);
      }

      // Existing environment variables take precedence.
      if (getenv($key) === false) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
      }
    }
  }
}

/**
 * Read a configured value, preserving empty strings.
 */
if (!function_exists('gym_env')) {
  function gym_env(string $key, string $default = ''): string
  {
    $value = getenv($key);

    if ($value !== false) {
      return (string) $value;
    }

    if (isset($_ENV[$key])) {
      return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key])) {
      return (string) $_SERVER[$key];
    }

    return $default;
  }
}

/**
 * Return a reusable PDO database connection.
 */
if (!function_exists('getDB')) {
  function getDB(bool $showError = true): PDO
  {
    static $pdo = null;

    if ($pdo instanceof PDO) {
      return $pdo;
    }

    $host = gym_env('DB_HOST', '127.0.0.1');
    $port = gym_env('DB_PORT', '3306');
    $name = gym_env('DB_NAME', 'gym_management');
    $user = gym_env('DB_USER', 'root');
    $pass = gym_env('DB_PASS', '');

    if (
      !preg_match('/^[a-zA-Z0-9_.-]+$/', $host) ||
      !ctype_digit($port) ||
      (int) $port < 1 ||
      (int) $port > 65535 ||
      !preg_match('/^[a-zA-Z0-9_]+$/', $name) ||
      $user === ''
    ) {
      throw new RuntimeException(
        'Invalid database configuration. Check DB_HOST, DB_PORT, DB_NAME and DB_USER.'
      );
    }

    $dsn = sprintf(
      'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
      $host,
      (int) $port,
      $name
    );

    try {
      $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
          PDO::ATTR_PERSISTENT => false,
        ]
      );

      return $pdo;
    } catch (PDOException $e) {
      // Log diagnostic details privately, never the password.
      error_log(
        'APEXFITNESS database connection failed: ' .
        $e->getMessage()
      );

      // The setup installer can catch this exception.
      throw new RuntimeException(
        'Could not connect to the gym_management database. ' .
        'Check MySQL status and your .env database credentials.',
        0,
        $e
      );
    }
  }
}
