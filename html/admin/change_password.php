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
    SELECT id, username, email, role, profile_image, created_at, last_login
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
// PASSWORD CHANGE HANDLER
// ===============================
$passwordError = "";
$passwordSuccess = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_password"])) {
    $currentPassword = trim($_POST["current_password"] ?? '');
    $newPassword = trim($_POST["new_password"] ?? '');
    $confirmPassword = trim($_POST["confirm_password"] ?? '');

    // Validation
    if (empty($currentPassword)) {
        $passwordError = "Current password is required.";
    } elseif (empty($newPassword)) {
        $passwordError = "New password is required.";
    } elseif (empty($confirmPassword)) {
        $passwordError = "Please confirm your new password.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $passwordError = "New password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $passwordError = "New password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $passwordError = "New password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $passwordError = "New password must contain at least one number.";
    } else {
        // Verify current password
        $pw_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $pw_stmt->bind_param("i", $user_id);
        $pw_stmt->execute();
        $pw_result = $pw_stmt->get_result()->fetch_assoc();

        if (!password_verify($currentPassword, $pw_result['password_hash'])) {
            $passwordError = "Current password is incorrect.";
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashedPassword, $user_id);

            if ($update_stmt->execute()) {
                $passwordSuccess = "Password changed successfully!";

                // Clear form fields
                $_POST = array();
            } else {
                $passwordError = "Database update failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - MAO Abra De Ilog</title>
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

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .requirement {
            transition: all 0.3s ease;
        }

        .requirement.met {
            color: #16a34a;
        }

        .requirement.unmet {
            color: #6b7280;
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
        <div class="max-w-4xl mx-auto p-6">

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
                    <h1 class="text-3xl font-bold text-gray-900">Change Password</h1>
                    <p class="text-gray-600 mt-2">Secure your account with a new password</p>
                </div>
                <button onclick="closeAndGoBack()"
                    class="mt-4 md:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-all duration-200 font-medium cursor-pointer z-50 relative">
                    <i class="fas fa-arrow-left"></i>
                    Back to Profile
                </button>
            </div>

            <!-- Password Change Card -->
            <div class="bg-white rounded-2xl shadow-sm card-hover p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-key text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Change Your Password</h2>
                        <p class="text-gray-600 text-sm">Update your password to keep your account secure</p>
                    </div>
                </div>

                <!-- Success Message -->
                <?php if (!empty($passwordSuccess)): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                        <p class="text-green-700 font-medium"><?= htmlspecialchars($passwordSuccess) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($passwordError)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                        <p class="text-red-700 font-medium"><?= htmlspecialchars($passwordError) ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <input type="hidden" name="change_password" value="1">

                    <div class="space-y-6">
                        <!-- Current Password -->
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Current Password
                            </label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password"
                                    value="<?= htmlspecialchars($_POST['current_password'] ?? '') ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10"
                                    placeholder="Enter your current password" required>
                                <button type="button" onclick="togglePassword('current_password')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password"
                                    value="<?= htmlspecialchars($_POST['new_password'] ?? '') ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10"
                                    placeholder="Enter your new password" onkeyup="checkPasswordStrength()" required>
                                <button type="button" onclick="togglePassword('new_password')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <!-- Password Strength Meter -->
                            <div class="mt-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-600">Password strength</span>
                                    <span id="strength-text" class="text-sm font-medium">Weak</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div id="strength-bar" class="password-strength bg-red-500 w-1/4 rounded-full">
                                    </div>
                                </div>
                            </div>

                            <!-- Password Requirements -->
                            <div class="mt-4 space-y-2">
                                <p class="text-sm font-medium text-gray-700 mb-2">Password requirements:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div class="requirement unmet flex items-center gap-2 text-sm">
                                        <i class="fas fa-circle text-xs"></i>
                                        <span>At least 8 characters</span>
                                    </div>
                                    <div class="requirement unmet flex items-center gap-2 text-sm">
                                        <i class="fas fa-circle text-xs"></i>
                                        <span>One uppercase letter</span>
                                    </div>
                                    <div class="requirement unmet flex items-center gap-2 text-sm">
                                        <i class="fas fa-circle text-xs"></i>
                                        <span>One lowercase letter</span>
                                    </div>
                                    <div class="requirement unmet flex items-center gap-2 text-sm">
                                        <i class="fas fa-circle text-xs"></i>
                                        <span>One number</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm New Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password"
                                    value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10"
                                    placeholder="Confirm your new password" onkeyup="checkPasswordMatch()" required>
                                <button type="button" onclick="togglePassword('confirm_password')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-match" class="mt-2 text-sm hidden">
                                <i class="fas fa-check text-green-500 mr-1"></i>
                                <span class="text-green-600">Passwords match</span>
                            </div>
                            <div id="password-mismatch" class="mt-2 text-sm hidden">
                                <i class="fas fa-times text-red-500 mr-1"></i>
                                <span class="text-red-600">Passwords do not match</span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit"
                            class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 font-medium transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            id="submit-btn">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                    </div>
                </form>

                <!-- Security Tips -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-3 text-lg">Security Tips</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-shield-alt text-green-500 mt-1"></i>
                            <span>Use a unique password that you don't use for other accounts</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-sync-alt text-green-500 mt-1"></i>
                            <span>Change your password regularly for better security</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-ban text-green-500 mt-1"></i>
                            <span>Avoid using personal information like your name or birthdate</span>
                        </li>
                    </ul>
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

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            const requirements = document.querySelectorAll('.requirement');

            let strength = 0;
            let messages = [];

            // Check length
            if (password.length >= 8) {
                strength += 25;
                requirements[0].classList.add('met');
                requirements[0].classList.remove('unmet');
                requirements[0].querySelector('i').className = 'fas fa-check text-green-500 text-xs';
            } else {
                requirements[0].classList.add('unmet');
                requirements[0].classList.remove('met');
                requirements[0].querySelector('i').className = 'fas fa-circle text-xs';
            }

            // Check uppercase
            if (/[A-Z]/.test(password)) {
                strength += 25;
                requirements[1].classList.add('met');
                requirements[1].classList.remove('unmet');
                requirements[1].querySelector('i').className = 'fas fa-check text-green-500 text-xs';
            } else {
                requirements[1].classList.add('unmet');
                requirements[1].classList.remove('met');
                requirements[1].querySelector('i').className = 'fas fa-circle text-xs';
            }

            // Check lowercase
            if (/[a-z]/.test(password)) {
                strength += 25;
                requirements[2].classList.add('met');
                requirements[2].classList.remove('unmet');
                requirements[2].querySelector('i').className = 'fas fa-check text-green-500 text-xs';
            } else {
                requirements[2].classList.add('unmet');
                requirements[2].classList.remove('met');
                requirements[2].querySelector('i').className = 'fas fa-circle text-xs';
            }

            // Check numbers
            if (/[0-9]/.test(password)) {
                strength += 25;
                requirements[3].classList.add('met');
                requirements[3].classList.remove('unmet');
                requirements[3].querySelector('i').className = 'fas fa-check text-green-500 text-xs';
            } else {
                requirements[3].classList.add('unmet');
                requirements[3].classList.remove('met');
                requirements[3].querySelector('i').className = 'fas fa-circle text-xs';
            }

            // Update strength bar and text
            strengthBar.style.width = strength + '%';

            if (strength < 50) {
                strengthBar.className = 'password-strength bg-red-500 rounded-full';
                strengthText.textContent = 'Weak';
                strengthText.className = 'text-sm font-medium text-red-600';
            } else if (strength < 75) {
                strengthBar.className = 'password-strength bg-yellow-500 rounded-full';
                strengthText.textContent = 'Fair';
                strengthText.className = 'text-sm font-medium text-yellow-600';
            } else if (strength < 100) {
                strengthBar.className = 'password-strength bg-blue-500 rounded-full';
                strengthText.textContent = 'Good';
                strengthText.className = 'text-sm font-medium text-blue-600';
            } else {
                strengthBar.className = 'password-strength bg-green-500 rounded-full';
                strengthText.textContent = 'Strong';
                strengthText.className = 'text-sm font-medium text-green-600';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('password-match');
            const mismatchElement = document.getElementById('password-mismatch');

            if (confirmPassword === '') {
                matchElement.classList.add('hidden');
                mismatchElement.classList.add('hidden');
                return;
            }

            if (password === confirmPassword) {
                matchElement.classList.remove('hidden');
                mismatchElement.classList.add('hidden');
            } else {
                matchElement.classList.add('hidden');
                mismatchElement.classList.remove('hidden');
            }
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