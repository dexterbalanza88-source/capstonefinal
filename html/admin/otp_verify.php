<?php
session_name("admin_session");
session_start();
require_once "../../db/conn.php";
require_once "mailer.php";

// Redirect if no pending OTP
if (empty($_SESSION['pending_user_id'])) {
    header("Location: adminlogin.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function flash($key)
{
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function recordSecurityEvent($conn, $user_id, $email, $event_type, $description, $severity = 'MEDIUM')
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if ($stmt = $conn->prepare("INSERT INTO security_events (user_id, email, event_type, event_description, ip_address, user_agent, severity) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
        $stmt->bind_param("issssss", $user_id, $email, $event_type, $description, $ip, $ua, $severity);
        $stmt->execute();
    }
}

// RESEND OTP
if (isset($_GET['resend']) && $_GET['resend'] == 1) {
    $user_id = (int) $_SESSION['pending_user_id'];
    $otp = random_int(100000, 999999);
    $otp_expiry = date("Y-m-d H:i:s", time() + 300);

    $update = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
    $update->bind_param("ssi", $otp, $otp_expiry, $user_id);
    $update->execute();

    $s = $conn->prepare("SELECT email FROM users WHERE id=?");
    $s->bind_param("i", $user_id);
    $s->execute();
    $res = $s->get_result();
    $row = $res->fetch_assoc();

    if ($row) {
        sendOTPEmail($row['email'], $otp, 5);
        $_SESSION['success'] = "A new code was sent to your email.";
        recordSecurityEvent($conn, $user_id, $row['email'], 'OTP_RESENT', 'OTP resent', 'LOW');
    } else {
        $_SESSION['error'] = "User not found.";
    }

    header("Location: otp_verify.php");
    exit;
}

// OTP Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid session token.";
        header("Location: otp_verify.php");
        exit;
    }

    $code = trim($_POST['otp_code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) {
        $_SESSION['error'] = "Enter a valid 6-digit code.";
        header("Location: otp_verify.php");
        exit;
    }

    $user_id = (int) $_SESSION['pending_user_id'];
    $stmt = $conn->prepare("SELECT id, username, email, role, otp_code, otp_expires FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || empty($user['otp_code']) || empty($user['otp_expires'])) {
        $_SESSION['error'] = "No OTP found. Request a new code.";
        header("Location: otp_verify.php");
        exit;
    }

    if (strtotime($user['otp_expires']) < time()) {
        $_SESSION['error'] = "Code expired. Click 'Resend OTP'.";
        header("Location: otp_verify.php");
        exit;
    }

    if (hash_equals((string) $user['otp_code'], (string) $code)) {
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = strtolower($_SESSION['pending_role'] ?? $user['role']);

        // Clear OTP
        $clear = $conn->prepare("UPDATE users SET otp_code=NULL, otp_expires=NULL WHERE id=?");
        $clear->bind_param("i", $user_id);
        $clear->execute();

        recordSecurityEvent($conn, $user_id, $user['email'], 'OTP_VERIFIED', 'OTP verified', 'LOW');

        // Remove pending data
        unset($_SESSION['pending_user_id'], $_SESSION['pending_role'], $_SESSION['otp_expires']);

        session_regenerate_id(true);

        // Display alert before redirecting
        echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Redirecting...</title>
        <script>
            alert("OTP verification successful! You are now logged in.");
            window.location.href = "index.php";
        </script>
    </head>
    <body>
        <p>If you are not redirected automatically, <a href="index.php">click here</a>.</p>
    </body>
    </html>';
    } else {
        $_SESSION['error'] = "Incorrect code.";
        recordSecurityEvent($conn, $user_id, $user['email'], 'OTP_FAILED', 'Incorrect OTP', 'MEDIUM');
        header("Location: otp_verify.php");
        exit;
    }   
}

// For display
$err = flash('error');
$ok = flash('success');

$stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['pending_user_id']);
$stmt->execute();
$r = $stmt->get_result();
$row = $r->fetch_assoc();
$email = $row['email'] ?? '';
$parts = explode('@', $email);
$local = $parts[0] ?? '';
$domain = $parts[1] ?? '';
$local_mask = (strlen($local) <= 2) ? str_repeat('*', strlen($local)) : substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
$info_email = $local_mask . '@' . $domain;
?>



<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>OTP Verification | MAO – Abra De Ilog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <style>
        body {
            background: url('../../img/mun.png') center/cover fixed;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-6">
    <div class="glass rounded-2xl shadow-xl max-w-md w-full p-8">
        <div class="text-center mb-6">
            <img src="../../img/logo.png" class="mx-auto w-20 h-20" alt="Logo">
            <h1 class="text-2xl font-bold text-green-800 mt-3">OTP Verification</h1>
            <p class="text-sm text-gray-600 mt-2">Enter the 6-digit code sent to
                <strong><?= htmlspecialchars($info_email ?? '') ?></strong>.
            </p>
        </div>

        <?php if ($err): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded border border-red-200"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if ($ok): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded border border-green-200"><?= htmlspecialchars($ok) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="otp_verify.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <label class="block text-sm font-medium text-gray-700">Enter OTP</label>
            <input name="otp_code" inputmode="numeric" pattern="\d{6}" required
                class="w-full p-3 border rounded focus:ring-2 focus:ring-green-600" placeholder="123456" maxlength="6">

            <button type="submit" class="w-full py-3 bg-green-700 text-white rounded font-semibold">Verify Code</button>
        </form>

        <div class="mt-4 text-center text-sm">
            <a href="otp_verify.php?resend=1" class="text-blue-600 hover:underline">Resend OTP</a>
            <span class="mx-2">·</span>
            <a href="adminlogin.php" class="text-gray-600 hover:underline">Back to Login</a>
        </div>

        <p class="mt-6 text-xs text-gray-500 text-center">This code expires in 5 minutes. If you still have problems,
            contact the administrator.</p>
    </div>
</body>

</html>