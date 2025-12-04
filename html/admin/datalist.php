<?php
session_name("admin_session");
session_start();
require_once "../../db/conn.php";
require_once "activity_logger.php";
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

// Auto logout after 5 minutes (300 seconds)
$timeout = 1000;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    echo "<script>alert('⏰ Session expired due to inactivity. Please login again.'); 
          window.location.href='adminlogin.php';</script>";
    exit;
}
if (isset($_GET['partial']) && $_GET['partial'] === true) {
    // Output only <tr> rows (no <table> wrapper)
    while ($row = mysqli_fetch_assoc($result)) {
        // your existing <tr> rendering code here
    }
    exit;
}
// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "farmer_info";
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// --- SQL to fetch livelihoods only ---
$sql_livelihoods = "
SELECT 
    l.registration_form_id,
    GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', ') AS livelihoodsList,
    COUNT(DISTINCT sa.id) AS total_livelihoods
FROM livelihoods l
LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
GROUP BY l.registration_form_id
ORDER BY l.registration_form_id ASC;
";

// Execute the query
$livelihoods_data = [];
if ($result2 = $conn->query($sql_livelihoods)) {
    while ($row2 = $result2->fetch_assoc()) {
        $livelihoods_data[$row2['registration_form_id']] = [
            'livelihoodsList' => $row2['livelihoodsList'],
            'total_livelihoods' => $row2['total_livelihoods']
        ];
    }
} else {
    die("Query failed: " . $conn->error);
}

?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/output.css" rel="stylesheet">
</head>
<style>
    /* 🧩 Apply only inside the view modal */
    #view-farmer-modal input[readonly],
    #view-farmer-modal textarea[readonly] {
        background-color: #f9fafb;
        /* light gray */
        cursor: not-allowed;
        pointer-events: none;
        color: #111827;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.5rem;
    }

    html,
    body {
        height: 100%;
        overflow: hidden;
        /* 🔥 Prevents the outer scroll */
    }

    @media print {

        .print\:hidden,
        .no-print {
            display: none !important;
        }
    }

    /* Clear search button */
    #clearBtn {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #clearBtn:hover {
        color: #374151;
        background: #f3f4f6;
        border-radius: 50%;
    }

    /* Enhanced Table Styles */
    .enhanced-table {
        font-size: 0.875rem;
        background: white;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }

    .enhanced-table thead {
        background: linear-gradient(135deg, #166534 0%, #15803d 100%);
    }

    .enhanced-table thead th {
        color: white;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.75rem 1rem;
        border: none;
        position: relative;
    }

    .enhanced-table tbody tr {
        transition: all 0.2s ease-in-out;
        border-bottom: 1px solid #e5e7eb;
    }

    .enhanced-table tbody tr:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .enhanced-table tbody td {
        padding: 0.875rem 1rem;
        vertical-align: middle;
        border: none;
        color: #374151;
    }

    /* Status badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-registered {
        background-color: #d1fae5;
        color: #065f46;
    }

    /* Action buttons */
    .action-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        font-size: 0.75rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .btn-view {
        background: #3b82f6;
        color: white;
    }

    .btn-view:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }

    .btn-archive {
        background: #ef4444;
        color: white;
    }

    .btn-archive:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .btn-generate {
        background: #10b981;
        color: white;
    }

    .btn-generate:hover {
        background: #059669;
        transform: translateY(-1px);
    }

    /* Checkbox styling */
    .table-checkbox {
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 0.375rem;
        border: 2px solid #d1d5db;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }

    .table-checkbox:checked {
        background-color: #166534;
        border-color: #166534;
    }

    /* Loading states */
    .table-loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .skeleton-row {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }
</style>

<body>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- NAVBAR -->
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

                    <!-- PROFILE BUTTON -->
                    <button id="user-menu-button" class="flex items-center rounded-full ring-2 ring-transparent hover:ring-[#FFD447] 
        transition-all duration-200 p-[3px]" onclick="toggleUserDropdown()">

                        <img class="w-11 h-11 rounded-full shadow-md border-2 border-white" src="../../img/profile.png"
                            alt="User photo">
                    </button>

                    <!-- DROPDOWN MENU -->
                    <div id="userDropdown" class="hidden absolute right-0 top-14 w-62 bg-white rounded-xl shadow-xl 
        border border-gray-200 z-50 transform opacity-0 scale-95 transition-all duration-200 origin-top-right">

                        <!-- HEADER -->
                        <div class="py-2 px-2 border-b bg-gray-50 rounded-t-xl text-center">

                            <img src="../../img/profile.png"
                                class="w-16 h-16 mx-auto rounded-full border-2 border-white shadow" alt="">

                            <div class="mt-3 text-lg font-semibold text-gray-900">
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                            </div>

                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($_SESSION['email'] ?? ''); ?>
                            </div>
                        </div>

                        <!-- MENU -->
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

                            <!-- User Management -->
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

                            <!-- Divider -->
                            <li class="border-t my-2"></li>

                            <!-- Sign Out -->
                            <li>
                                <a href="logout.php" onclick="return confirmLogout(event)" class="flex items-center gap-3 px-5 py-3 text-sm text-red-600 
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

        <!-- SIDEBAR -->
        <aside id="drawer-navigation"
            class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            aria-label="Sidenav">
            <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
                <ul class="space-y-2">
                    <li>
                        <a href="index.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                            </svg>
                            <span class="ml-3">Dashboard</span>
                        </a>
                    </li>

                    <li>
                        <a href="adddata.php"
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
                            <svg class="w-4 h-4 ml-auto transition-transform duration-300" id="dropdownArrow"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- ✅ Custom-controlled dropdown -->
                        <ul id="farmersDropdownMenu" class="hidden py-2 space-y-1 ml-8">
                            <li id="allFarmersBtn" class="cursor-pointer" onclick="showTable('allFarmersTable', event)">
                                <div
                                    class="flex items-center p-2 text-sm font-medium text-blue-700 rounded-lg hover:bg-blue-100">
                                    <svg class="w-5 h-5 text-blue-700 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 3h14v2H3V3zm0 6h14v2H3V9zm0 6h14v2H3v-2z" />
                                    </svg>
                                    New Farmers
                                </div>
                            </li>
                            <li id="registeredBtn" class="cursor-pointer" onclick="showTable('registeredTable', event)">
                                <div
                                    class="flex items-center p-2 text-sm font-medium text-[#166534] rounded-lg hover:bg-green-100">
                                    <img src="../../img/farmer.jpg" alt="Farmer Icon"
                                        class="w-6 h-6 rounded-full object-cover" />
                                    <span class="text-[#166534] font-medium">Registered Farmers</span>
                                </div>
                            </li>
                        </ul>
                    </li>

                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="report.php"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
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
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
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
        <main class="md:ml-64 pt-20 width-full">
            <!-- Start coding here -->
            <section class="bg-gray-50 dark:bg-gray-900 p-2 sm:p-1">
                <div class="mx-auto max-w-screen-xl px-4">
                    <!-- Start coding here -->
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg">
                        <div
                            class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class=" md:w-1/2" style="width: 500px;">
                                <form id="searchForm" method="GET" class="flex items-center relative w-full">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search" name="search" placeholder="Search"
                                            value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg w-full pl-10 pr-10 p-2">

                                        <!-- Clear button -->
                                        <button type="button" id="clearBtn" onclick="window.location='?';"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 <?= isset($_GET['search']) ? '' : 'hidden' ?>">
                                            &times;
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div
                                class=" md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <div class="flex items-center space-x-3 w-full md:w-auto">
                                    <div class="flex flex-wrap items-center gap-3 h-[45px] mb-4">

                                        <!-- 🕓 Last Activity -->
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Last Activity Update:
                                            </span>
                                            <a href="#" data-modal-target="activityLogModal"
                                                data-modal-toggle="activityLogModal"
                                                class="text-blue-600 font-semibold hover:text-blue-700 hover:underline transition">
                                                View History
                                            </a>
                                        </div>

                                        <!-- 🔄 Update Status Button (Only for New Farmers) -->
                                        <div id="updateStatusContainer" class="relative hidden">
                                            <button id="statusDropdownButton" data-dropdown-toggle="statusDropdown"
                                                type="button"
                                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium 
                   text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 hover:shadow-md 
                   focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                                                disabled>
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 4v5h.582a1 1 0 01.993.883L6 10v5h12v-5a1 1 0 01.883-.993L19 9h.5V4H4z" />
                                                </svg>
                                                Update Status
                                                <svg class="w-4 h-4 ml-1" xmlns="http://www.w3.org/2000/svg"
                                                    fill="currentColor" viewBox="0 0 20 20">
                                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586
                    l3.293-3.293a1 1 0 111.414 1.414l-4 4a1
                    1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                </svg>
                                            </button>

                                            <!-- Dropdown -->
                                            <div id="statusDropdown"
                                                class="hidden absolute z-20 mt-2 w-44 rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                                                <ul class="py-2 text-sm text-gray-700">
                                                    <li>
                                                        <button type="button" data-status="Registered"
                                                            class="statusOption block w-full text-left px-4 py-2 text-green-600 hover:bg-green-50">
                                                            ✅ Set Registered
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- 📤 Export Dropdown (Only for Registered Farmers) -->
                                        <div id="exportContainer" class="relative hidden">
                                            <button id="exportBtn" type="button"
                                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium 
                   text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm 
                   hover:bg-gray-100 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all">
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 4v12m0 0l3-3m-3 3l-3-3m9 3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Export
                                                <svg class="w-4 h-4 ml-1" xmlns="http://www.w3.org/2000/svg"
                                                    fill="currentColor" viewBox="0 0 20 20">
                                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586
                    l3.293-3.293a1 1 0 111.414 1.414l-4 4a1
                    1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                </svg>
                                            </button>

                                            <!-- Dropdown Menu -->
                                            <div id="exportDropdown"
                                                class="hidden absolute right-0 z-50 mt-2 w-44 rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                                                <div class="py-1">
                                                    <a href="export_users.php?format=csv"
                                                        class="block px-4 py-2 text-sm hover:bg-gray-100">📊 CSV /
                                                        Excel</a>
                                                    <a href="export_users.php?format=json"
                                                        class="block px-4 py-2 text-sm hover:bg-gray-100">🧩 JSON</a>
                                                    <a href="export_users.php?format=pdf"
                                                        class="block px-4 py-2 text-sm hover:bg-gray-100">📄 PDF</a>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- After the export container (around line 210) -->
                                        <div id="importContainer" class="relative">
                                            <button id="importBtn" type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium 
               text-white bg-purple-600 rounded-lg shadow-sm hover:bg-purple-700 hover:shadow-md 
               focus:outline-none focus:ring-2 focus:ring-purple-400 transition-all">
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                                </svg>
                                                Import CSV
                                            </button>

                                            <!-- Dropdown menu for import options -->
                                            <div id="importDropdown"
                                                class="hidden absolute right-0 z-50 mt-2 w-64 bg-white rounded-lg shadow-lg border">
                                                <div class="p-3">
                                                    <form action="import_farmers.php" method="POST"
                                                        enctype="multipart/form-data">
                                                        <input type="file" name="csv_file" accept=".csv"
                                                            class="w-full mb-2 text-sm" required>
                                                        <div class="flex justify-between">
                                                            <button type="submit"
                                                                class="px-3 py-1 bg-purple-600 text-white text-xs rounded">
                                                                Upload
                                                            </button>
                                                            <button type="button"
                                                                onclick="document.getElementById('importDropdown').classList.add('hidden')"
                                                                class="px-3 py-1 bg-gray-200 text-xs rounded">
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <section id="new-farmers">
                            <!-- ✅ NEW FARMERS TABLE -->
                            <div id="allFarmersTable" class="hidden">
                                <?php
                                include '../../db/conn.php';

                                // Pagination setup
                                $limit = 10;
                                $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
                                $offset = ($page - 1) * $limit;
                                $search = isset($_GET['search']) ? trim($_GET['search']) : '';

                                // --- WHERE CLAUSE ---
                                $whereClause = "WHERE 1 AND LOWER(rf.status) = 'pending'";

                                if (!empty($search)) {
                                    $safeSearch = $conn->real_escape_string($search);

                                    $whereClause .= " AND (
                                    LOWER(rf.f_name) LIKE LOWER('%$safeSearch%')
                                    OR LOWER(rf.s_name) LIKE LOWER('%$safeSearch%')
                                    OR LOWER(rf.brgy) LIKE LOWER('%$safeSearch%')
                                    OR LOWER(sa.sub_name) LIKE LOWER('%$safeSearch%')
                                )";
                                }

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
                                        COALESCE(SUM(l.total_area), 0) AS total_farmarea,
                                        COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'N/A') AS livelihoodsList
                                    FROM registration_form rf
                                    LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
                                    LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
                                    $whereClause
                                    GROUP BY rf.id
                                    ORDER BY rf.f_name ASC
                                    LIMIT $limit OFFSET $offset;
                                ";


                                // --- EXECUTE QUERY ---
                                $result = $conn->query($sql);
                                if (!$result) {
                                    die("SQL Error: " . $conn->error);
                                }

                                // --- PAGINATION COUNT ---
                                $countQuery = "
    SELECT COUNT(DISTINCT rf.id) AS total
    FROM registration_form rf
    LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
    LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
    $whereClause
";
                                $countResult = $conn->query($countQuery);
                                $totalRows = ($countResult) ? $countResult->fetch_assoc()['total'] : 0;
                                $totalPages = ceil($totalRows / $limit);
                                ?>


                                <div class="max-h-[450px] overflow-y-auto">
                                    <?php if ($totalRows > 0): ?>
                                        <table id="yourTableId"
                                            class="w-full text-xs text-left text-gray-500 bg-white shadow rounded-lg">
                                            <thead class=" text-xs text-gray-700 uppercase bg-gray-50">
                                                <tr>
                                                    <th class="px-3 py-3 border-b border-gray-200 whitespace-nowrap"><input
                                                            type="checkbox" id="selectAll"
                                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded"></th>
                                                    <th class="px-3 py-3">No</th>
                                                    <th class="px-3 py-3">Name</th>
                                                    <th class="px-3 py-3">Address</th>
                                                    <th class="px-3 py-3">Contact</th>
                                                    <th class="px-3 py-3">Age</th>
                                                    <th class="px-3 py-3">DOB</th>
                                                    <th class="px-3 py-3">Gender</th>
                                                    <th class="px-3 py-3">Farm Size</th>
                                                    <th class="px-3 py-3">Type of Commodity</th>
                                                    <th class="px-3 py-3">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="results_new">
                                                <?php
                                                $startNumber = ($page - 1) * $limit + 1;

                                                while ($row = $result->fetch_assoc()):
                                                    $dob = new DateTime($row['dob']);
                                                    $today = new DateTime();
                                                    $age = $today->diff($dob)->y;

                                                    $middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
                                                    $fullName = htmlspecialchars(ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name']))));
                                                    ?>
                                                    <tr class="border-b border-gray-200" data-id="<?= $row['id'] ?>">
                                                        <td class="px-3 py-3"><input type="checkbox" class="rowCheckbox"
                                                                value="<?= $row['id'] ?>"></td>
                                                        <td class="px-3 py-3"><?= $startNumber++ ?></td>
                                                        <td class="px-3 py-3"><?= $fullName ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['brgy']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['mobile']) ?></td>
                                                        <td class="px-3 py-3"><?= $age ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['dob']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['gender']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['total_farmarea']) ?>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <?= htmlspecialchars($row['livelihoodsList'] ?: 'N/A') ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <button data-id="<?= $row['id']; ?>" class="archiveAction flex items-center justify-center gap-2 
                                                                px-4 py-2 bg-green-500 text-white font-medium 
                                                                rounded-lg shadow-sm hover:bg-green-600 hover:shadow 
                                                                transition-all duration-200">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                                    stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6" />
                                                                </svg>
                                                                Archive
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>

                                        <!-- ✅ Pagination -->
                                        <?php if ($totalPages > 1): ?>
                                            <nav class="flex justify-between items-center p-4" aria-label="Table navigation">
                                                <span class="text-sm text-gray-500">Page <?= $page ?> of
                                                    <?= $totalPages ?></span>
                                                <ul class="inline-flex -space-x-px">
                                                    <li>
                                                        <?php if ($page > 1): ?>
                                                            <a href="?page=<?= $page - 1 ?>"
                                                                class="py-2 px-3 bg-white border rounded-l-lg hover:bg-gray-100">Prev</a>
                                                        <?php else: ?>
                                                            <span
                                                                class="py-2 px-3 bg-gray-100 border rounded-l-lg text-gray-400">Prev</span>
                                                        <?php endif; ?>
                                                    </li>
                                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                        <li>
                                                            <a href="?page=<?= $i ?>"
                                                                class="py-2 px-3 border <?= ($i == $page) ? 'bg-blue-500 text-white' : 'bg-white hover:bg-gray-100' ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <li>
                                                        <?php if ($page < $totalPages): ?>
                                                            <a href="?page=<?= $page + 1 ?>"
                                                                class="py-2 px-3 bg-white border rounded-r-lg hover:bg-gray-100">Next</a>
                                                        <?php else: ?>
                                                            <span
                                                                class="py-2 px-3 bg-gray-100 border rounded-r-lg text-gray-400">Next</span>
                                                        <?php endif; ?>
                                                    </li>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <div class="p-6 bg-white shadow rounded-lg text-center">
                                            <p class="text-red-500 font-semibold text-lg">
                                                <?= !empty($search) ? 'No records found for "' . htmlspecialchars($search) . '"' : 'No new farmer records available.' ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                        <!-- ✅ Registered Farmers Table Section -->
                        <section id="registered-farmers">
                            <div id="registeredTable" class="hidden">
                                <?php
                                // 🧩 Get search input
                                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                $safeSearch = mysqli_real_escape_string($conn, $search);

                                // 🧩 Pagination setup
                                $page_reg = isset($_GET['page_reg']) ? (int) $_GET['page_reg'] : 1;
                                $limit_reg = 10;
                                $offset_reg = ($page_reg - 1) * $limit_reg;

                                // 🧩 BUILD WHERE CLAUSE (just like NEW FARMERS)
                                $whereClause = "WHERE LOWER(rf.status) = 'registered'";

                                if (!empty($safeSearch)) {
                                    $whereClause .= " AND (
                                    LOWER(rf.f_name) LIKE LOWER('%$safeSearch%') OR
                                    LOWER(rf.m_name) LIKE LOWER('%$safeSearch%') OR
                                    LOWER(rf.s_name) LIKE LOWER('%$safeSearch%') OR
                                    LOWER(rf.brgy) LIKE LOWER('%$safeSearch%') OR
                                    LOWER(rf.mobile) LIKE LOWER('%$safeSearch%') OR
                                    LOWER(sa.sub_name) LIKE LOWER('%$safeSearch%')
                                )";
                                }

                                // 🧩 MAIN DATA QUERY
                                $query_reg = "
                                        SELECT
                                            rf.id,
                                            rf.f_name,
                                            rf.m_name,
                                            rf.s_name,
                                            rf.brgy,
                                            rf.dob,
                                            rf.gender,
                                            rf.mobile,
                                            COALESCE(SUM(l.total_area), 0) AS total_farmarea,
                                            COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'N/A') AS livelihoodsList
                                        FROM registration_form rf
                                        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
                                        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
                                        $whereClause
                                        GROUP BY rf.id
                                        ORDER BY rf.f_name ASC
                                        LIMIT $limit_reg OFFSET $offset_reg
                                    ";

                                $result_reg = mysqli_query($conn, $query_reg);

                                // 🧩 COUNT QUERY (must match EXACT same WHERE)
                                $countQuery = "
                                        SELECT COUNT(DISTINCT rf.id) AS total
                                        FROM registration_form rf
                                        LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
                                        LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
                                        $whereClause
                                    ";

                                $totalRegisteredFarmers = mysqli_fetch_assoc(mysqli_query($conn, $countQuery))['total'];
                                $totalPages_reg = ceil($totalRegisteredFarmers / $limit_reg);
                                ?>


                                <div class="max-h-[450px] overflow-y-auto" id="registered-farmers">
                                    <?php if ($totalRegisteredFarmers > 0): ?>
                                        <table id="registeredFarmersTable"
                                            class="w-full text-xs text-left text-gray-500 bg-white shadow rounded-lg">
                                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-4 border-b border-gray-200 whitespace-nowrap"><input
                                                            type="checkbox" id="selectAllRegistered"
                                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded"></th>
                                                    <th class="px-4 py-4">No</th>
                                                    <th class="px-4 py-4">Name</th>
                                                    <th class="px-4 py-4">Address</th>
                                                    <th class="px-4 py-4">Contact</th>
                                                    <th class="px-4 py-4">Age</th>
                                                    <th class="px-4 py-4">DOB</th>
                                                    <th class="px-4 py-4">Gender</th>
                                                    <th class="px-4 py-4">Farm Size</th>
                                                    <th class="px-4 py-4">Type of Commodity</th>
                                                    <th class="px-4 py-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="results_registered">
                                                <?php
                                                $counter = 1;
                                                while ($row = mysqli_fetch_assoc($result_reg)):
                                                    $dob = new DateTime($row['dob']);
                                                    $today = new DateTime();
                                                    $age = $today->diff($dob)->y;

                                                    // Full name formatting
                                                    $middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
                                                    $fullName = ucwords(strtolower(trim($row['f_name'] . ' ' . $middleInitial . ' ' . $row['s_name'])));
                                                    ?>
                                                    <tr class="border-b border-gray-200" data-id="<?= $row['id'] ?>">
                                                        <td class="px-3 py-3"><input type="checkbox" class="rowCheckbox"
                                                                value="<?= $row['id'] ?>"></td>
                                                        <td class="px-3 py-3"><?= (($page_reg - 1) * $limit_reg) + $counter ?>
                                                        </td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($fullName) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['brgy']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['mobile']) ?></td>
                                                        <td class="px-3 py-3"><?= $age ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['dob']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['gender']) ?></td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['total_farmarea']) ?>
                                                        </td>
                                                        <td class="px-3 py-3"><?= htmlspecialchars($row['livelihoodsList']) ?>
                                                        </td>

                                                        <!-- ✅ Actions Dropdown -->
                                                        <td class="px-4 py-3 relative">
                                                            <button type="button"
                                                                class="dropdown-toggle inline-flex items-center p-1 text-gray-500 hover:text-gray-800 rounded-lg">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path
                                                                        d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                                </svg>
                                                            </button>
                                                            <div
                                                                class="dropdown-menu hidden absolute right-0 z-50 w-48 bg-white rounded shadow divide-y divide-gray-100">
                                                                <ul class="py-1 text-sm text-gray-700 font-medium">
                                                                    <!-- View -->
                                                                    <li> <a href="view_details.php?id=<?= $row['id']; ?>"
                                                                            class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                                                stroke="currentColor">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                            </svg> View Details </a> </li> <!-- Archive -->
                                                                    <li> <button data-id="<?= $row['id']; ?>"
                                                                            class="archiveAction w-full text-left flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                                                stroke="currentColor">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6" />
                                                                            </svg> Archive </button> </li> <!-- Divider -->
                                                                    <li class="border-t border-gray-100 my-1"></li>
                                                                    <!-- Generate ID -->
                                                                    <li> <button data-id="<?= $row['id']; ?>"
                                                                            class="generateIdBtn w-full text-left flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                                                stroke="currentColor">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M12 4v16m8-8H4" />
                                                                            </svg> Generate ID </button> </li> <!-- Print -->
                                                                    <li> <a href="view.php?id=<?= $row['id']; ?>"
                                                                            target="_blank"
                                                                            class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-700 transition">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                                                stroke="currentColor">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M6 9V2h12v7m-1 11h-2a2 2 0 01-2-2v-2H9v4H7v-4H5v4h14v-4h-2v2a2 2 0 01-2 2z" />
                                                                            </svg> Print Form </a> </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php $counter++; endwhile; ?>
                                            </tbody>
                                        </table>

                                        <!-- ✅ Pagination -->
                                        <?php if ($totalPages_reg > 1): ?>
                                            <nav class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-4"
                                                aria-label="Table navigation">

                                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                                    Showing
                                                    <span class="font-semibold text-gray-900 dark:text-white">
                                                        <?= $page_reg ?>
                                                    </span>
                                                    of
                                                    <span class="font-semibold text-gray-900 dark:text-white">
                                                        <?= $totalPages_reg ?>
                                                    </span>
                                                </span>

                                                <ul class="inline-flex items-stretch -space-x-px">
                                                    <!-- Prev Button -->
                                                    <li>
                                                        <?php if ($page_reg > 1): ?>
                                                            <a href="?section=registered_farmers&page_reg=<?= $page_reg - 1 ?>&search=<?= urlencode($search) ?>#registered-farmers"
                                                                class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-500 bg-white rounded-l-lg border border-gray-300 
                                    hover:bg-gray-100 hover:text-gray-700">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 
                                        3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 
                                        010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                                </svg>
                                                            </a>
                                                        <?php else: ?>
                                                            <span
                                                                class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-400 bg-gray-100 rounded-l-lg border border-gray-300">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 
                                        10l3.293 3.293a1 1 0 
                                        01-1.414 1.414l-4-4a1 1 0 
                                        010-1.414l4-4a1 1 0 
                                        011.414 0z" clip-rule="evenodd" />
                                                                </svg>
                                                            </span>
                                                        <?php endif; ?>
                                                    </li>

                                                    <!-- Page Numbers -->
                                                    <?php for ($i = 1; $i <= $totalPages_reg; $i++): ?>
                                                        <li>
                                                            <a href="?section=registered_farmers&page_reg=<?= $i ?>&search=<?= urlencode($search) ?>#registered-farmers"
                                                                class="flex items-center justify-center text-sm py-2 px-3 leading-tight 
                                border border-gray-300 
                                <?= ($i == $page_reg)
                                    ? 'bg-blue-500 text-white'
                                    : 'text-gray-500 bg-white hover:bg-gray-100 hover:text-gray-700'; ?>">
                                                                <?= $i ?>
                                                            </a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <!-- Next Button -->
                                                    <li>
                                                        <?php if ($page_reg < $totalPages_reg): ?>
                                                            <a href="?section=registered_farmers&page_reg=<?= $page_reg + 1 ?>&search=<?= urlencode($search) ?>#registered-farmers"
                                                                class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-500 
                                    bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 
                                        10 7.293 6.707a1 1 0 
                                        011.414-1.414l4 4a1 1 0 
                                        010 1.414l-4 4a1 1 0 
                                        -1.414 0z" clip-rule="evenodd" />
                                                                </svg>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-400 
                                    bg-gray-100 rounded-r-lg border border-gray-300">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 
                                        10 7.293 6.707a1 1 0 
                                        011.414-1.414l4 4a1 1 0 
                                        010 1.414l-4 4a1 1 0 
                                        -1.414 0z" clip-rule="evenodd" />
                                                                </svg>
                                                            </span>
                                                        <?php endif; ?>
                                                    </li>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="p-6 bg-white shadow rounded-lg text-center">
                                            <p class="text-red-500 font-semibold text-lg">No registered farmers found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                        </section>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <!-- 🔍 History View Modal -->
    <div id="activityLogModal" tabindex="-1" aria-hidden="true"
        class="hidden fixed inset-0 z-50 flex items-center justify-center w-full h-ful">
        <div class="relative bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="relative bg-white rounded-lg shadow">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-4 border-b rounded-t dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        User Activity Log
                    </h3>
                    <button type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        data-modal-hide="activityLogModal">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6-6M7 7l6 6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <!-- Modal body -->
                <div class="p-4 space-y-4 overflow-y-auto max-h-[65vh]">
                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-6 py-3">#</th>
                                    <th scope="col" class="px-6 py-3">Name</th>
                                    <th scope="col" class="px-6 py-3">Action</th>
                                    <th scope="col" class="px-6 py-3">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="activityLogBody">
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-gray-500">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>>
            </div>
        </div>
    </div>
    <!-- ✅ Generate ID Modal -->
    <div id="farmerModal" class="hidden fixed inset-0 bg-black/60 z-50 flex justify-center items-center p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 relative w-[500px]">

            <!-- ✅ ID Card -->
            <div class="bg-white border-2 border-black p-2 shadow-md h-full">
                <div class="border-2 border-dashed border-black p-2">

                    <!-- Header -->
                    <div class="text-center">
                        <img src="../assets/da_logo.png" alt="DA Logo" class="w-16 mx-auto flex justify-baseline">
                        <p class="text-[11px] leading-tight">Republic of the Philippines</p>
                        <p class="text-[11px] font-semibold leading-tight">DEPARTMENT OF AGRICULTURE</p>
                        <h1 class="text-[13px] uppercase font-bold mt-1 leading-snug">
                            NATIONAL REGISTRY SYSTEM FOR<br>FARMERS AND FISHERIES
                        </h1>
                    </div>

                    <!-- Info Box -->
                    <div class="border-b border-black p-2 ">
                        <div class="flex items-center justify-between gap-2">

                            <!-- Photo -->
                            <div
                                class="w-[85px] h-[85px] bg-gray-200 border border-gray-400 flex items-center justify-center">
                                <img id="farmerPhoto" src="../assets/profile.png" alt="Profile"
                                    class="w-[65%] opacity-80">
                            </div>

                            <!-- Full Name + Reference -->
                            <div class="flex-1 px-2">
                                <div class="text-[12px] font-bold">Full Name</div>
                                <div id="farmerFullName" class="text-[12px] mb-1">Loading...</div>
                                <div class="text-[12px] font-bold">RSBSA Reference No.</div>
                                <div id="farmerReference" class="text-[12px]">Loading...</div>
                            </div>
                            <!-- QR Code -->
                            <div class="flex items-center justify-center">
                                <canvas id="qrCode"
                                    class="w-[95px] h-[95px] border border-gray-300 rounded-sm"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Logos -->
                </div>

                <!-- Action Buttons -->

            </div>
            <div class="flex justify-end gap-1 mt-1 print:hidden">
                <button id="printBtn"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-md transition">
                    PRINT
                </button>
                <button id="closeModalBtn"
                    class="bg-gray-900 hover:bg-gray-800 text-white text-sm px-4 py-2 rounded-md transition">
                    CLOSE
                </button>
            </div>
        </div>
    </div>
    <script>
        // Add to your existing JavaScript
        const importBtn = document.getElementById('importBtn');
        const importDropdown = document.getElementById('importDropdown');

        if (importBtn && importDropdown) {
            importBtn.addEventListener('click', () => {
                importDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!importBtn.contains(e.target) && !importDropdown.contains(e.target)) {
                    importDropdown.classList.add('hidden');
                }
            });
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const farmersDropdownBtn = document.getElementById("farmersDropdownBtn");
            const farmersDropdownMenu = document.getElementById("farmersDropdownMenu");
            const dropdownArrow = document.getElementById("dropdownArrow");

            // --- Disable toggle behavior (dropdown always open) ---
            if (farmersDropdownMenu && dropdownArrow) {
                farmersDropdownMenu.classList.remove("hidden");
                dropdownArrow.classList.add("rotate-180");
                localStorage.setItem("farmersDropdownOpen", true);
            }

            // --- Show Table Function ---
            window.showTable = function (tableId, event) {
                const tables = ["allFarmersTable", "registeredTable"];
                const buttons = ["allFarmersBtn", "registeredBtn"];

                // Hide all tables
                tables.forEach(id => document.getElementById(id)?.classList.add("hidden"));

                // Show selected table
                document.getElementById(tableId)?.classList.remove("hidden");

                // Remove highlight
                buttons.forEach(btn => {
                    const el = document.getElementById(btn)?.querySelector("div");
                    if (el) el.classList.remove("bg-blue-200", "bg-green-200", "font-bold");
                });

                // Apply highlight to active button
                const activeBtn = event?.currentTarget?.querySelector("div");
                if (activeBtn) {
                    if (tableId === "allFarmersTable") activeBtn.classList.add("bg-blue-200", "font-bold");
                    if (tableId === "registeredTable") activeBtn.classList.add("bg-green-200", "font-bold");
                }

                // Show/hide appropriate buttons based on table
                const updateStatusContainer = document.getElementById("updateStatusContainer");
                const exportContainer = document.getElementById("exportContainer");

                if (tableId === "allFarmersTable") {
                    updateStatusContainer.classList.remove("hidden");
                    exportContainer.classList.add("hidden");
                } else if (tableId === "registeredTable") {
                    updateStatusContainer.classList.add("hidden");
                    exportContainer.classList.remove("hidden");
                }

                // Save active table
                localStorage.setItem("activeTable", tableId);
            };

            // --- Preserve active table before pagination ---
            document.querySelectorAll('a[href]').forEach(link => {
                link.addEventListener("click", () => {
                    const currentVisibleTable = !document.getElementById("registeredTable").classList.contains("hidden")
                        ? "registeredTable"
                        : "allFarmersTable";
                    localStorage.setItem("activeTable", currentVisibleTable);
                    localStorage.setItem("farmersDropdownOpen", true); // Always open
                });
            });
        });

        // --- Restore table after reload or pagination ---
        window.addEventListener("load", () => {
            const farmersDropdownMenu = document.getElementById("farmersDropdownMenu");
            const dropdownArrow = document.getElementById("dropdownArrow");

            // ✅ Keep dropdown always open
            farmersDropdownMenu.classList.remove("hidden");
            dropdownArrow.classList.add("rotate-180");
            localStorage.setItem("farmersDropdownOpen", true);

            // ✅ Restore table view
            const savedTable = localStorage.getItem("activeTable") || "allFarmersTable";
            const savedButton = document.querySelector(`[onclick*="${savedTable}"]`);
            if (savedButton) savedButton.click();
        });
    </script>
    <!-- print farmer -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const printButton = document.getElementById("printFarmerBtn");

            printButton.addEventListener("click", () => {
                // Get the farmer ID from your hidden input (you already set this in onclick)
                const farmerId = document.getElementById("farmer_id").value;

                if (!farmerId) {
                    alert("⚠️ Farmer ID not found. Please open a valid record first.");
                    return;
                }

                // ✅ Redirect to backend print page
                window.open(`print_farmer.php?id=${farmerId}`, "_blank");
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            let currentFarmerId = null;

            // When you open the "View Farmer" modal
            document.querySelectorAll('[data-modal-target="view-farmer-modal"]').forEach(btn => {
                btn.addEventListener("click", () => {
                    currentFarmerId = btn.getAttribute("data-registration-id");
                    console.log("👁 Viewing farmer ID:", currentFarmerId);
                });
            });

            // When you click "Add Livelihood" inside the View modal
            const addLivelihoodBtn = document.getElementById("open-livelihood-modal");
            const livelihoodInput = document.getElementById("registration_form_id");

            if (addLivelihoodBtn && livelihoodInput) {
                addLivelihoodBtn.addEventListener("click", () => {
                    if (currentFarmerId) {
                        livelihoodInput.value = currentFarmerId;
                        console.log("🧩 Set registration_form_id for livelihood modal:", currentFarmerId);
                    } else {
                        console.warn("⚠️ No farmer ID available when opening Add Livelihood modal!");
                    }
                });
            }
        });

    </script>
    <!-- for button cancel update -->
    <script>
        const editBtn = document.getElementById("editBtn");
        const cancelBtn = document.getElementById("cancelBtn");
        const updateBtn = document.getElementById("updateBtn");

        editBtn.addEventListener("click", () => {
            editBtn.classList.add("hidden");
            cancelBtn.classList.remove("hidden");
            updateBtn.classList.remove("hidden");
        });

        cancelBtn.addEventListener("click", () => {
            cancelBtn.classList.add("hidden");
            updateBtn.classList.add("hidden");
            editBtn.classList.remove("hidden");
        });
    </script>
    <!-- farmer selector -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const livelihood = document.getElementById("mainLivelihood");

            const farmerDiv = document.getElementById("farmerDiv");
            const farmerSelect = document.getElementById("farmerSelect");

            const farmerworkerDiv = document.getElementById("farmerworkerDiv");
            const farmerworkerSelect = document.getElementById("farmerworkerSelect");

            const fisherfolkDiv = document.getElementById("fisherfolkDiv");
            const fisherfolkSelect = document.getElementById("fisherfolkSelect");

            const youthDiv = document.getElementById("youthDiv");
            const youthSelect = document.getElementById("youthSelect");

            function hideDiv(divEl, selectEl) {
                if (divEl) divEl.style.display = "none";
                if (selectEl) selectEl.value = "";
            }

            function showDiv(divEl) {
                if (divEl) divEl.style.display = "block";
            }

            // Hide all dependent selects initially
            hideDiv(farmerDiv, farmerSelect);
            hideDiv(farmerworkerDiv, farmerworkerSelect);
            hideDiv(fisherfolkDiv, fisherfolkSelect);
            hideDiv(youthDiv, youthSelect);

            livelihood.addEventListener("change", function () {
                // hide all first
                hideDiv(farmerDiv, farmerSelect);
                hideDiv(farmerworkerDiv, farmerworkerSelect);
                hideDiv(fisherfolkDiv, fisherfolkSelect);
                hideDiv(youthDiv, youthSelect);

                // show only the selected
                if (this.value === "farmer") showDiv(farmerDiv);
                if (this.value === "farmerworker") showDiv(farmerworkerDiv);
                if (this.value === "fisherfolk") showDiv(fisherfolkDiv);
                if (this.value === "agri-youth") showDiv(youthDiv);
            });
        });
    </script>
    <!-- step function -->
    <script>
        let currentStep = 1;
        const totalSteps = 6; // adjust if you have more or fewer steps

        function showStep(step) {
            // hide all steps first
            for (let i = 1; i <= totalSteps; i++) {
                const el = document.getElementById("step" + i);
                if (el) {
                    el.style.display = (i === step) ? "block" : "none";
                }
            }
            currentStep = step;
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                showStep(currentStep + 1);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }

        // initialize on page load
        window.addEventListener("DOMContentLoaded", () => {
            showStep(currentStep);
        });
    </script>
    <!-- history modal -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Select all buttons that open the modal
            const modalButtons = document.querySelectorAll('[data-modal-target="activityLogModal"]');

            modalButtons.forEach(button => {
                button.addEventListener("click", async () => {
                    const tbody = document.getElementById("activityLogBody");
                    tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-3 text-gray-500">Loading activity logs...</td>
                </tr>
            `;
                    try {
                        const res = await fetch("fetch_activity_log.php", { cache: "no-store" });
                        const data = await res.json();

                        // Validate and check if success
                        if (!data.success) {
                            throw new Error(data.message || "Failed to fetch logs");
                        }

                        const logs = data.data;
                        if (!logs || logs.length === 0) {
                            tbody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-3 text-gray-500">No activity logs found.</td>
                        </tr>
                    `;
                            return;
                        }

                        // Build rows efficiently
                        const rows = logs.map((log, i) => `
                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        <td class="px-6 py-4">${i + 1}</td>
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">${log.user}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300">${log.action}</td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">${log.created_at}</td>
                    </tr>
                `).join("");

                        tbody.innerHTML = rows;
                    } catch (err) {
                        console.error("❌ Error loading activity logs:", err);
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-3 text-red-500">Error loading logs. Please try again later.</td>
                    </tr>
                `;
                    }
                });
            });
        });
    </script>
    <!-- export-dropdown -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const exportBtn = document.getElementById('exportBtn');
            const dropdown = document.getElementById('exportDropdown');

            exportBtn.addEventListener('click', () => {
                dropdown.classList.toggle('hidden');
            });

            // Close dropdown if click outside
            document.addEventListener('click', (e) => {
                if (!exportBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        });
    </script>
    <!-- autorefreshafterupdate -->
    <script>
        // ✅ --- Checkbox & Dropdown Logic ---
        function clearCheckboxes() {
            document.querySelectorAll(".rowCheckbox").forEach(cb => cb.checked = false);
            const selectAll = document.getElementById("selectAll");
            if (selectAll) selectAll.checked = false;
        }

        function disableStatusButton() {
            const statusBtn = document.getElementById("statusDropdownButton");
            if (statusBtn) statusBtn.disabled = true;
        }

        function updateStatusButtonState() {
            const anyChecked = document.querySelectorAll(".rowCheckbox:checked").length > 0;
            const statusBtn = document.getElementById("statusDropdownButton");
            if (statusBtn) statusBtn.disabled = !anyChecked;
        }

        // ✅ Attach listeners to all current checkboxes
        function attachCheckboxListeners() {
            const checkboxes = document.querySelectorAll(".rowCheckbox");
            const selectAll = document.getElementById("selectAll");

            checkboxes.forEach(cb => {
                cb.addEventListener("change", updateStatusButtonState);
            });

            if (selectAll) {
                selectAll.addEventListener("change", (e) => {
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                    updateStatusButtonState();
                });
            }
        }

        // ✅ On page load
        document.addEventListener("DOMContentLoaded", () => {
            clearCheckboxes();
            disableStatusButton();
            attachCheckboxListeners();
        });

        // ✅ After page show (handles back/forward navigation)
        window.addEventListener("pageshow", () => {
            clearCheckboxes();
            disableStatusButton();
            attachCheckboxListeners();
        });

        // ✅ When you reload or leave
        window.addEventListener("beforeunload", () => {
            clearCheckboxes();
            sessionStorage.removeItem("selectedIds");
            sessionStorage.removeItem("allSelectedAcrossPages");
        });

        // ✅ --- Integration Hook ---
        // Call this after AJAX table update to reattach listeners
        function refreshCheckboxBehaviorAfterUpdate() {
            clearCheckboxes();
            disableStatusButton();
            attachCheckboxListeners();
        }
    </script>
    <!-- liveSearch -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const searchInput = document.getElementById("simple-search");
            let searchTimeout;

            function performSearch(searchTerm) {
                console.log("Searching for:", searchTerm);

                fetch("search.php?search=" + encodeURIComponent(searchTerm))
                    .then(res => {
                        if (!res.ok) throw new Error('Network response was not ok');
                        return res.json();
                    })
                    .then(data => {
                        console.log("Search results received");

                        // Update both tables
                        const newResults = document.getElementById("results_new");
                        const registeredResults = document.getElementById("results_registered");

                        if (newResults) newResults.innerHTML = data.new;
                        if (registeredResults) registeredResults.innerHTML = data.registered;

                        // Reattach event listeners
                        bindDropdowns();
                        attachCheckboxListeners();
                        attachArchiveListeners();
                        attachGenerateIdListeners();
                    })
                    .catch(err => {
                        console.error("❌ Fetch error:", err);
                        // Show error in tables
                        const errorHtml = "<tr><td colspan='11' class='px-4 py-4 text-center text-red-500'>Search error. Please try again.</td></tr>";
                        const newResults = document.getElementById("results_new");
                        const registeredResults = document.getElementById("results_registered");

                        if (newResults) newResults.innerHTML = errorHtml;
                        if (registeredResults) registeredResults.innerHTML = errorHtml;
                    });
            }

            searchInput.addEventListener("input", function () {
                const searchTerm = this.value.trim();

                // Clear previous timeout
                clearTimeout(searchTimeout);

                // Set new timeout (debounce search)
                searchTimeout = setTimeout(() => {
                    if (searchTerm.length === 0 || searchTerm.length >= 2) {
                        performSearch(searchTerm);
                    }
                }, 300);
            });

            // Clear search when clear button is clicked
            const clearBtn = document.getElementById("clearBtn");
            if (clearBtn) {
                clearBtn.addEventListener("click", function () {
                    searchInput.value = '';
                    performSearch('');
                    this.classList.add('hidden');
                });
            }

            // Show/hide clear button based on input
            searchInput.addEventListener('input', function () {
                const clearBtn = document.getElementById("clearBtn");
                if (clearBtn) {
                    if (this.value.trim() !== '') {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }
            });

            function bindDropdowns() {
                document.querySelectorAll(".dropdown-toggle").forEach(btn => {
                    // Remove existing listeners
                    btn.replaceWith(btn.cloneNode(true));
                });

                document.querySelectorAll(".dropdown-toggle").forEach(btn => {
                    btn.onclick = function (e) {
                        e.stopPropagation();
                        const menu = this.nextElementSibling;

                        // Close other dropdowns
                        document.querySelectorAll(".dropdown-menu").forEach(m => {
                            if (m !== menu) m.classList.add("hidden");
                        });

                        // Toggle current dropdown
                        menu.classList.toggle("hidden");
                    };
                });

                // Close dropdowns when clicking outside
                document.addEventListener("click", () => {
                    document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.add("hidden"));
                });
            }

            function attachCheckboxListeners() {
                const checkboxes = document.querySelectorAll(".rowCheckbox");
                const selectAll = document.getElementById("selectAll");

                checkboxes.forEach(cb => {
                    cb.addEventListener("change", updateStatusButtonState);
                });

                if (selectAll) {
                    selectAll.addEventListener("change", (e) => {
                        checkboxes.forEach(cb => cb.checked = e.target.checked);
                        updateStatusButtonState();
                    });
                }
            }

            function attachArchiveListeners() {
                document.querySelectorAll(".archiveAction").forEach(btn => {
                    btn.addEventListener("click", function () {
                        const id = this.dataset.id;
                        if (!confirm("Archive this farmer?")) return;

                        fetch("archive.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "id=" + id
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert("Archived successfully!");
                                    location.reload();
                                } else {
                                    alert("Error: " + data.error);
                                }
                            })
                            .catch(err => {
                                console.error("Archive error:", err);
                                alert("Archive failed. Please try again.");
                            });
                    });
                });
            }

            function attachGenerateIdListeners() {
                document.querySelectorAll(".generateIdBtn").forEach(btn => {
                    btn.addEventListener("click", function () {
                        const id = this.dataset.id;
                        // Your existing generate ID modal code here
                        console.log("Generate ID for:", id);
                    });
                });
            }

            // Initialize on page load
            bindDropdowns();
            attachCheckboxListeners();
            attachArchiveListeners();
            attachGenerateIdListeners();
        });
    </script>
    <!-- Update status auto reload -->
    <script>
        document.addEventListener("click", (e) => {
            const toggle = e.target.closest(".dropdown-toggle");
            const statusOption = e.target.closest(".statusOption");

            // ✅ If clicking a dropdown toggle
            if (toggle) {
                const dropdown = toggle.nextElementSibling;
                if (dropdown && dropdown.classList.contains("dropdown-menu")) {
                    // Close all other dropdowns
                    document.querySelectorAll(".dropdown-menu").forEach(m => {
                        if (m !== dropdown) m.classList.add("hidden");
                    });
                    // Toggle this one
                    dropdown.classList.toggle("hidden");
                }
                return;
            }

            // ✅ If clicking a status option
            if (statusOption) {
                e.preventDefault();
                e.stopPropagation();

                const newStatus = statusOption.dataset.status;
                if (typeof updateStatus === "function") {
                    updateStatus(newStatus);
                }

                // Close related dropdown
                const dropdownMenu = statusOption.closest(".dropdown-menu");
                const statusButton = dropdownMenu?.previousElementSibling || null;
                if (dropdownMenu) dropdownMenu.classList.add("hidden");

                if (statusButton) {
                    statusButton.setAttribute("aria-expanded", "false");
                    statusButton.blur();
                    statusButton.disabled = true;
                }

                // Clear all row checkboxes
                document.querySelectorAll(".rowCheckbox").forEach(cb => cb.checked = false);
                const selectAll = document.getElementById("selectAll");
                if (selectAll) selectAll.checked = false;

                return;
            }

            // ✅ Click outside → close all dropdowns
            if (!e.target.closest(".dropdown-menu")) {
                document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.add("hidden"));
            }
        });
    </script>
    <!-- update status and archive -->
    <script>
        const statusButton = document.getElementById("statusDropdownButton");
        const selectAll = document.getElementById("selectAll");
        const statusDropdown = document.getElementById("statusDropdown");
        const statusInfo = document.getElementById("statusUpdateInfo");
        let isUpdating = false;

        // --- Helper: get all row checkboxes ---
        function getCheckboxes() {
            return Array.from(document.querySelectorAll(".rowCheckbox"));
        }

        // --- Enable/disable status button ---
        function toggleStatusButton() {
            const anyChecked = getCheckboxes().some(cb => cb.checked);
            statusButton.disabled = !anyChecked;
        }

        // --- Select All handler ---
        if (selectAll) {
            selectAll.addEventListener("change", function () {
                getCheckboxes().forEach(cb => cb.checked = this.checked);
                toggleStatusButton();
            });
        }

        // --- Individual checkbox listener ---
        document.addEventListener("change", e => {
            if (e.target.matches(".rowCheckbox")) toggleStatusButton();
        });

        // --- Update Status function ---
        function updateStatus(status) {
            if (isUpdating) return;
            isUpdating = true;

            const selectedIds = getCheckboxes()
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert("Please select at least one record.");
                isUpdating = false;
                return;
            }

            fetch("update_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ ids: selectedIds, status })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Log activity for each updated record
                        selectedIds.forEach(id => {
                            const row = document.querySelector(`tr[data-id='${id}']`);
                            if (row) {
                                const farmerName = row.querySelector('td:nth-child(3)').textContent.trim();

                                // Log the status update activity
                                fetch('log_activity.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        action: 'UPDATE_STATUS',
                                        description: `Changed status to ${data.status} for: ${farmerName}`,
                                        table_name: 'registration_form',
                                        record_id: id,
                                        farmer_name: farmerName,
                                        new_status: data.status
                                    })
                                }).catch(err => console.error('Activity logging error:', err));
                            }
                        });

                        data.ids.forEach(id => {
                            const row = document.querySelector(`tr[data-id='${id}']`);
                            if (row) {
                                const cell = row.querySelector(".statusCell");

                                if (cell) {
                                    cell.textContent = data.status;
                                    cell.classList.remove("text-green-600", "text-red-600");
                                    cell.classList.add(data.status === "Registered" ? "text-green-600" : "text-red-600");
                                }

                                if (data.status === "Registered") {
                                    const registeredTableBody = document.querySelector("#registeredTable tbody");
                                    if (registeredTableBody) {
                                        const clone = row.cloneNode(true);
                                        registeredTableBody.appendChild(clone);
                                    }
                                    row.remove();
                                }
                            }
                        });

                        statusInfo.innerHTML = `<div class="text-green-600 font-semibold">✅ Status updated successfully!</div>`;
                        setTimeout(() => statusInfo.innerHTML = "", 2500);

                        getCheckboxes().forEach(cb => cb.checked = false);
                        if (selectAll) selectAll.checked = false;
                        toggleStatusButton();

                        statusDropdown.classList.add("hidden");
                    } else {
                        statusInfo.innerHTML = `<div class="text-red-600 font-semibold">❌ ${data.error}</div>`;
                    }
                })
                .catch(err => {
                    console.error("Fetch error:", err);
                    statusInfo.innerHTML = `<div class="text-red-600 font-semibold">❌ Failed to update status. Try again.</div>`;
                })
                .finally(() => isUpdating = false);
        }

        // ✅ ARCHIVE FUNCTION WITH ACTIVITY LOGGING
        document.addEventListener('DOMContentLoaded', function () {
            // Remove all existing event listeners from archive buttons
            const archiveButtons = document.querySelectorAll('.archiveAction');
            archiveButtons.forEach(btn => {
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
            });

            // Add single event listener
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('archiveAction') || e.target.closest('.archiveAction')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    const archiveBtn = e.target.classList.contains('archiveAction') ? e.target : e.target.closest('.archiveAction');
                    const id = archiveBtn.getAttribute('data-id');
                    const row = archiveBtn.closest('tr');
                    const farmerName = row.querySelector('td:nth-child(3)').textContent.trim();

                    if (confirm(`Are you sure you want to archive ${farmerName}?`)) {
                        // Disable button immediately
                        archiveBtn.disabled = true;
                        archiveBtn.innerHTML = 'Archiving...';

                        // Log the archive activity BEFORE archiving (so we have the record)
                        fetch('log_activity.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'ARCHIVE',
                                description: `Started archiving farmer: ${farmerName}`,
                                table_name: 'registration_form',
                                record_id: id,
                                farmer_name: farmerName,
                                timestamp: new Date().toISOString()
                            })
                        }).catch(err => console.error('Activity logging error:', err));

                        fetch('archive.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'id=' + id
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Log successful archive activity
                                    fetch('log_activity.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            action: 'ARCHIVE_COMPLETE',
                                            description: `Successfully archived farmer: ${farmerName}`,
                                            table_name: 'registration_form',
                                            record_id: id,
                                            farmer_name: farmerName,
                                            status: 'Archived'
                                        })
                                    }).catch(err => console.error('Activity logging error:', err));

                                    // Remove row with animation
                                    row.style.opacity = '0';
                                    row.style.transition = 'opacity 0.3s';
                                    setTimeout(() => {
                                        row.remove();

                                        // Show success message
                                        const successMsg = document.createElement('div');
                                        successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                                        successMsg.innerHTML = `<i class="fas fa-check mr-2"></i> ✅ ${farmerName} archived successfully!`;
                                        document.body.appendChild(successMsg);

                                        setTimeout(() => {
                                            successMsg.style.opacity = '0';
                                            successMsg.style.transition = 'opacity 0.5s';
                                            setTimeout(() => successMsg.remove(), 500);
                                        }, 3000);
                                    }, 300);
                                } else {
                                    // Log failed archive attempt
                                    fetch('log_activity.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            action: 'ARCHIVE_FAILED',
                                            description: `Failed to archive farmer: ${farmerName}. Error: ${data.error}`,
                                            table_name: 'registration_form',
                                            record_id: id,
                                            farmer_name: farmerName,
                                            error: data.error
                                        })
                                    }).catch(err => console.error('Activity logging error:', err));

                                    alert('❌ Error: ' + data.error);
                                    archiveBtn.disabled = false;
                                    archiveBtn.innerHTML = 'Archive';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);

                                // Log network error
                                fetch('log_activity.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        action: 'ARCHIVE_ERROR',
                                        description: `Network error while archiving farmer: ${farmerName}`,
                                        table_name: 'registration_form',
                                        record_id: id,
                                        farmer_name: farmerName,
                                        error: error.message
                                    })
                                }).catch(err => console.error('Activity logging error:', err));

                                alert('❌ Network error. Please try again.');
                                archiveBtn.disabled = false;
                                archiveBtn.innerHTML = 'Archive';
                            });
                    }
                }
            }, true); // Use capture phase
        });

        // --- Dropdown toggle ---
        statusButton.addEventListener("click", () => {
            statusDropdown.classList.toggle("hidden");
        });

        // --- Click outside dropdown ---
        document.addEventListener("click", e => {
            if (!e.target.closest("#statusDropdown") && !e.target.closest("#statusDropdownButton")) {
                statusDropdown.classList.add("hidden");
            }
        });

        // --- Init ---
        toggleStatusButton();
    </script>
    <!-- update-status-message -->
    <script>
        async function updateStatus(newStatus) {
            const checkboxes = document.querySelectorAll(".rowCheckbox:checked");
            const ids = Array.from(checkboxes).map(cb => cb.value);
            const statusDropdownButton = document.getElementById("statusDropdownButton");

            if (ids.length === 0) {
                alert("Please select at least one record to update.");
                return;
            }

            // ✅ Disable dropdown button during update
            if (statusDropdownButton) statusDropdownButton.disabled = true;

            try {
                const response = await fetch("update_status.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ ids, status: newStatus })
                });

                const data = await response.json();

                if (data.success) {
                    // ✅ Update only the status cells
                    ids.forEach(id => {
                        const row = document.querySelector(`tr[data-id='${id}']`);
                        if (row) {
                            const statusCell = row.querySelector(".statusCell");
                            if (statusCell) {
                                statusCell.textContent = data.status;
                                statusCell.classList.add("text-green-700", "font-semibold");
                            }
                        }
                    });

                    // ✅ Create floating message box
                    const namesList = data.names.join(", ");

                    // Get exact local time
                    const now = new Date();
                    const formattedTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                    const messageBox = document.createElement("div");
                    messageBox.className = `
                    fixed top-5 right-5 bg-green-100 text-green-800 
                    border border-green-300 rounded-lg shadow-lg p-4 
                    w-80 z-50 transition-all transform ease-in-out duration-300
                    `;
                    messageBox.innerHTML = `
                    <div class="font-semibold mb-1">✅ Status Updated!</div>
                    <div class="text-sm">
                        <strong>Names:</strong> ${namesList}<br>
                        <strong>Action:</strong> Updated status to 
                        <span class="font-semibold">${data.status}</span><br>
                        <small>${formattedTime}</small>
                    </div>
                    `;
                    document.body.appendChild(messageBox);

                    // ✅ Smooth fade-out after 10 seconds, then auto-refresh
                    setTimeout(() => {
                        messageBox.style.opacity = "0";
                        setTimeout(() => {
                            messageBox.remove();
                            // ✅ Automatically refresh the page
                            window.location.reload();
                        }, 500);
                    }, 10000);

                    // ✅ Clear selected checkboxes
                    document.querySelectorAll(".rowCheckbox").forEach(cb => cb.checked = false);
                    const selectAll = document.getElementById("selectAll");
                    if (selectAll) selectAll.checked = false;

                    // ✅ Close dropdown menu
                    const dropdownMenu = document.getElementById("statusDropdown");
                    if (dropdownMenu) {
                        dropdownMenu.classList.add("hidden");
                        dropdownMenu.classList.remove("block");
                        dropdownMenu.style.display = "none";
                    }

                } else {
                    alert("⚠️ Update failed: " + (data.error || "Unknown error"));
                    if (statusDropdownButton) statusDropdownButton.disabled = false;
                }
            } catch (error) {
                console.error("Error updating status:", error);
                alert("Something went wrong. Please try again.");
                if (statusDropdownButton) statusDropdownButton.disabled = false;
            }
        }

        // ✅ Attach click handlers to dropdown options
        document.querySelectorAll(".statusOption").forEach(option => {
            option.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                const newStatus = option.dataset.status;
                updateStatus(newStatus);
            });
        });
    </script>
    <!-- checkboxes -->
    <script>
        // Grab all checkboxes
        const selectAll = document.getElementById("selectAll");
        const checkboxes = document.querySelectorAll(".rowCheckbox");

        // Function to toggle all row checkboxes
        if (selectAll) {
            selectAll.addEventListener("change", function () {
                checkboxes.forEach(cb => cb.checked = this.checked);
                toggleStatusButton(); // enable/disable Update Status button
            });
        }

        // Enable/disable Update Status button & manage Select All state
        checkboxes.forEach(cb => {
            cb.addEventListener("change", function () {
                toggleStatusButton();

                // If any checkbox is unchecked, uncheck Select All
                if (!this.checked) {
                    selectAll.checked = false;
                } else {
                    // If all checkboxes are checked, set Select All to checked
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    selectAll.checked = allChecked;
                }
            });
        });
    </script>
    <!-- for generate id modal -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const modal = document.getElementById("farmerModal");
            const printBtn = document.getElementById("printBtn");
            const closeModalBtn = document.getElementById("closeModalBtn");
            const farmerName = document.getElementById("farmerFullName");
            const farmerReference = document.getElementById("farmerReference");
            const qrCanvas = document.getElementById("qrCode");

            // 🧩 Ensure QR only renders once per open
            const generateQR = (text) => {
                QRCode.toCanvas(qrCanvas, text, { width: 95 }, (error) => {
                    if (error) console.error(error);
                });
            };

            // 🧩 Attach event to Generate ID buttons (remove old listeners first)
            document.querySelectorAll(".generateIdBtn").forEach(btn => {
                btn.replaceWith(btn.cloneNode(true)); // remove old listener safely
            });

            document.querySelectorAll(".generateIdBtn").forEach(btn => {
                btn.addEventListener("click", async () => {
                    const id = btn.dataset.id;

                    // Show modal
                    modal.classList.remove("hidden");
                    farmerName.textContent = "Loading...";
                    farmerReference.textContent = "Loading...";

                    try {
                        const res = await fetch(`generate_farmer.php?id=${id}`);
                        const data = await res.json();

                        if (data.status === "success") {
                            farmerName.textContent = data.full_name;
                            farmerReference.textContent = data.reference;
                            generateQR(`${data.full_name}\nRef: ${data.reference}`);
                        } else {
                            farmerName.textContent = "Not found";
                            farmerReference.textContent = "-";
                        }
                    } catch (err) {
                        console.error(err);
                        farmerName.textContent = "Error loading";
                        farmerReference.textContent = "-";
                    }
                });
            });

            // 🧩 Prevent multiple print triggers
            printBtn.replaceWith(printBtn.cloneNode(true));
            const newPrintBtn = document.getElementById("printBtn");
            newPrintBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                setTimeout(() => window.print(), 200);
            });

            // 🧩 Close modal
            closeModalBtn.addEventListener("click", () => {
                modal.classList.add("hidden");
            });
        });
    </script>
    <script>
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
    </script>
    <script>
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

            if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add("opacity-0", "scale-95");
                setTimeout(() => dropdown.classList.add("hidden"), 150);
            }
        });

        // Enhanced profile menu auto-opening when returning from profile pages
        document.addEventListener('DOMContentLoaded', function () {
            // Method 1: Check URL parameter (highest priority)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('profileMenu') === 'open') {
                openProfileDropdown();
                // Clean URL without reload
                window.history.replaceState({}, '', window.location.pathname);
            }

            // Method 2: Check sessionStorage
            if (sessionStorage.getItem('keepProfileOpen') === 'true') {
                openProfileDropdown();
                sessionStorage.removeItem('keepProfileOpen');
            }

            // Method 3: Check referrer (if coming from profile pages)
            const referrer = document.referrer;
            const profilePages = ['profile.php', 'login_history.php', 'user_management.php'];
            const isFromProfilePage = profilePages.some(page => referrer.includes(page));

            if (isFromProfilePage && !urlParams.get('profileMenu')) {
                // Small delay to ensure all other DOM manipulations are complete
                setTimeout(openProfileDropdown, 300);
            }

            function openProfileDropdown() {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown && dropdown.classList.contains("hidden")) {
                    console.log("Auto-opening profile dropdown");
                    dropdown.classList.remove("hidden");
                    setTimeout(() => {
                        dropdown.classList.remove("opacity-0", "scale-95");
                        dropdown.classList.add("opacity-100", "scale-100");
                    }, 50);
                }
            }
        });
    </script>
    <script>
        function confirmLogout(event) {
            if (!confirm("Are you sure you want to logout?")) {
                event.preventDefault(); // Prevent the default link behavior
                return false;
            }
            // If confirmed, the link will naturally proceed to logout.php
        }
    </script>
    <script src="../js/tailwind.config.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</body>

</html>