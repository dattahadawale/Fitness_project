<?php
/**
 * SMTP diagnostic. Put in the project root and run from PowerShell:
 *     php test-smtp.php you@example.com
 * Prints the REAL reason mail fails. Passwords/credentials are redacted. Delete it when done.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('Run from the command line only.');
}
require __DIR__ . '/vendor/autoload.php';

// Read .env as a fallback; real environment variables win.
$env = [];
if (is_file(__DIR__ . '/.env')) {
  foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if ($l[0] === '#' || strpos($l, '=') === false)
      continue;
    [$k, $v] = explode('=', $l, 2);
    $env[trim($k)] = trim($v, " \t\"'");
  }
}
$get = fn($k, $d = '') => getenv($k) !== false && getenv($k) !== '' ? getenv($k) : ($env[$k] ?? $d);

$host = $get('SMTP_HOST');
$port = (int) $get('SMTP_PORT', 587);
$user = $get('SMTP_USERNAME');
$pass = $get('SMTP_PASSWORD');
$enc = strtolower($get('SMTP_ENCRYPTION', 'tls'));
$from = $get('SMTP_FROM_EMAIL') ?: $user;
$to = $argv[1] ?? $user;

echo "SMTP_HOST=$host  SMTP_PORT=$port  SMTP_ENCRYPTION=$enc\n";
echo "SMTP_USERNAME=" . ($user !== '' ? $user : '<EMPTY>') . "\n";
echo "SMTP_PASSWORD=" . ($pass !== '' ? '<set, ' . strlen($pass) . ' chars>' : '<EMPTY>') . "\n";
echo "SMTP_FROM_EMAIL=" . ($from !== '' ? $from : '<EMPTY>') . "\n";
echo "openssl extension: " . (extension_loaded('openssl') ? 'enabled' : 'MISSING (enable extension=openssl in php.ini)') . "\n\n";
if ($user === '' || $pass === '' || $from === '' || $to === '') {
  exit("STOP: fill SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL in .env first.\n");
}

$m = new PHPMailer\PHPMailer\PHPMailer(true);
try {
  $m->isSMTP();
  $m->Host = $host;
  $m->Port = $port;
  $m->SMTPAuth = true;
  $m->Username = $user;
  $m->Password = $pass;
  $m->SMTPSecure = $enc === 'ssl' ? 'ssl' : 'tls';
  $m->SMTPDebug = 2;
  $m->Debugoutput = function ($s) {
    $s = rtrim($s);
    echo preg_match('/CLIENT -> SERVER: [A-Za-z0-9+\/=]{12,}$/', $s) ? "CLIENT -> SERVER: <redacted>\n" : $s . "\n";
  };
  $m->setFrom($from, 'Apex Fitness Club');
  $m->addAddress($to);
  $m->Subject = 'Apex SMTP test';
  $m->Body = 'If you can read this, SMTP works.';
  $m->send();
  echo "\nSUCCESS: test email sent to $to\n";
} catch (Throwable $e) {
  echo "\nFAILED: " . $e->getMessage() . "\n";
}

