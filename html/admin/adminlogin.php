<?php
session_name("admin_session");
session_start();

require_once "../../db/conn.php";
require_once "mailer.php"; // must provide sendOTPEmail($email, $otp, $expiryMinutes)

// ---------------------------
// Security headers
// ---------------------------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ---------------------------
// Redirect if fully logged in
// ---------------------------
if (!empty($_SESSION['user_id']) && empty($_SESSION['pending_user_id'])) {
    header("Location: index.php");
    exit;
}

// ---------------------------
// Roles
// ---------------------------
$allowed_roles = ['admin', 'user', 'staff', 'farmer'];
$requested_role = strtolower($_GET['role'] ?? 'admin');
if (!in_array($requested_role, $allowed_roles)) {
    $requested_role = 'admin';
}

// ---------------------------
// CSRF token
// ---------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------
// Security settings
// ---------------------------
function getSecuritySettings($conn) {
    $defaults = [
        'rate_limit_window' => 15,
        'max_login_attempts' => 5,
        'lockout_duration' => 15,
        'otp_expiry_minutes' => 5,
        'require_2fa' => 1
    ];

    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'security_settings'");
        if ($table_check && $table_check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT setting_name, setting_value FROM security_settings");
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = $row['setting_name'];
                $val = $row['setting_value'];
                if ($name === 'require_2fa') {
                    $defaults[$name] = (int)$val;
                } else if (is_numeric($val)) {
                    $defaults[$name] = (int)$val;
                } else {
                    $defaults[$name] = $val;
                }
            }
        }
    } catch (Exception $e) {
        error_log('getSecuritySettings error: ' . $e->getMessage());
    }

    return $defaults;
}

$security_settings = getSecuritySettings($conn);

// ---------------------------
// Helper functions
// ---------------------------
function recordLoginAttempt($conn, $ip, $email, $success, $reason = null) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if ($stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, success, attempt_time, user_agent, reason) VALUES (?, ?, ?, NOW(), ?, ?)")) {
        $stmt->bind_param("ssiss", $ip, $email, $success, $user_agent, $reason);
        $stmt->execute();
    }
}

function recordSecurityEvent($conn, $user_id, $email, $event_type, $description, $severity = 'MEDIUM') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if ($stmt = $conn->prepare("INSERT INTO security_events (user_id, email, event_type, event_description, ip_address, user_agent, severity) VALUES (?, ?, ?, ?, ?, ?, ?)") ) {
        $stmt->bind_param("issssss", $user_id, $email, $event_type, $description, $ip, $ua, $severity);
        $stmt->execute();
    }
}

// ---------------------------
// Handle POST login
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }

    // Rate limit
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $window_minutes = intval($security_settings['rate_limit_window']);
    $stmt->bind_param("si", $ip, $window_minutes);
    $stmt->execute();
    $attempt_count = $stmt->get_result()->fetch_assoc()['attempt_count'] ?? 0;
    if ($attempt_count >= intval($security_settings['max_login_attempts'])) {
        $_SESSION['error'] = "Too many login attempts. Try again later.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        recordLoginAttempt($conn, $ip, $_POST['email'] ?? '', 0, 'invalid_csrf');
        $_SESSION['error'] = "Invalid security token.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    if (!$email || $password === '') {
        recordLoginAttempt($conn, $ip, $_POST['email'] ?? '', 0, 'empty_fields');
        $_SESSION['error'] = "Enter a valid email and password.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    // Fetch user
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, is_verified, COALESCE(failed_attempts,0) as failed_attempts, locked_until, COALESCE(two_factor_enabled,0) as two_factor_enabled FROM users WHERE email = ? AND COALESCE(is_active,1)=1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $dummy_hash = '$2y$10$dummyhashforconsistenttiming123456789012';
    $hash_to_verify = $user ? $user['password_hash'] : $dummy_hash;
    $password_correct = password_verify($password, $hash_to_verify);

    if (!$user || !$password_correct) {
        recordLoginAttempt($conn, $ip, $email, 0, 'invalid_credentials');
        recordSecurityEvent($conn, $user['id'] ?? 0, $email, 'LOGIN_FAILED', 'Invalid email or password', 'HIGH');
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    // Role check
    if (strtolower($user['role']) !== $requested_role) {
        recordLoginAttempt($conn, $ip, $email, 0, 'role_mismatch');
        recordSecurityEvent($conn, $user['id'], $email, 'LOGIN_FAILED', 'Role mismatch', 'HIGH');
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    // Account lock & verification
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        recordSecurityEvent($conn, $user['id'], $email, 'LOGIN_FAILED', 'Account locked', 'HIGH');
        $_SESSION['error'] = "Account temporarily locked.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }
    if ($user['is_verified'] != 1) {
        recordSecurityEvent($conn, $user['id'], $email, 'LOGIN_FAILED', 'Email not verified', 'MEDIUM');
        $_SESSION['error'] = "Please verify your email.";
        header("Location: adminlogin.php?role={$requested_role}");
        exit;
    }

    // -----------------------------
    // Require OTP
    // -----------------------------
    $otp = random_int(100000, 999999);
    $otp_expiry = date("Y-m-d H:i:s", time() + intval($security_settings['otp_expiry_minutes']) * 60);

    $upd = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
    $upd->bind_param("ssi", $otp, $otp_expiry, $user['id']);
    $upd->execute();

    $emailSent = sendOTPEmail($email, $otp, intval($security_settings['otp_expiry_minutes']));
    recordSecurityEvent($conn, $user['id'], $email, 'OTP_SENT', 'OTP sent to user for 2FA login', 'MEDIUM');

    // --- FIX: remove full login session, keep pending
    unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_role']);

    $_SESSION['pending_user_id'] = $user['id'];
    $_SESSION['pending_user_email'] = $email;
    $_SESSION['pending_role'] = $requested_role;
    $_SESSION['otp_expires'] = $otp_expiry;
    $_SESSION['require_2fa'] = true;
    $_SESSION['login_start_time'] = time();

    session_regenerate_id(true);

    header("Location: otp_verify.php");
    exit;
}
?>


<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login | MAO in Abra De Ilog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <style>
        .overlay {
            background-color: rgba(243, 244, 237, 0.9);
            backdrop-filter: blur(6px);
            animation: fadeIn 0.8s;
            transition: transform .3s, box-shadow .3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        nav {
            background-color: #166534;
            border-bottom: 4px solid #E6B800;
        }

        input:focus {
            box-shadow: 0 0 0 3px rgba(230, 184, 0, 0.3);
            transform: translateY(-1px);
        }

        .green-btn {
            background-color: #166534;
            color: white;
            transition: all .3s;
        }

        .green-btn:hover {
            background-color: #14532d;
            transform: translateY(-1px);
        }

        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, .2), rgba(0, 0, 0, .2)), url('../../img/mun.png');
            background-size: cover;
            background-position: center;
            filter: brightness(.9) blur(1px);
            z-index: -1;
        }

        .security-badge {
            background: rgba(22, 101, 52, 0.1);
            border: 1px solid #166534;
            color: #166534;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .twofa-badge {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid #dc2626;
            color: #dc2626;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">
    <div class="background-image"></div>
    <nav class="text-white shadow-lg fixed w-full z-20 top-0 left-0">
        <div class="flex items-center justify-between max-w-6xl mx-auto px-6 py-3">
            <div class="flex items-center space-x-3">
                <img src="../../img/logo.png" alt="LGU Logo" class="h-12 w-12 rounded-full border-2 border-white">
                <h1 class="text-lg font-semibold tracking-wide">Municipal Agriculture Office – Abra De Ilog</h1>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center relative mt-20 mb-8">
        <div class="overlay rounded-2xl shadow-2xl border border-[#166534] max-w-md w-full p-8 text-center">
            <div class="flex justify-center mb-4"><img src="../../img/logo.png" alt="Logo" class="w-20 h-20"></div>
            <h2 class="text-2xl font-bold text-[#166534] mb-1">ADMIN LOGIN PORTAL</h2>
            <p class="text-sm text-gray-700 mb-4">Municipal Agriculture Office – Abra de Ilog</p>



            <?php if (!empty($_SESSION['error'])): ?>
                <div class="bg-red-100 text-red-700 border border-red-400 px-4 py-2 rounded mb-4">
                    <?php echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['notice'])): ?>
                <div class="bg-yellow-100 text-yellow-800 border border-yellow-400 px-4 py-2 rounded mb-4">
                    <?php echo htmlspecialchars($_SESSION['notice']);
                    unset($_SESSION['notice']); ?>
                </div>
            <?php endif; ?>

            <form action="adminlogin.php" method="POST" class="space-y-2 text-left">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" required
                        class="block w-full border border-[#166534] rounded-lg p-2.5 text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required
                        class="block w-full border border-[#166534] rounded-lg p-2.5 text-sm"
                        minlength="<?= $security_settings['password_min_length'] ?>" />
                </div>

                <p class="text-right mt-1">
                    <a href="forgot_password.php" class="text-sm text-[#166534] hover:text-[#E6B800] font-medium">
                        Forgot Password?
                    </a>
                </p>

                <button type="submit" name="login"
                    class="green-btn w-full font-semibold py-2.5 rounded-lg mt-4 shadow-md flex items-center justify-center gap-2">
                    Sign In
                </button>

                <p class="text-sm text-gray-700 text-center mt-3">Don't have an account? <a href="account.php"
                        class="font-medium text-[#166534] hover:text-[#E6B800]">Click here</a></p>
            </form>

            <div class="mt-4 text-xs text-gray-700">
                © 2025 Municipal Agriculture Office <br>
                <span class="text-[#166534] font-semibold">Abra de Ilog Occidental Mindoro</span>
            </div>
            <div class="mt-4">
                <a href="../login.html" class="text-sm text-[#166534] hover:text-[#E6B800] font-medium">
                    &larr; Back to User Type Selection
                </a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>