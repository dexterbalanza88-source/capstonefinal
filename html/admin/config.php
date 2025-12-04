<?php
// config.php
// Put this file outside webroot if possible for production.

// --- Database settings (update for your local environment) ---
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmer_info'; // change as needed

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    die('Database connection error.');
}

// --- SMTP / PHPMailer settings (leave empty for dev) ---
$smtp = [
    'host' => '',            // e.g. smtp.gmail.com
    'port' => 587,
    'username' => '',
    'password' => '',
    'from_email' => 'no-reply@local.test',
    'from_name' => 'MAO Abra De Ilog',
    'secure' => 'tls' // 'tls' or 'ssl'
];

// --- Security constants ---
define('OTP_EXPIRY_SECONDS', 300);        // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5);         // attempts before lockout
define('LOCKOUT_SECONDS', 900);          // lockout window (15 minutes)
define('INACTIVITY_TIMEOUT', 1800);      // 30 minutes session inactivity

// Start a secure session
function secure_session_start()
{
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Safe headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

?>