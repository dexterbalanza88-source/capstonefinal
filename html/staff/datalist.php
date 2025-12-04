<?php
session_start(); // Add session start
require_once "../../db/conn.php";

// Check if staff_id is in GET or POST
$staff_id = $_GET['staff_id'] ?? $_POST['staff_id'] ?? '';
if (!$staff_id) {
    // Try to get from cookie or session
    if (isset($_SESSION['staff_id'])) {
        $staff_id = $_SESSION['staff_id'];
    } else {
        // Check all cookies for staff token
        foreach ($_COOKIE as $cookie_name => $token) {
            if (strpos($cookie_name, 'staff_token_') === 0) {
                $staff_id = str_replace('staff_token_', '', $cookie_name);
                break;
            }
        }
    }

    if (!$staff_id) {
        die("Invalid request - No staff ID found");
    }
}

// Store in session for future use
$_SESSION['staff_id'] = $staff_id;

$cookie_name = "staff_token_" . $staff_id;
$token = $_COOKIE[$cookie_name] ?? '';
if (!$token) {
    // Try to get token from session
    if (isset($_SESSION['staff_token'])) {
        $token = $_SESSION['staff_token'];
    } else {
        header("Location: stafflogin.php");
        exit;
    }
}

// Validate token
$sql = "SELECT u.fullname, u.email 
        FROM staff_sessions s
        JOIN user_accounts u ON u.id = s.staff_id
        WHERE s.session_token=? AND s.staff_id=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("si", $token, $staff_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows < 1) {
    $stmt->close();
    setcookie($cookie_name, "", time() - 3600, "/");
    unset($_SESSION['staff_token']);
    unset($_SESSION['staff_id']);
    header("Location: stafflogin.php");
    exit;
}

$stmt->bind_result($fullname, $email);
$stmt->fetch();
$stmt->close();

// Store user info in session
$_SESSION['username'] = $fullname;
$_SESSION['email'] = $email;
$_SESSION['role'] = 'Staff';
$_SESSION['staff_token'] = $token;

// Update last activity
$stmt = $conn->prepare("UPDATE staff_sessions SET last_activity=NOW() WHERE session_token=?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->close();

// Get counts for statistics
$pendingCountQuery = "SELECT COUNT(*) as total FROM registration_form WHERE status = 'pending'";
$registeredCountQuery = "SELECT COUNT(*) as total FROM registration_form WHERE status = 'registered'";
$totalCountQuery = "SELECT COUNT(*) as total FROM registration_form";

$pendingCount = $conn->query($pendingCountQuery)->fetch_assoc()['total'];
$registeredCount = $conn->query($registeredCountQuery)->fetch_assoc()['total'];
$totalCount = $conn->query($totalCountQuery)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Data - Staff Portal</title>

    <!-- CSS Resources -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="../../css/output.css" rel="stylesheet">

    <style>
        .table-container {
            max-height: 65vh;
            overflow-y: auto;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-registered {
            background-color: #d1fae5;
            color: #065f46;
        }

        .action-btn {
            transition: all 0.2s ease-in-out;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-active {
            border-bottom: 3px solid #166534;
            color: #166534;
            font-weight: 600;
        }

        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 50;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .notification.info {
            background-color: #3b82f6;
            color: white;
        }

        .notification.warning {
            background-color: #f59e0b;
            color: white;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
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
                            d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
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

                <!-- DROPDOWN MENU -->
                <div id="userDropdown" class="hidden absolute right-0 top-14 w-62 bg-white rounded-xl shadow-xl 
                    border border-gray-200 z-50 transform opacity-0 scale-95 transition-all duration-200 
                    origin-top-right">
                    <!-- HEADER -->
                    <div class="py-2 px-2 border-b bg-gray-50 rounded-t-xl text-center">
                        <img src="../../img/profile.png"
                            class="w-16 h-16 mx-auto rounded-full border-2 border-white shadow" alt="">

                        <div class="mt-3 text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Staff'); ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($_SESSION['email'] ?? ''); ?>
                        </div>
                    </div>

                    <!-- STAFF MENU ONLY -->
                    <ul class="py-2">
                        <!-- My Profile -->
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

                        <!-- Login History -->
                        <li>
                            <a href="login_history.php" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 
                                hover:bg-gray-100 transition rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10m-9 4h6M5 21h14a2 2 0 002-2v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7a2 2 0 002 2z" />
                                </svg>
                                Login History
                            </a>
                        </li>

                        <!-- Divider -->
                        <li class="border-t my-2"></li>

                        <!-- Sign Out -->
                        <li>
                            <a href="logout.php?staff_id=<?= $staff_id ?>" class="flex items-center gap-3 px-5 py-3 text-sm text-red-600 
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
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700"
        aria-label="Sidenav">
        <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
            <ul class="space-y-2">
                <li>
                    <a href="adddata.php?staff_id=<?= $staff_id ?>"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd"
                                d="M9 2.221V7H4.221a2 2 0 0 1 .365-.5L8.5 2.586A2 2 0 0 1 9 2.22ZM11 2v5a2 2 0 0 1-2 2H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-7Z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="ml-3">Add Data</span>
                    </a>
                </li>

                <li>
                    <button id="farmersDropdownBtn" type="button"
                        class="flex items-center w-full p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group hover:bg-green-100 transition duration-150">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap">Data List</span>
                        <svg class="w-4 h-4 ml-auto transition-transform duration-300 rotate-180" id="dropdownArrow"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <ul id="farmersDropdownMenu" class="py-2 space-y-1 ml-8">
                        <li>
                            <a href="?staff_id=<?= $staff_id ?>&table=new"
                                class="flex items-center p-2 text-sm font-medium text-blue-700 rounded-lg hover:bg-blue-100 transition">
                                <svg class="w-5 h-5 text-blue-700 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                New Farmers
                                <span
                                    class="inline-flex items-center justify-center w-4 h-4 ms-2 text-xs font-semibold text-blue-800 bg-blue-200 rounded-full">
                                    <?= $pendingCount ?>
                                </span>
                            </a>
                        </li>
                        <li>
                            <a href="?staff_id=<?= $staff_id ?>&table=registered"
                                class="flex items-center p-2 text-sm font-medium text-[#166534] rounded-lg hover:bg-green-100 transition">
                                <svg class="w-5 h-5 text-[#166534] mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Registered Farmers
                                <span
                                    class="inline-flex items-center justify-center w-4 h-4 ms-2 text-xs font-semibold text-green-800 bg-green-200 rounded-full">
                                    <?= $registeredCount ?>
                                </span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </aside>

    <main class="md:ml-64 pt-20 p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Farmer Data Management
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                View farmer records (Read-only access for staff)
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stats-card rounded-lg p-4 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-white opacity-90" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white opacity-90">Total Farmers</p>
                        <p class="text-2xl font-bold text-white"><?= $totalCount ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Review</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $pendingCount ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $registeredCount ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <!-- Toolbar -->
            <div
                class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4 border-b border-gray-200">
                <!-- Search -->
                <div class="w-full md:w-1/3">
                    <form id="searchForm" method="GET" class="flex items-center relative w-full">
                        <!-- Keep staff_id in the form -->
                        <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" id="searchInput" name="search"
                                placeholder="Search by name, barangay, or mobile..."
                                value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                                class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg w-full pl-10 pr-10 p-2 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">

                            <!-- Clear button -->
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <button type="button" onclick="window.location='?staff_id=<?= $staff_id ?>'"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- View Only Notice -->
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-blue-700 font-medium">View Only - Staff Access</span>
                        </div>
                    </div>

                    <!-- Export Button -->
                    <button id="exportBtn"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v12m0 0l3-3m-3 3l-3-3m9 3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Export
                    </button>
                </div>
            </div>

            <!-- Table Tabs -->
            <div class="border-b border-gray-200">
                <div class="flex space-x-8 px-4">
                    <a href="?staff_id=<?= $staff_id ?>&table=new" id="newFarmersTab"
                        class="py-4 px-2 font-medium text-sm border-b-2 <?= (!isset($_GET['table']) || $_GET['table'] == 'new') ? 'tab-active' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        New Farmers (<?= $pendingCount ?>)
                    </a>
                    <a href="?staff_id=<?= $staff_id ?>&table=registered" id="registeredFarmersTab"
                        class="py-4 px-2 font-medium text-sm border-b-2 <?= (isset($_GET['table']) && $_GET['table'] == 'registered') ? 'tab-active' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        Registered Farmers (<?= $registeredCount ?>)
                    </a>
                </div>
            </div>

            <!-- Tables Container -->
            <div class="p-4">
                <?php
                // Determine which table to show
                $current_table = isset($_GET['table']) ? $_GET['table'] : 'new';
                $show_new = ($current_table == 'new');
                $show_registered = ($current_table == 'registered');

                // Pagination setup
                $limit = 10;
                $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
                $offset = ($page - 1) * $limit;
                $search = isset($_GET['search']) ? trim($_GET['search']) : '';

                if ($show_new):
                    ?>
                    <!-- New Farmers Table -->
                    <div id="newFarmersTable" class="table-container">
                        <?php
                        // Base query for new farmers (pending)
                        $whereClause = "WHERE rf.status = 'pending'";

                        if (!empty($search)) {
                            $safeSearch = $conn->real_escape_string($search);
                            $whereClause .= " AND (
                            LOWER(rf.f_name) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.s_name) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.brgy) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.mobile) LIKE LOWER('%$safeSearch%')
                        )";
                        }

                        // Main query for new farmers
                        $sql = "
                        SELECT
                            rf.id,
                            rf.f_name,
                            rf.m_name,
                            rf.s_name,
                            rf.brgy,
                            rf.dob,
                            rf.gender,
                            rf.mobile,
                            rf.created_at,
                            COALESCE(SUM(l.total_area), 0) AS total_farmarea,
                            COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'Not specified') AS livelihoods
                        FROM registration_form rf
                        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
                        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
                        $whereClause
                        GROUP BY rf.id
                        ORDER BY rf.created_at DESC
                        LIMIT $limit OFFSET $offset;
                    ";

                        $result = $conn->query($sql);
                        if (!$result) {
                            die("SQL Error: " . $conn->error);
                        }

                        // Count query
                        $countQuery = "SELECT COUNT(*) as total FROM registration_form rf $whereClause";
                        $countResult = $conn->query($countQuery);
                        $totalRows = $countResult ? $countResult->fetch_assoc()['total'] : 0;
                        $totalPages = ceil($totalRows / $limit);
                        ?>

                        <?php if ($totalRows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Farmer Information</th>
                                            <th scope="col" class="px-4 py-3">Contact & Location</th>
                                            <th scope="col" class="px-4 py-3">Farm Details</th>
                                            <th scope="col" class="px-4 py-3">Date Added</th>
                                            <th scope="col" class="px-4 py-3">Status</th>
                                            <th scope="col" class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200">
                                        <?php
                                        $startNum = ($page - 1) * $limit + 1;

                                        while ($row = $result->fetch_assoc()):
                                            $dob = new DateTime($row['dob']);
                                            $age = (new DateTime())->diff($dob)->y;

                                            $middleInitial = !empty($row['m_name'])
                                                ? strtoupper(substr($row['m_name'], 0, 1)) . "."
                                                : "";

                                            $fullName = ucwords(strtolower(
                                                trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name'])
                                            ));

                                            $createdDate = new DateTime($row['created_at']);
                                            $formattedDate = $createdDate->format('M j, Y');
                                            ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors fade-in">
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-3">
                                                        <div
                                                            class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                                            <span class="text-yellow-600 font-semibold text-sm">
                                                                <?= strtoupper(substr($row['f_name'], 0, 1)) ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-gray-900">
                                                                <?= htmlspecialchars($fullName) ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?= $age ?> yrs • <?= htmlspecialchars($row['gender']) ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">ID: <?= $row['id'] ?></div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="text-gray-900 font-medium"><?= htmlspecialchars($row['mobile']) ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($row['brgy']) ?></div>
                                                    <div class="text-xs text-gray-500">Barangay</div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="space-y-1">
                                                        <div class="text-gray-900 font-medium">
                                                            <?= $row['total_farmarea'] > 0 ? number_format($row['total_farmarea'], 2) . ' ha' : 'Not specified' ?>
                                                        </div>
                                                        <div class="text-sm text-gray-600 max-w-xs">
                                                            <?= htmlspecialchars($row['livelihoods']) ?>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="text-gray-900"><?= $formattedDate ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <?= $createdDate->format('g:i A') ?>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <span class="status-badge status-pending">
                                                        Pending Review
                                                    </span>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-2">
                                                        <button onclick="viewFarmer(<?= $row['id'] ?>, '<?= $staff_id ?>')"
                                                            class="action-btn p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                            title="View Details">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                        </button>

                                                        <button onclick="showStaffNotice()"
                                                            class="action-btn p-2 text-gray-400 rounded-lg transition-colors cursor-not-allowed"
                                                            title="Approve (Admin Only)">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="flex items-center justify-between p-4 border-t border-gray-200">
                                    <div class="text-sm text-gray-700">
                                        Showing <?= (($page - 1) * $limit) + 1 ?> to <?= min($page * $limit, $totalRows) ?> of
                                        <?= $totalRows ?> results
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?staff_id=<?= $staff_id ?>&page=<?= $page - 1 ?>&table=new<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                Previous
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <a href="?staff_id=<?= $staff_id ?>&page=<?= $page + 1 ?>&table=new<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No pending farmers</h3>
                                <p class="text-gray-500">
                                    <?= !empty($search)
                                        ? 'No pending farmers found for "' . htmlspecialchars($search) . '"'
                                        : 'All farmer registrations have been processed by admin.' ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_registered): ?>
                    <!-- Registered Farmers Table -->
                    <div id="registeredFarmersTable" class="table-container">
                        <?php
                        // Query for registered farmers
                        $whereClauseRegistered = "WHERE rf.status = 'registered'";

                        if (!empty($search)) {
                            $safeSearch = $conn->real_escape_string($search);
                            $whereClauseRegistered .= " AND (
                            LOWER(rf.f_name) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.s_name) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.brgy) LIKE LOWER('%$safeSearch%')
                            OR LOWER(rf.mobile) LIKE LOWER('%$safeSearch%')
                        )";
                        }

                        $sqlRegistered = "
                        SELECT
                            rf.id,
                            rf.f_name,
                            rf.m_name,
                            rf.s_name,
                            rf.brgy,
                            rf.dob,
                            rf.gender,
                            rf.mobile,
                            rf.created_at,
                            COALESCE(SUM(l.total_area), 0) AS total_farmarea,
                            COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'Not specified') AS livelihoods
                        FROM registration_form rf
                        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
                        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
                        $whereClauseRegistered
                        GROUP BY rf.id
                        ORDER BY rf.created_at DESC
                        LIMIT $limit OFFSET $offset;
                    ";

                        $resultRegistered = $conn->query($sqlRegistered);
                        $countRegistered = $conn->query("SELECT COUNT(*) as total FROM registration_form rf $whereClauseRegistered")->fetch_assoc()['total'];
                        $totalPagesRegistered = ceil($countRegistered / $limit);
                        ?>

                        <?php if ($countRegistered > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Farmer Information</th>
                                            <th scope="col" class="px-4 py-3">Contact & Location</th>
                                            <th scope="col" class="px-4 py-3">Farm Details</th>
                                            <th scope="col" class="px-4 py-3">Registration Date</th>
                                            <th scope="col" class="px-4 py-3">Status</th>
                                            <th scope="col" class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200">
                                        <?php while ($row = $resultRegistered->fetch_assoc()):
                                            $dob = new DateTime($row['dob']);
                                            $age = (new DateTime())->diff($dob)->y;

                                            $middleInitial = !empty($row['m_name'])
                                                ? strtoupper(substr($row['m_name'], 0, 1)) . "."
                                                : "";

                                            $fullName = ucwords(strtolower(
                                                trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name'])
                                            ));

                                            $createdDate = new DateTime($row['created_at']);
                                            $formattedDate = $createdDate->format('M j, Y');
                                            ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors fade-in">
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-3">
                                                        <div
                                                            class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                                            <span class="text-green-600 font-semibold text-sm">
                                                                <?= strtoupper(substr($row['f_name'], 0, 1)) ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-gray-900">
                                                                <?= htmlspecialchars($fullName) ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?= $age ?> yrs • <?= htmlspecialchars($row['gender']) ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">ID: <?= $row['id'] ?></div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="text-gray-900 font-medium"><?= htmlspecialchars($row['mobile']) ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($row['brgy']) ?></div>
                                                    <div class="text-xs text-gray-500">Barangay</div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="space-y-1">
                                                        <div class="text-gray-900 font-medium">
                                                            <?= $row['total_farmarea'] > 0 ? number_format($row['total_farmarea'], 2) . ' ha' : 'Not specified' ?>
                                                        </div>
                                                        <div class="text-sm text-gray-600 max-w-xs">
                                                            <?= htmlspecialchars($row['livelihoods']) ?>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="text-gray-900"><?= $formattedDate ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <?= $createdDate->format('g:i A') ?>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <span class="status-badge status-registered">
                                                        Registered
                                                    </span>
                                                </td>

                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-2">
                                                        <button onclick="viewFarmer(<?= $row['id'] ?>, '<?= $staff_id ?>')"
                                                            class="action-btn p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                            title="View Details">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                        </button>

                                                        <button onclick="showStaffNotice()"
                                                            class="action-btn p-2 text-gray-400 rounded-lg transition-colors cursor-not-allowed"
                                                            title="Edit (Admin Only)">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination for Registered Farmers -->
                            <?php if ($totalPagesRegistered > 1): ?>
                                <div class="flex items-center justify-between p-4 border-t border-gray-200">
                                    <div class="text-sm text-gray-700">
                                        Showing <?= (($page - 1) * $limit) + 1 ?> to <?= min($page * $limit, $countRegistered) ?> of
                                        <?= $countRegistered ?> results
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?staff_id=<?= $staff_id ?>&page=<?= $page - 1 ?>&table=registered<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                Previous
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($page < $totalPagesRegistered): ?>
                                            <a href="?staff_id=<?= $staff_id ?>&page=<?= $page + 1 ?>&table=registered<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No registered farmers</h3>
                                <p class="text-gray-500">
                                    <?= !empty($search)
                                        ? 'No registered farmers found for "' . htmlspecialchars($search) . '"'
                                        : 'No farmers have been registered yet.' ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Notifications Container -->
    <div id="notifications"></div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Table management
        function showTable(tableId, staffId) {
            // Hide all tables
            document.querySelectorAll('[id$="Table"]').forEach(table => {
                table.classList.add('hidden');
            });

            // Show selected table
            document.getElementById(tableId).classList.remove('hidden');

            // Update active tab
            document.querySelectorAll('button[onclick*="showTable"]').forEach(btn => {
                btn.classList.remove('tab-active');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            const activeTab = document.getElementById(tableId === 'newFarmersTable' ? 'newFarmersTab' : 'registeredFarmersTab');
            activeTab.classList.add('tab-active');
            activeTab.classList.remove('border-transparent', 'text-gray-500');

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('table', tableId === 'newFarmersTable' ? 'new' : 'registered');
            window.history.replaceState({}, '', url);
        }

        // View farmer details
        function viewFarmer(id, staffId) {
            window.open(`view_farmer.php?id=${id}&staff_id=${staffId}`, '_blank');
        }

        // Staff notice for restricted actions
        function showStaffNotice() {
            showNotification('This action requires administrator privileges. Please contact your administrator.', 'warning');
        }

        // Export functionality
        document.getElementById('exportBtn').addEventListener('click', function () {
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search');
            const table = document.getElementById('newFarmersTable')?.classList.contains('hidden') ? 'registered' : 'new';

            let url = `export_farmers.php?format=csv&type=${table}`;
            if (search) {
                url += `&search=${encodeURIComponent(search)}`;
            }
            url += `&staff_id=<?= $staff_id ?>`;

            window.open(url, '_blank');
            showNotification('Exporting farmer data...', 'info');
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type} fade-in`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${type === 'warning' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
                }
                        </svg>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `;

            document.getElementById('notifications').appendChild(notification);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // User dropdown functionality
        function toggleUserDropdown() {
            const box = document.getElementById("userDropdown");
            if (box.classList.contains("hidden")) {
                box.classList.remove("hidden");
                setTimeout(() => {
                    box.classList.remove("opacity-0", "scale-95");
                    box.classList.add("opacity-100", "scale-100");
                }, 10);
            } else {
                box.classList.add("opacity-0", "scale-95");
                setTimeout(() => box.classList.add("hidden"), 150);
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener("click", function (event) {
            const button = document.getElementById("user-menu-button");
            const dropdown = document.getElementById("userDropdown");

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add("opacity-0", "scale-95");
                setTimeout(() => dropdown.classList.add("hidden"), 150);
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Keep sidebar dropdown always open
            const farmersDropdownMenu = document.getElementById("farmersDropdownMenu");
            const dropdownArrow = document.getElementById("dropdownArrow");
            farmersDropdownMenu.classList.remove("hidden");
            dropdownArrow.classList.add("rotate-180");
        });
    </script>
</body>

</html>