<?php
session_name("admin_session");
session_start();
require_once "../../db/conn.php";

// ----------------------------------------------------
// Security Headers
// ----------------------------------------------------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ----------------------------------------------------
// Authentication check
// ----------------------------------------------------

// User must be fully logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to access this page.";
    header("Location: adminlogin.php");
    exit;
}

// If user has pending OTP verification, redirect to OTP page
if (!empty($_SESSION['pending_user_id'])) {
    header("Location: otp_verify.php");
    exit;
}

// ----------------------------------------------------
// At this point, user is fully authenticated
// ----------------------------------------------------
$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_id = (int) $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("
    SELECT id, username, email, role, profile_image, created_at, last_login, 
           failed_attempts, locked_until, is_verified
    FROM users 
    WHERE id = ? AND is_active = 1 LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: adminlogin.php?error=account_not_found");
    exit;
}

// Set profile image path
$profileImg = (!empty($user["profile_image"]) && file_exists("uploads/profile/" . $user["profile_image"]))
    ? "uploads/profile/" . $user["profile_image"]
    : "../../img/profile.png";

// ===============================
// CREATE MISSING SECURITY COLUMNS IF THEY DON'T EXIST
// ===============================
function createSecurityColumns($conn) {
    $columns = [
        "two_factor_enabled" => "INT DEFAULT 0",
        "session_timeout" => "INT DEFAULT 30",
        "single_session" => "INT DEFAULT 0",
        "email_alerts" => "INT DEFAULT 1",
        "sms_alerts" => "INT DEFAULT 0",
        "new_device_alerts" => "INT DEFAULT 1",
        "recovery_email" => "VARCHAR(255) DEFAULT ''",
        "security_question" => "VARCHAR(255) DEFAULT ''",
        "security_answer" => "VARCHAR(255) DEFAULT ''"
    ];
    
    foreach ($columns as $column => $definition) {
        try {
            $check_sql = "SHOW COLUMNS FROM users LIKE '$column'";
            $result = $conn->query($check_sql);
            
            if ($result->num_rows == 0) {
                $alter_sql = "ALTER TABLE users ADD COLUMN $column $definition";
                $conn->query($alter_sql);
            }
        } catch (Exception $e) {
            // Column might already exist or other error, continue
            error_log("Column creation error for $column: " . $e->getMessage());
        }
    }
}

// Create missing columns
createSecurityColumns($conn);

// ===============================
// SECURITY SETTINGS HANDLER
// ===============================
$securityError = "";
$securitySuccess = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Two-Factor Authentication Toggle
    if (isset($_POST["toggle_2fa"])) {
        $enable_2fa = isset($_POST["enable_2fa"]) ? 1 : 0;
        
        try {
            $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $enable_2fa, $user_id);
            
            if ($update_stmt->execute()) {
                $securitySuccess = $enable_2fa ? "Two-factor authentication enabled!" : "Two-factor authentication disabled!";
            } else {
                $securityError = "Failed to update two-factor authentication settings.";
            }
        } catch (Exception $e) {
            $securityError = "Error updating two-factor authentication settings.";
        }
    }
    
    // Session Settings
    if (isset($_POST["update_session_settings"])) {
        $session_timeout = (int) $_POST["session_timeout"];
        $single_session = isset($_POST["single_session"]) ? 1 : 0;
        
        // Validate session timeout
        if ($session_timeout < 5 || $session_timeout > 480) {
            $securityError = "Session timeout must be between 5 and 480 minutes.";
        } else {
            try {
                $update_stmt = $conn->prepare("UPDATE users SET session_timeout = ?, single_session = ? WHERE id = ?");
                $update_stmt->bind_param("iii", $session_timeout, $single_session, $user_id);
                
                if ($update_stmt->execute()) {
                    $securitySuccess = "Session settings updated successfully!";
                } else {
                    $securityError = "Failed to update session settings.";
                }
            } catch (Exception $e) {
                $securityError = "Error updating session settings.";
            }
        }
    }
    
    // Login Alerts
    if (isset($_POST["update_login_alerts"])) {
        $email_alerts = isset($_POST["email_alerts"]) ? 1 : 0;
        $sms_alerts = isset($_POST["sms_alerts"]) ? 1 : 0;
        $new_device_alerts = isset($_POST["new_device_alerts"]) ? 1 : 0;
        
        try {
            $update_stmt = $conn->prepare("UPDATE users SET email_alerts = ?, sms_alerts = ?, new_device_alerts = ? WHERE id = ?");
            $update_stmt->bind_param("iiii", $email_alerts, $sms_alerts, $new_device_alerts, $user_id);
            
            if ($update_stmt->execute()) {
                $securitySuccess = "Login alert preferences updated!";
            } else {
                $securityError = "Failed to update alert preferences.";
            }
        } catch (Exception $e) {
            $securityError = "Error updating alert preferences.";
        }
    }
    
    // Account Recovery
    if (isset($_POST["update_recovery_settings"])) {
        $recovery_email = trim($_POST["recovery_email"] ?? '');
        $security_question = trim($_POST["security_question"] ?? '');
        $security_answer = trim($_POST["security_answer"] ?? '');
        
        if (!empty($recovery_email) && !filter_var($recovery_email, FILTER_VALIDATE_EMAIL)) {
            $securityError = "Please enter a valid recovery email address.";
        } else {
            $hashed_answer = !empty($security_answer) ? password_hash($security_answer, PASSWORD_DEFAULT) : null;
            
            try {
                $update_stmt = $conn->prepare("UPDATE users SET recovery_email = ?, security_question = ?, security_answer = ? WHERE id = ?");
                $update_stmt->bind_param("sssi", $recovery_email, $security_question, $hashed_answer, $user_id);
                
                if ($update_stmt->execute()) {
                    $securitySuccess = "Account recovery settings updated!";
                } else {
                    $securityError = "Failed to update recovery settings.";
                }
            } catch (Exception $e) {
                $securityError = "Error updating recovery settings.";
            }
        }
    }
}

// ===============================
// FETCH CURRENT SECURITY SETTINGS WITH FALLBACK DEFAULTS
// ===============================
$settings = [
    'two_factor_enabled' => 0,
    'session_timeout' => 30,
    'single_session' => 0,
    'email_alerts' => 1,
    'sms_alerts' => 0,
    'new_device_alerts' => 1,
    'recovery_email' => '',
    'security_question' => ''
];

try {
    $settings_stmt = $conn->prepare("
        SELECT two_factor_enabled, session_timeout, single_session, email_alerts, 
               sms_alerts, new_device_alerts, recovery_email, security_question
        FROM users WHERE id = ?
    ");
    $settings_stmt->bind_param("i", $user_id);
    $settings_stmt->execute();
    $db_settings = $settings_stmt->get_result()->fetch_assoc();
    
    if ($db_settings) {
        $settings = array_merge($settings, $db_settings);
    }
} catch (Exception $e) {
    // Use default settings if query fails
    error_log("Error fetching security settings: " . $e->getMessage());
}

// ===============================
// CREATE LOGIN HISTORY TABLE IF IT DOESN'T EXIST
// ===============================
try {
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            success TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    $conn->query($create_table_sql);
} catch (Exception $e) {
    error_log("Error creating login_history table: " . $e->getMessage());
}

// Fetch recent login history
$login_history = [];
try {
    $history_stmt = $conn->prepare("
        SELECT login_time, ip_address, user_agent, success 
        FROM login_history 
        WHERE user_id = ? 
        ORDER BY login_time DESC 
        LIMIT 10
    ");
    $history_stmt->bind_param("i", $user_id);
    $history_stmt->execute();
    $login_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching login history: " . $e->getMessage());
    // Continue with empty login history
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - MAO Abra De Ilog</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

        .profile-gradient {
            background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .toggle-checkbox:checked {
            right: 0;
            border-color: #16a34a;
            background-color: #16a34a;
        }

        .toggle-checkbox:checked + .toggle-label {
            background-color: #16a34a;
        }

        .security-level {
            transition: all 0.3s ease;
        }

        .security-level.active {
            border-color: #16a34a;
            background-color: #f0fdf4;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <nav class="bg-[#166534] text-white shadow-lg fixed w-full z-50 top-0 left-0 border-b-4 border-[#E6B800]">
        <div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-3">
            <!-- Left: Logo & Drawer Toggle -->
            <div class="flex items-center space-x-3">
                <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                    aria-controls="drawer-navigation"
                    class="p-2 text-white rounded-lg cursor-pointer md:hidden hover:bg-[#14532d] focus:ring-2 focus:ring-[#E6B800]">
                    <!-- Menu icon -->
                    <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <span class="sr-only">Toggle sidebar</span>
                </button>

                <img src="../../img/logo.png" alt="LGU Logo"
                    class="h-12 w-12 rounded-full border-2 border-white bg-white">
                <h1 class="text-lg font-semibold tracking-wide">
                    Municipal Agriculture Office – Abra De Ilog
                </h1>
            </div>

            <!-- Right: Profile Dropdown -->
            <div class="flex items-center space-x-3 relative select-none">
                <button id="user-menu-button" class="flex items-center rounded-full ring-2 ring-transparent hover:ring-[#FFD447] 
        transition-all duration-200 p-[3px]" onclick="toggleUserDropdown()">
                    <img class="w-11 h-11 rounded-full shadow-md border-2 border-white" src="../../img/profile.png"
                        alt="User photo">
                </button>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside id="drawer-navigation"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200"
        aria-label="Sidenav">
        <div class="overflow-y-auto py-5 px-3 h-full bg-white">
            <ul class="space-y-2">
                <li>
                    <a href="index.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                            <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                        </svg>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="adddata.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd"
                                d="M9 2.221V7H4.221a2 2 0 0 1 .365-.5L8.5 2.586A2 2 0 0 1 9 2.22ZM11 2v5a2 2 0 0 1-2 2H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-7Z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="ml-3">Add Data</span>
                    </a>
                </li>

                <li>
                    <a href="datalist.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-3">Data List</span>
                    </a>
                </li>
                
                <li>
                    <a href="report.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-3">Reports</span>
                    </a>
                </li>

                <li>
                    <a href="archived.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0-2-2v-4z">
                            </path>
                        </svg>
                        <span class="ml-3">Archived</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="pt-20 md:ml-64 min-h-screen">
        <div class="max-w-6xl mx-auto p-6">

            <!-- Success Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div
                    class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3 animate-fade-in">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-green-800 font-medium">
                            <?= htmlspecialchars($_SESSION['success']);
                            unset($_SESSION['success']); ?>
                        </p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Security Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your account security and privacy</p>
                </div>
                <button onclick="closeAndGoBack()"
                    class="mt-4 md:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-all duration-200 font-medium cursor-pointer z-50 relative">
                    <i class="fas fa-arrow-left"></i>
                    Back to Profile
                </button>
            </div>

            <!-- Security Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Security Score -->
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Security Score</p>
                            <p class="text-2xl font-bold text-gray-900">85%</p>
                        </div>
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                </div>

                <!-- Two-Factor Status -->
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 <?= $settings['two_factor_enabled'] ? 'border-green-500' : 'border-yellow-500' ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">2FA Status</p>
                            <p class="text-lg font-bold <?= $settings['two_factor_enabled'] ? 'text-green-600' : 'text-yellow-600' ?>">
                                <?= $settings['two_factor_enabled'] ? 'Enabled' : 'Disabled' ?>
                            </p>
                        </div>
                        <div class="w-16 h-16 <?= $settings['two_factor_enabled'] ? 'bg-green-100' : 'bg-yellow-100' ?> rounded-full flex items-center justify-center">
                            <i class="fas fa-mobile-alt <?= $settings['two_factor_enabled'] ? 'text-green-600' : 'text-yellow-600' ?> text-xl"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <?= $settings['two_factor_enabled'] ? 'Extra security layer active' : 'Add an extra security layer' ?>
                    </p>
                </div>

                <!-- Last Login -->
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Last Login</p>
                            <p class="text-lg font-bold text-gray-900">
                                <?= $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'Never' ?>
                            </p>
                        </div>
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-sign-in-alt text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <?= $user['last_login'] ? 'From your current session' : 'No login recorded' ?>
                    </p>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($securityError)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                    <p class="text-red-700 font-medium"><?= htmlspecialchars($securityError) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($securitySuccess)): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                    <p class="text-green-700 font-medium"><?= htmlspecialchars($securitySuccess) ?></p>
                </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-2 gap-8">

                <!-- Two-Factor Authentication -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-mobile-alt text-blue-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Two-Factor Authentication</h2>
                            <p class="text-gray-600 text-sm">Add an extra layer of security to your account</p>
                        </div>
                    </div>

                    <form action="" method="POST">
                        <input type="hidden" name="toggle_2fa" value="1">
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Enable 2FA</h3>
                                    <p class="text-sm text-gray-600">Require a code from your phone to log in</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="enable_2fa" value="1" 
                                        <?= $settings['two_factor_enabled'] ? 'checked' : '' ?>
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                        peer-checked:after:translate-x-full peer-checked:after:border-white 
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                        after:bg-white after:border-gray-300 after:border after:rounded-full 
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                            </div>

                            <?php if ($settings['two_factor_enabled']): ?>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                        <div>
                                            <p class="font-medium text-green-800">2FA is active</p>
                                            <p class="text-sm text-green-600">Your account is protected with two-factor authentication</p>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                        <div>
                                            <p class="font-medium text-yellow-800">2FA is inactive</p>
                                            <p class="text-sm text-yellow-600">Enable for better account protection</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button type="submit"
                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                Update 2FA Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Session Management -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-clock text-purple-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Session Settings</h2>
                            <p class="text-gray-600 text-sm">Control how long you stay logged in</p>
                        </div>
                    </div>

                    <form action="" method="POST">
                        <input type="hidden" name="update_session_settings" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="session_timeout" class="block text-sm font-medium text-gray-700 mb-2">
                                    Session Timeout (minutes)
                                </label>
                                <input type="number" id="session_timeout" name="session_timeout" 
                                    value="<?= $settings['session_timeout'] ?>"
                                    min="5" max="480"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="30">
                                <p class="text-xs text-gray-500 mt-1">Automatically log out after inactivity (5-480 minutes)</p>
                            </div>

                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Single Session</h3>
                                    <p class="text-sm text-gray-600">Log out other devices when you log in</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="single_session" value="1" 
                                        <?= $settings['single_session'] ? 'checked' : '' ?>
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                        peer-checked:after:translate-x-full peer-checked:after:border-white 
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                        after:bg-white after:border-gray-300 after:border after:rounded-full 
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                            </div>

                            <button type="submit"
                                class="w-full bg-purple-600 text-white px-4 py-3 rounded-lg hover:bg-purple-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                Update Session Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Login Alerts -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <i class="fas fa-bell text-orange-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Login Alerts</h2>
                            <p class="text-gray-600 text-sm">Get notified about account activity</p>
                        </div>
                    </div>

                    <form action="" method="POST">
                        <input type="hidden" name="update_login_alerts" value="1">
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Email Alerts</h3>
                                    <p class="text-sm text-gray-600">Receive login notifications via email</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="email_alerts" value="1" 
                                        <?= $settings['email_alerts'] ? 'checked' : '' ?>
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                        peer-checked:after:translate-x-full peer-checked:after:border-white 
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                        after:bg-white after:border-gray-300 after:border after:rounded-full 
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-gray-900">SMS Alerts</h3>
                                    <p class="text-sm text-gray-600">Receive login notifications via SMS</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="sms_alerts" value="1" 
                                        <?= $settings['sms_alerts'] ? 'checked' : '' ?>
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                        peer-checked:after:translate-x-full peer-checked:after:border-white 
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                        after:bg-white after:border-gray-300 after:border after:rounded-full 
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <h3 class="font-semibold text-gray-900">New Device Alerts</h3>
                                    <p class="text-sm text-gray-600">Alert when logging in from new devices</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="new_device_alerts" value="1" 
                                        <?= $settings['new_device_alerts'] ? 'checked' : '' ?>
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                        peer-checked:after:translate-x-full peer-checked:after:border-white 
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                        after:bg-white after:border-gray-300 after:border after:rounded-full 
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                    </div>
                                </label>
                            </div>

                            <button type="submit"
                                class="w-full bg-orange-600 text-white px-4 py-3 rounded-lg hover:bg-orange-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                Update Alert Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Recovery -->
                <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-life-ring text-green-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Account Recovery</h2>
                            <p class="text-gray-600 text-sm">Set up recovery options for your account</p>
                        </div>
                    </div>

                    <form action="" method="POST">
                        <input type="hidden" name="update_recovery_settings" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="recovery_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Recovery Email
                                </label>
                                <input type="email" id="recovery_email" name="recovery_email" 
                                    value="<?= htmlspecialchars($settings['recovery_email']) ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="backup@email.com">
                                <p class="text-xs text-gray-500 mt-1">Used for account recovery and security notifications</p>
                            </div>

                            <div>
                                <label for="security_question" class="block text-sm font-medium text-gray-700 mb-2">
                                    Security Question
                                </label>
                                <select id="security_question" name="security_question"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="">Select a security question</option>
                                    <option value="What was your first pet's name?" <?= $settings['security_question'] == "What was your first pet's name?" ? 'selected' : '' ?>>What was your first pet's name?</option>
                                    <option value="What city were you born in?" <?= $settings['security_question'] == "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
                                    <option value="What is your mother's maiden name?" <?= $settings['security_question'] == "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
                                    <option value="What was your first school?" <?= $settings['security_question'] == "What was your first school?" ? 'selected' : '' ?>>What was your first school?</option>
                                    <option value="What is your favorite book?" <?= $settings['security_question'] == "What is your favorite book?" ? 'selected' : '' ?>>What is your favorite book?</option>
                                </select>
                            </div>

                            <div>
                                <label for="security_answer" class="block text-sm font-medium text-gray-700 mb-2">
                                    Security Answer
                                </label>
                                <input type="text" id="security_answer" name="security_answer" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Your answer">
                                <p class="text-xs text-gray-500 mt-1">Answer to your security question</p>
                            </div>

                            <button type="submit"
                                class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                Update Recovery Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Login Activity -->
            <div class="bg-white rounded-2xl shadow-sm card-hover p-6 mt-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <i class="fas fa-history text-gray-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Recent Login Activity</h2>
                        <p class="text-gray-600 text-sm">Monitor your account access history</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-4 py-3">Date & Time</th>
                                <th class="px-4 py-3">IP Address</th>
                                <th class="px-4 py-3">Device/Browser</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($login_history)): ?>
                                <?php foreach ($login_history as $login): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <?= date('M j, Y g:i A', strtotime($login['login_time'])) ?>
                                        </td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($login['ip_address']) ?></td>
                                        <td class="px-4 py-3">
                                            <span class="truncate max-w-xs block" title="<?= htmlspecialchars($login['user_agent']) ?>">
                                                <?= htmlspecialchars(substr($login['user_agent'], 0, 50)) ?>...
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $login['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $login['success'] ? 'Success' : 'Failed' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        No login history available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <a href="login_history.php" 
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-600 hover:text-green-700">
                        View Full History
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mt-8">
                <div class="flex items-center gap-3 mb-4">
                    <i class="fas fa-lightbulb text-blue-600 text-xl"></i>
                    <h3 class="text-lg font-bold text-blue-900">Security Best Practices</h3>
                </div>
                <div class="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle mt-1"></i>
                        <span>Use a strong, unique password and change it regularly</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle mt-1"></i>
                        <span>Enable two-factor authentication for extra security</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle mt-1"></i>
                        <span>Regularly review your login activity</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle mt-1"></i>
                        <span>Keep your recovery information up to date</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        function closeAndGoBack() {
            window.location.href = 'profile.php';
        }

        // Set active sidebar link
        document.addEventListener('DOMContentLoaded', function () {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('#drawer-navigation a');

            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.remove('text-gray-900', 'hover:bg-green-100');
                    link.classList.add('bg-green-50', 'border-l-4', 'border-[#E6B800]', 'text-[#166534]');
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>