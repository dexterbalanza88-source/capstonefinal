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

// Fetch user info for profile dropdown
$user_stmt = $conn->prepare("
    SELECT id, username, email, role, profile_image 
    FROM users 
    WHERE id = ? AND is_active = 1 LIMIT 1
");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

if (!$current_user) {
    session_destroy();
    header("Location: adminlogin.php?error=account_not_found");
    exit;
}

// Set profile image path
$profileImg = (!empty($current_user["profile_image"]) && file_exists("uploads/profile/" . $current_user["profile_image"]))
    ? "uploads/profile/" . $current_user["profile_image"]
    : "../../img/profile.png";

// ===============================
// CREATE TABLES IF THEY DON'T EXIST
// ===============================
function createActivityLogsTable($conn)
{
    try {
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                username VARCHAR(255) NOT NULL,
                user_role VARCHAR(50) NOT NULL,
                action_type VARCHAR(100) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, ARCHIVE, RESTORE, LOGIN, LOGOUT, etc.',
                action_description TEXT NOT NULL,
                table_name VARCHAR(100) COMMENT 'Which table was affected',
                record_id INT COMMENT 'ID of the affected record',
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        $conn->query($create_table_sql);

        // Add missing columns if they don't exist
        $columns_to_add = [
            "table_name" => "VARCHAR(100) DEFAULT NULL",
            "record_id" => "INT DEFAULT NULL",
            "user_role" => "VARCHAR(50) NOT NULL DEFAULT ''"
        ];

        foreach ($columns_to_add as $column => $definition) {
            try {
                $check_sql = "SHOW COLUMNS FROM activity_logs LIKE '$column'";
                $result = $conn->query($check_sql);

                if ($result->num_rows == 0) {
                    $alter_sql = "ALTER TABLE activity_logs ADD COLUMN $column $definition";
                    $conn->query($alter_sql);
                }
            } catch (Exception $e) {
                // Column might already exist, continue
                error_log("Activity log column creation error for $column: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Error creating activity_logs table: " . $e->getMessage());
    }
}

function createLoginHistoryTable($conn)
{
    try {
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                username VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                role VARCHAR(50) DEFAULT 'USER',
                login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                logout_time DATETIME NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NOT NULL,
                success TINYINT(1) DEFAULT 1,
                status VARCHAR(20) DEFAULT 'SUCCESS',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $conn->query($create_table_sql);

        // Add missing columns if they don't exist
        $columns_to_add = [
            "success" => "TINYINT(1) DEFAULT 1",
            "status" => "VARCHAR(20) DEFAULT 'SUCCESS'",
            "username" => "VARCHAR(255) NOT NULL DEFAULT ''",
            "email" => "VARCHAR(255) DEFAULT ''",
            "role" => "VARCHAR(50) DEFAULT 'USER'"
        ];

        foreach ($columns_to_add as $column => $definition) {
            try {
                $check_sql = "SHOW COLUMNS FROM login_history LIKE '$column'";
                $result = $conn->query($check_sql);

                if ($result->num_rows == 0) {
                    $alter_sql = "ALTER TABLE login_history ADD COLUMN $column $definition";
                    $conn->query($alter_sql);
                }
            } catch (Exception $e) {
                error_log("Column creation error for $column: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Error creating login_history table: " . $e->getMessage());
    }
}

// Create both tables
createLoginHistoryTable($conn);
createActivityLogsTable($conn);

// ===============================
// STATISTICS DATA
// ===============================

// Get current tab from URL
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login-history';

// LOGIN HISTORY STATS
try {
    $total_logins_result = $conn->query("SELECT COUNT(*) as total FROM login_history");
    $total_logins = $total_logins_result ? $total_logins_result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $total_logins = 0;
}

try {
    $active_sessions_result = $conn->query("SELECT COUNT(*) as active FROM login_history WHERE logout_time IS NULL");
    $active_sessions = $active_sessions_result ? $active_sessions_result->fetch_assoc()['active'] : 0;
} catch (Exception $e) {
    $active_sessions = 0;
}

try {
    $failed_attempts_result = $conn->query("SELECT COUNT(*) as failed FROM login_history WHERE status = 'FAILED' OR success = 0");
    $failed_attempts = $failed_attempts_result ? $failed_attempts_result->fetch_assoc()['failed'] : 0;
} catch (Exception $e) {
    $failed_attempts = 0;
}

try {
    $unique_users_result = $conn->query("SELECT COUNT(DISTINCT user_id) as unique_users FROM login_history");
    $unique_users = $unique_users_result ? $unique_users_result->fetch_assoc()['unique_users'] : 0;
} catch (Exception $e) {
    $unique_users = 0;
}

// ACTIVITY LOGS STATS
try {
    $total_activities_result = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
    $total_activities = $total_activities_result ? $total_activities_result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $total_activities = 0;
}

try {
    $today_activities_result = $conn->query("SELECT COUNT(*) as today FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $today_activities = $today_activities_result ? $today_activities_result->fetch_assoc()['today'] : 0;
} catch (Exception $e) {
    $today_activities = 0;
}

try {
    $unique_actors_result = $conn->query("SELECT COUNT(DISTINCT user_id) as unique_actors FROM activity_logs");
    $unique_actors = $unique_actors_result ? $unique_actors_result->fetch_assoc()['unique_actors'] : 0;
} catch (Exception $e) {
    $unique_actors = 0;
}

try {
    $top_action_result = $conn->query("
        SELECT action_type, COUNT(*) as count 
        FROM activity_logs 
        GROUP BY action_type 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $top_action = $top_action_result ? $top_action_result->fetch_assoc() : ['action_type' => 'N/A', 'count' => 0];
} catch (Exception $e) {
    $top_action = ['action_type' => 'N/A', 'count' => 0];
}

// ===============================
// FETCH DATA BASED ON CURRENT TAB
// ===============================

// Fetch login history data
$login_history_data = [];
try {
    $login_query = "
        SELECT lh.*, u.username as actual_username, u.email as actual_email, u.role as actual_role
        FROM login_history lh
        LEFT JOIN users u ON lh.user_id = u.id
        ORDER BY lh.login_time DESC
        LIMIT 50
    ";
    $login_result = $conn->query($login_query);
    if ($login_result) {
        while ($row = $login_result->fetch_assoc()) {
            $login_history_data[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching login history: " . $e->getMessage());
}

// Fetch activity logs data
$activity_logs_data = [];
try {
    $activity_query = "
        SELECT al.*, u.username as actual_username, u.role as actual_role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 50
    ";
    $activity_result = $conn->query($activity_query);
    if ($activity_result) {
        while ($row = $activity_result->fetch_assoc()) {
            $activity_logs_data[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching activity logs: " . $e->getMessage());
}

// Get action types for filter
$action_types = [];
try {
    $action_types_result = $conn->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type");
    if ($action_types_result) {
        while ($row = $action_types_result->fetch_assoc()) {
            $action_types[] = $row['action_type'];
        }
    }
} catch (Exception $e) {
    // If no action types, use default ones
    $action_types = ['CREATE', 'UPDATE', 'DELETE', 'ARCHIVE', 'RESTORE', 'LOGIN', 'LOGOUT'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - MAO Abra De Ilog</title>
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

        /* Tab styling */
        .tab-link {
            transition: all 0.2s ease;
            position: relative;
        }

        .tab-link.active {
            color: #166534;
            border-bottom: 3px solid #166534;
        }

        .tab-link.active:after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            right: 0;
            height: 3px;
            background: #166534;
            border-radius: 3px 3px 0 0;
        }

        /* Animation for new entries */
        .new-entry {
            animation: highlight 2s ease-out;
        }

        @keyframes highlight {
            0% {
                background-color: rgba(34, 197, 94, 0.3);
            }

            100% {
                background-color: transparent;
            }
        }

        /* Action type badges */
        .badge-create {
            background-color: #10b981;
            color: white;
        }

        .badge-update {
            background-color: #3b82f6;
            color: white;
        }

        .badge-delete {
            background-color: #ef4444;
            color: white;
        }

        .badge-archive {
            background-color: #f59e0b;
            color: white;
        }

        .badge-restore {
            background-color: #8b5cf6;
            color: white;
        }

        .badge-login {
            background-color: #059669;
            color: white;
        }

        .badge-logout {
            background-color: #6366f1;
            color: white;
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
                    <img class="w-11 h-11 rounded-full shadow-md border-2 border-white" src="<?= $profileImg ?>"
                        alt="User photo">
                </button>

                <!-- DROPDOWN MENU -->
                <div id="userDropdown" class="hidden absolute right-0 top-14 w-62 bg-white rounded-xl shadow-xl 
        border border-gray-200 z-50 transform opacity-0 scale-95 transition-all duration-200 origin-top-right">
                    <!-- HEADER -->
                    <div class="py-2 px-2 border-b bg-gray-50 rounded-t-xl text-center">
                        <img src="<?= $profileImg ?>"
                            class="w-16 h-16 mx-auto rounded-full border-2 border-white shadow" alt="">
                        <div class="mt-3 text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars($current_user['username'] ?? 'Guest'); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($current_user['email'] ?? ''); ?>
                        </div>
                    </div>

                    <!-- MENU -->
                    <ul class="py-2">
                        <li>
                            <a href="profile.php" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 
                    hover:bg-gray-100 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M12 12a4 4 0 100-8 4 4 0 000 8z" />
                                </svg>
                                My Profile
                            </a>
                        </li>

                        <li>
                            <a href="login_history.php" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 
                    hover:bg-gray-100 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10m-9 4h6M5 21h14a2 2 0 002-2v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7a2 2 0 002 2z" />
                                </svg>
                                Activity Logs
                            </a>
                        </li>

                        <li class="border-t my-2"></li>

                        <li class="px-5 pb-1 text-xs font-semibold text-gray-500 uppercase">
                            Admin Tools
                        </li>

                        <li>
                            <a href="user_management.php" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 
                    hover:bg-gray-100 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5V4h-5M2 20h15V4H2v16zm5-9h5m-5 4h8m-8-8h8" />
                                </svg>
                                User Management
                            </a>
                        </li>

                        <li class="border-t my-2"></li>

                        <li>
                            <a href="logout.php" class="flex items-center gap-3 px-5 py-3 text-sm text-red-600 
                    hover:bg-red-100 transition rounded-lg font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1" />
                                </svg>
                                Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
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
        <div class="max-w-7xl mx-auto p-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">System Activity Logs</h1>
                    <p class="text-gray-600 mt-2">Monitor login activities and user actions</p>
                </div>
                <button onclick="closeAndGoBack()"
                    class="mt-4 md:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-gray-700 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-all duration-200 font-medium cursor-pointer z-50 relative">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </button>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
                <div class="border-b border-gray-200">
                    <div class="flex">
                        <a href="?tab=login-history"
                            class="tab-link flex-1 text-center py-4 px-6 font-medium text-gray-500 hover:text-gray-700 <?= $current_tab == 'login-history' ? 'active' : '' ?>">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login History
                        </a>
                        <a href="?tab=activity-logs"
                            class="tab-link flex-1 text-center py-4 px-6 font-medium text-gray-500 hover:text-gray-700 <?= $current_tab == 'activity-logs' ? 'active' : '' ?>">
                            <i class="fas fa-history mr-2"></i>Activity Logs
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($current_tab == 'login-history'): ?>
                <!-- LOGIN HISTORY TAB -->
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Logins</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $total_logins ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-sign-in-alt text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Active Sessions</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $active_sessions ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-check text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Failed Attempts</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $failed_attempts ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Unique Users</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $unique_users ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login History Table -->
                <div class="bg-white rounded-2xl shadow-sm card-hover overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-gray-100 rounded-lg">
                                <i class="fas fa-sign-in-alt text-gray-600"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Recent Login Activity</h2>
                                <p class="text-gray-600 text-sm">All login attempts across the system</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">User</th>
                                    <th class="px-6 py-4 font-semibold">Role</th>
                                    <th class="px-6 py-4 font-semibold">Login Time</th>
                                    <th class="px-6 py-4 font-semibold">Logout Time</th>
                                    <th class="px-6 py-4 font-semibold">IP Address</th>
                                    <th class="px-6 py-4 font-semibold">Status</th>
                                    <th class="px-6 py-4 font-semibold">Device/Browser</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (!empty($login_history_data)): ?>
                                    <?php foreach ($login_history_data as $row): ?>
                                        <?php
                                        $hasSuccess = isset($row['success']);
                                        $hasStatus = isset($row['status']);

                                        $isSuccess = $hasSuccess ? $row['success'] : true;
                                        $statusText = $hasStatus ? $row['status'] : ($isSuccess ? 'SUCCESS' : 'FAILED');

                                        $statusColor = ($statusText === 'SUCCESS' || $isSuccess)
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800';

                                        $role = $row['actual_role'] ?? $row['role'] ?? 'USER';
                                        $roleColor = ($role === "ADMIN")
                                            ? "bg-blue-100 text-blue-800"
                                            : "bg-green-100 text-green-800";

                                        $username = $row['actual_username'] ?? $row['username'] ?? 'Unknown User';
                                        $email = $row['actual_email'] ?? $row['email'] ?? 'No email';

                                        $loginTime = date('M j, Y g:i A', strtotime($row['login_time']));
                                        $logoutTime = $row['logout_time']
                                            ? date('M j, Y g:i A', strtotime($row['logout_time']))
                                            : '<span class="text-gray-400 italic">Still active</span>';

                                        $userAgent = strlen($row['user_agent']) > 50
                                            ? substr($row['user_agent'], 0, 50) . '...'
                                            : $row['user_agent'];
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-8 h-8 flex items-center justify-center bg-gray-100 text-gray-600 rounded-full">
                                                        <i class="fas fa-user text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($username) ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?= htmlspecialchars($email) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $roleColor ?>">
                                                    <?= htmlspecialchars($role) ?>
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                <?= $loginTime ?>
                                            </td>

                                            <td class="px-6 py-4 text-gray-700">
                                                <?= $logoutTime ?>
                                            </td>

                                            <td class="px-6 py-4 font-mono text-sm text-gray-600">
                                                <?= htmlspecialchars($row['ip_address']) ?>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                                                    <i
                                                        class="fas <?= ($statusText === 'SUCCESS' || $isSuccess) ? 'fa-check' : 'fa-times' ?> mr-1"></i>
                                                    <?= $statusText ?>
                                                </span>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span class="text-xs text-gray-600"
                                                    title="<?= htmlspecialchars($row['user_agent']) ?>">
                                                    <?= htmlspecialchars($userAgent) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center gap-2">
                                                <i class="fas fa-history text-3xl text-gray-300"></i>
                                                <p class="text-lg font-medium text-gray-400">No login history available</p>
                                                <p class="text-sm text-gray-500">Login activities will appear here once users
                                                    start logging in.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- ACTIVITY LOGS TAB -->
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Activities</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $total_activities ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Today's Activities</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $today_activities ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Unique Actors</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?= $unique_actors ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-friends text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Most Frequent Action</p>
                                <p class="text-xl font-bold text-gray-900 truncate"
                                    title="<?= htmlspecialchars($top_action['action_type']) ?>">
                                    <?= htmlspecialchars($top_action['action_type']) ?>
                                </p>
                                <p class="text-sm text-gray-500">(<?= $top_action['count'] ?> times)</p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-star text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-2">
                            <button onclick="filterActivities('all')"
                                class="px-4 py-2 bg-[#166534] text-white rounded-lg hover:bg-[#14532d] transition-colors">
                                <i class="fas fa-list mr-2"></i>All Activities
                            </button>
                            <?php foreach ($action_types as $action_type): ?>
                                <button onclick="filterActivities('<?= $action_type ?>')"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    <?= htmlspecialchars($action_type) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="exportActivities()"
                                class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-download mr-2"></i>Export
                            </button>
                            <button onclick="refreshActivities()"
                                class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div class="bg-white rounded-2xl shadow-sm card-hover overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-gray-100 rounded-lg">
                                <i class="fas fa-tasks text-gray-600"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">System Activity Logs</h2>
                                <p class="text-gray-600 text-sm">Track all user actions and system activities</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Timestamp</th>
                                    <th class="px-6 py-4 font-semibold">User</th>
                                    <th class="px-6 py-4 font-semibold">Action Type</th>
                                    <th class="px-6 py-4 font-semibold">Description</th>
                                    <th class="px-6 py-4 font-semibold">Affected Resource</th>
                                    <th class="px-6 py-4 font-semibold">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="activityLogsTable">
                                <?php if (!empty($activity_logs_data)): ?>
                                    <?php foreach ($activity_logs_data as $row): ?>
                                        <?php
                                        $actionType = $row['action_type'] ?? 'UNKNOWN';
                                        $badgeClass = 'badge-' . strtolower($actionType);
                                        if (!in_array($badgeClass, ['badge-create', 'badge-update', 'badge-delete', 'badge-archive', 'badge-restore', 'badge-login', 'badge-logout'])) {
                                            $badgeClass = 'bg-gray-100 text-gray-800';
                                        }

                                        $username = $row['actual_username'] ?? $row['username'] ?? 'System';
                                        $userRole = $row['actual_role'] ?? $row['user_role'] ?? 'USER';
                                        $timestamp = date('M j, Y g:i:s A', strtotime($row['created_at']));
                                        $description = htmlspecialchars($row['action_description'] ?? 'No description');
                                        $tableName = $row['table_name'] ?? 'N/A';
                                        $recordId = $row['record_id'] ?? '';

                                        $affectedResource = $tableName;
                                        if ($recordId) {
                                            $affectedResource .= " (ID: $recordId)";
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors"
                                            data-action-type="<?= htmlspecialchars($actionType) ?>">
                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                <?= $timestamp ?>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-8 h-8 flex items-center justify-center bg-gray-100 text-gray-600 rounded-full">
                                                        <i class="fas fa-user text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($username) ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?= htmlspecialchars($userRole) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
                                                    <i class="fas fa-<?= getActionIcon($actionType) ?> mr-1"></i>
                                                    <?= htmlspecialchars($actionType) ?>
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 max-w-md">
                                                <div class="text-gray-700">
                                                    <?= $description ?>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">
                                                    <?= htmlspecialchars($affectedResource) ?>
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 font-mono text-sm text-gray-600">
                                                <?= htmlspecialchars($row['ip_address'] ?? 'N/A') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center gap-2">
                                                <i class="fas fa-clipboard-list text-3xl text-gray-300"></i>
                                                <p class="text-lg font-medium text-gray-400">No activity logs available</p>
                                                <p class="text-sm text-gray-500">User activities will appear here as they
                                                    interact with the system.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-600">
                                Showing <?= count($activity_logs_data) ?> activity records
                            </p>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">
                                    <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                    Real-time updates enabled
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Profile Dropdown Functions
        function toggleUserDropdown() {
            const dropdown = document.getElementById("userDropdown");
            if (!dropdown) return;

            if (dropdown.classList.contains("hidden")) {
                dropdown.classList.remove("hidden");
                setTimeout(() => {
                    dropdown.classList.remove("opacity-0", "scale-95");
                    dropdown.classList.add("opacity-100", "scale-100");
                }, 10);
            } else {
                dropdown.classList.add("opacity-0", "scale-95");
                setTimeout(() => dropdown.classList.add("hidden"), 150);
            }
        }

        document.addEventListener("click", function (event) {
            const button = document.getElementById("user-menu-button");
            const dropdown = document.getElementById("userDropdown");

            if (!button || !dropdown) return;

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add("opacity-0", "scale-95");
                setTimeout(() => dropdown.classList.add("hidden"), 150);
            }
        });

        function closeAndGoBack() {
            window.location.href = 'index.php';
        }

        // Activity Logs Functions
        function filterActivities(actionType) {
            const rows = document.querySelectorAll('#activityLogsTable tr[data-action-type]');

            rows.forEach(row => {
                if (actionType === 'all' || row.getAttribute('data-action-type') === actionType) {
                    row.style.display = '';
                    row.classList.add('new-entry');
                    setTimeout(() => row.classList.remove('new-entry'), 2000);
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function exportActivities() {
            alert('Export feature will be implemented soon!');
        }

        function refreshActivities() {
            window.location.reload();
        }

        // Real-time updates for activity logs (if needed)
        <?php if ($current_tab == 'activity-logs'): ?>
            function checkForNewActivities() {
                // This could be implemented with WebSockets or AJAX polling
                // For now, we'll just refresh every 30 seconds
                setTimeout(() => {
                    if (document.visibilityState === 'visible') {
                        fetch('?tab=activity-logs&check=1')
                            .then(response => response.text())
                            .then(data => {
                                // Parse response and update table if needed
                                console.log('Checking for new activities...');
                            });
                    }
                    checkForNewActivities();
                }, 30000);
            }

            // Start checking if user is on activity logs tab
            // checkForNewActivities();
        <?php endif; ?>

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

<?php
// Helper function to get appropriate icon for action type
function getActionIcon($actionType)
{
    $icons = [
        'CREATE' => 'plus',
        'UPDATE' => 'edit',
        'DELETE' => 'trash',
        'ARCHIVE' => 'archive',
        'RESTORE' => 'undo',
        'LOGIN' => 'sign-in-alt',
        'LOGOUT' => 'sign-out-alt',
        'EXPORT' => 'download',
        'IMPORT' => 'upload',
        'VIEW' => 'eye',
        'SEARCH' => 'search',
        'FILTER' => 'filter',
        'REPORT' => 'chart-bar',
        'BACKUP' => 'save',
        'RESTORE' => 'history',
        'APPROVE' => 'check',
        'REJECT' => 'times',
        'VERIFY' => 'check-circle',
        'RESET' => 'redo',
        'CHANGE' => 'exchange-alt',
        'ASSIGN' => 'user-check',
        'REMOVE' => 'user-minus'
    ];

    return $icons[strtoupper($actionType)] ?? 'circle';
}
?>