<?php
/**
 * Loads KEY=value lines from the project-root .env into getenv()/$_ENV,
 * so SMTP_* settings are always available to PHPMailer.
 * Real environment variables (e.g. set in PowerShell) are never overridden.
 */
(function () {
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
  if (!is_file($file) || !is_readable($file)) {
    return;
  }
  foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
      continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
      $value = substr($value, 1, -1);
    }
    if ($key === '' || getenv($key) !== false) {
      continue;                      // keep values already set by the real environment
    }
    putenv("$key=$value");
    $_ENV[$key] = $value;
  }
})();

