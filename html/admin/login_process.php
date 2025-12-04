<?php
require_once __DIR__ . '/config.php';
secure_session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If using Composer autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// helper: client IP
function client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: adminlogin.php');
    exit;
}

// CSRF check
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    error_log('CSRF mismatch from IP: ' . client_ip());
    header('Location: adminlogin.php?err=1');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$ip = client_ip();
$now = time();

// Validate username format (adjust allowed pattern as needed)
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    header('Location: adminlogin.php?err=1');
    exit;
}

// ------- Rate limit & lockout: use login_attempts table (create below) -------
// We'll track failed attempts per username+ip within LOCKOUT_SECONDS window.
$threshold_time = date('Y-m-d H:i:s', $now - LOCKOUT_SECONDS);

// Count recent failed attempts for this username+ip
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts WHERE username = ? AND ip_address = ? AND attempt_time > ? AND success = 0");
$stmt->bind_param('sss', $username, $ip, $threshold_time);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$recent_failures = (int)$res['cnt'];

if ($recent_failures >= MAX_LOGIN_ATTEMPTS) {
    header('Location: adminlogin.php?err=1');
    exit;
}

// Fetch user record
$stmt = $conn->prepare("SELECT id, username, email, password_hash, is_verified FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Log failed attempt
    $ins = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, NOW(), 0)");
    $ins->bind_param('ss', $username, $ip);
    $ins->execute();

    header('Location: adminlogin.php?err=1');
    exit;
}

$user = $result->fetch_assoc();

// Check is_verified
if ((int)$user['is_verified'] !== 1) {
    header('Location: adminlogin.php?err=1');
    exit;
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    $ins = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, NOW(), 0)");
    $ins->bind_param('ss', $username, $ip);
    $ins->execute();

    header('Location: adminlogin.php?err=1');
    exit;
}

// Success: clear attempts for this username+ip (optional)
$del = $conn->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?");
$del->bind_param('ss', $username, $ip);
$del->execute();

// Generate OTP, store in session (do NOT store OTP in DB for now)
$otp = random_int(100000, 999999);
$_SESSION['pending_user_id'] = $user['id'];
$_SESSION['pending_username'] = $user['username'];
$_SESSION['otp'] = (string)$otp;
$_SESSION['otp_expiry'] = time() + OTP_EXPIRY_SECONDS;
$_SESSION['otp_attempts'] = 0;

// Prepare OTP message
$otp_message = "Your one-time login code is: {$otp}\nThis code expires in " . (OTP_EXPIRY_SECONDS/60) . " minutes.";

// Try sending via PHPMailer if SMTP configured
$sent_ok = false;
if (!empty($smtp['host']) && !empty($smtp['username'])) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = $smtp['secure'] ?? 'tls';
        $mail->Port = $smtp['port'] ?? 587;

        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($user['email'], $user['username']);
        $mail->Subject = 'Your Login Verification Code';
        $mail->Body = $otp_message;
        $mail->send();
        $sent_ok = true;
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
        $sent_ok = false;
    }
} else {
    // Local dev fallback: write OTP to a dev log file
    $logLine = date('Y-m-d H:i:s') . " | OTP for user {$user['username']} ({$user['email']}): {$otp}\n";
    file_put_contents(__DIR__ . '/dev_otp_log.txt', $logLine, FILE_APPEND);
    $sent_ok = true;
}

// Log this successful authentication step for audit as success
$ins2 = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, NOW(), 1)");
$ins2->bind_param('ss', $username, $ip);
$ins2->execute();

// Redirect to OTP verification page
header('Location: verify_otp.php');
exit;
