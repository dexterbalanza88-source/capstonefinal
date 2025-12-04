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

// 1. Results per page
$limit = 10;

// 2. Current page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// 3. Search input
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// 4. Where clause for search
$whereClause = "";
if (!empty($search)) {
    $whereClause = " WHERE s_name LIKE '%$search%' 
                     OR f_name LIKE '%$search%' 
                     OR m_name LIKE '%$search%' 
                     OR brgy LIKE '%$search%' 
                     OR mobile LIKE '%$search%'";
}

// 5. Count total records (REGISTERED + SEARCH)
$countSql = "
    SELECT 
        COUNT(DISTINCT f.id) AS total
    FROM registration_form f
    LEFT JOIN livelihoods l ON f.id = l.registration_form_id
    LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
    WHERE f.status = 'Registered'
        " . (!empty($search) ? " AND (
                f.s_name LIKE '%$search%' OR
                f.f_name LIKE '%$search%' OR
                f.m_name LIKE '%$search%' OR
                f.brgy LIKE '%$search%' OR
                f.mobile LIKE '%$search%'
            )" : "");
$countResult = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ($totalRows > 0) ? ceil($totalRows / $limit) : 1;

// 6. Calculate starting row
$offset = ($page - 1) * $limit;

// 7. Fetch data with limit + search + registered only
$sql = "
    SELECT 
        f.id, 
        f.s_name, 
        f.f_name, 
        f.m_name, 
        f.brgy, 
        f.mobile, 
        f.dob, 
        f.gender, 
        f.total_farmarea, 
        f.status,
        GROUP_CONCAT(
            DISTINCT
            COALESCE(sa.sub_name, '')
            SEPARATOR ', '
        ) AS livelihoodsList
    FROM registration_form f
    LEFT JOIN livelihoods l ON f.id = l.registration_form_id
    LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
    WHERE f.status = 'Registered'
        " . (!empty($search) ? " AND (
                f.s_name LIKE '%$search%' OR
                f.f_name LIKE '%$search%' OR
                f.m_name LIKE '%$search%' OR
                f.brgy LIKE '%$search%' OR
                f.mobile LIKE '%$search%'
            )" : "") . "
    GROUP BY f.id
    ORDER BY f.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
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
      html,
    body {
        height: 100%;
        overflow: hidden;
        /* 🔥 Prevents the outer scroll */
    }
</style>

<body>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
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

                            <!-- Divider -->
                            <li class="border-t my-2"></li>

                            <!-- Admin Tools Label -->
                            <li class="px-5 pb-1 text-xs font-semibold text-gray-500 uppercase">
                                Admin Tools
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

                    <a href="datalist.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">

                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>

                        <span class="ml-3 flex-1">Data List</span>

                        <!-- 🔽 Dropdown arrow only for visual; not interactive -->
                        <svg class="w-4 h-4 ml-auto text-gray-700" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>

                </ul>

                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="report.php"
                            class="flex items-center p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
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
        <main class="md:ml-64 pt-20 width-full h-screen overflow-y-auto">
            <!-- Start coding here -->
            <div class="relative flex items-center justify-center ">
                <!-- Centered Title -->
                <h1 class="text-black font-bold text-4xl text-center w-full">Report</h1>
                <!-- Top-right Generate Button -->
                <button onclick="generateReport()" type="button"
                    class="absolute right-8 top-0 flex items-center text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium shadow rounded-lg text-base px-5 py-2.5 transition">
                    <span>Generate</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="ml-2" width="18" height="18" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 
            1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 
            1-2 2h-2" />
                        <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 
            0 0 1 1 1v6" />
                        <rect x="6" y="14" width="12" height="8" rx="1" />
                    </svg>
                </button>
            </div>

            <section class="bg-gray-50 dark:bg-gray-900 p-2 sm:p-2 ">
                <div class="mx-auto max-w-screen-xl px-4">
                    <!-- Start coding here -->
                    <div class="bg-white dark:bg-gray-800 shadow-md sm:rounded-lg ">
                        <div
                            class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class=" md:w-1/2 w-full">
                                <form id="searchForm" class="flex items-center relative w-full"
                                    onsubmit="return false;">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <!-- Search icon -->
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
                                            class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg w-full pl-10 pr-10 p-2"
                                            onkeyup="liveSearch()">

                                        <button type="button" id="clearBtn" onclick="clearSearch()"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 hidden">&times;</button>
                                    </div>
                                </form>
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-center gap-3 w-full bg-white/50 p-3 rounded-lg">

                                <!-- REPORT TYPE -->
                                <div class="relative">
                                    <button id="reportDropdownButton" data-dropdown-toggle="reportDropdown"
                                        class="min-w-[160px] flex items-center justify-between py-2 px-4 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-300 hover:bg-gray-100 hover:text-green-700"
                                        type="button">
                                        <span id="reportLabel">Report Type</span>
                                        <svg class="w-4 h-4 text-gray-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 
                                                10.586l3.293-3.293a1 1 0 
                                                111.414 1.414l-4 4a1 1 0 
                                                01-1.414 0l-4-4a1 1 0 
                                                010-1.414z" />
                                        </svg>
                                    </button>
                                    <div id="reportDropdown"
                                        class="absolute hidden w-56 bg-white rounded-lg shadow z-50">
                                        <ul class="text-sm text-gray-700 p-2 space-y-1 ">
                                            <li><label><input type="checkbox" name="reportType" value="farmerList"
                                                        class="reportTypeCheckbox mr-1"> Farmer List</label></li>
                                            <li><label><input type="checkbox" name="reportType" value="livelihood"
                                                        class="reportTypeCheckbox mr-1"> Livelihood</label></li>
                                            <li>
                                                <label>
                                                    <input type="checkbox" name="reportType" value="ageReport"
                                                        class="reportTypeCheckbox mr-1">
                                                    Age Report
                                                </label>
                                            </li>
                                            <li><label><input type="checkbox" name="reportType" value="submission"
                                                        class="reportTypeCheckbox mr-1"> Barangay Submission</label>
                                            </li>
                                            <li><label><input type="checkbox" name="reportType" value="summary"
                                                        class="reportTypeCheckbox mr-1"> Summary</label></li>
                                            <li>
                                                <label>
                                                    <input type="checkbox" name="reportType" value="genderReport"
                                                        class="reportTypeCheckbox mr-1">
                                                    Gender Report per Barangay
                                                </label>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- YEAR -->
                                <div class="relative">
                                    <button id="yearDropdownButton" data-dropdown-toggle="yearDropdown"
                                        class="min-w-[130px] flex items-center justify-between py-2 px-4 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-300 hover:bg-gray-100 hover:text-green-700"
                                        type="button">
                                        <span id="yearLabel">Year</span>
                                        <svg class="w-4 h-4 text-gray-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 
                                    011.414 0L10 10.586l3.293-3.293a1 1 0 
                                    111.414 1.414l-4 4a1 1 0 
                                    01-1.414 0l-4-4a1 1 0 
                                    010-1.414z" />
                                        </svg>
                                    </button>
                                    <div id="yearDropdown" class="absolute hidden w-48 bg-white rounded-lg shadow z-50">
                                        <ul class="text-sm text-gray-700 p-2 space-y-1">
                                            <li><label><input type="checkbox" id="selectAllYears"
                                                        class="mr-1"><strong>Select All</strong></label></li>
                                            <li><label><input type="checkbox" value="2025"
                                                        class="yearCheckbox mr-1">2025</label></li>
                                            <li><label><input type="checkbox" value="2024"
                                                        class="yearCheckbox mr-1">2024</label></li>
                                            <li><label><input type="checkbox" value="2023"
                                                        class="yearCheckbox mr-1">2023</label></li>
                                            <li><label><input type="checkbox" value="2022"
                                                        class="yearCheckbox mr-1">2022</label></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- MONTH -->
                                <div class="relative">
                                    <button id="monthDropdownButton" data-dropdown-toggle="monthDropdown"
                                        class="min-w-[130px] flex items-center justify-between py-2 px-4 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-300 hover:bg-gray-100 hover:text-green-700"
                                        type="button">
                                        <span id="monthLabel">Month</span>
                                        <svg class="w-4 h-4 text-gray-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 
                                        011.414 0L10 10.586l3.293-3.293a1 1 0 
                                        111.414 1.414l-4 4a1 1 0 
                                        01-1.414 0l-4-4a1 1 0 
                                        010-1.414z" />
                                        </svg>
                                    </button>
                                    <div id="monthDropdown"
                                        class="absolute hidden w-48 bg-white rounded-lg shadow z-50 max-h-60 overflow-y-auto">
                                        <ul class="text-sm text-gray-700 p-2 space-y-1">
                                            <li><label><input type="checkbox" id="selectAllMonths"
                                                        class="mr-1"><strong>Select All</strong></label></li>
                                            <script>
                                                const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                                                document.write(months.map(m => `<li><label><input type="checkbox" value="${m}" class="monthCheckbox mr-1">${m}</label></li>`).join(''));
                                            </script>
                                        </ul>
                                    </div>
                                </div>

                                <!-- BARANGAY -->
                                <div class="relative">
                                    <button id="filterDropdownButton" data-dropdown-toggle="filterDropdown"
                                        class="min-w-[150px] flex items-center justify-between py-2 px-4 text-sm font-medium text-gray-900 bg-white rounded-lg border border-gray-300 hover:bg-gray-100 hover:text-green-700"
                                        type="button">
                                        <span id="barangayLabel">Barangay</span>
                                        <svg class="w-4 h-4 text-gray-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 
                                            011.414 0L10 10.586l3.293-3.293a1 1 0 
                                            111.414 1.414l-4 4a1 1 0 
                                            01-1.414 0l-4-4a1 1 0 
                                            010-1.414z" />
                                        </svg>
                                    </button>
                                    <div id="filterDropdown"
                                        class="absolute hidden w-56 bg-white rounded-lg shadow z-50 max-h-60 overflow-y-auto">
                                        <ul class="text-sm text-gray-700 p-2 space-y-1">
                                            <li><label><input type="checkbox" id="selectAllBarangays"
                                                        class="mr-1"><strong>Select All</strong></label></li>
                                            <li><label><input type="checkbox" value="Armado"
                                                        class="filterCheckbox mr-1">Armado</label></li>
                                            <li><label><input type="checkbox" value="Balao"
                                                        class="filterCheckbox mr-1">Balao</label></li>
                                            <li><label><input type="checkbox" value="Cabacao"
                                                        class="filterCheckbox mr-1">Cabacao</label></li>
                                            <li><label><input type="checkbox" value="Lumangbayan"
                                                        class="filterCheckbox mr-1">Lumangbayan</label></li>
                                            <li><label><input type="checkbox" value="Poblacion"
                                                        class="filterCheckbox mr-1">Poblacion</label></li>
                                            <li><label><input type="checkbox" value="San Vicente"
                                                        class="filterCheckbox mr-1">San Vicente</label></li>
                                            <li><label><input type="checkbox" value="Sta. Maria"
                                                        class="filterCheckbox mr-1">Sta. Maria</label></li>
                                            <li><label><input type="checkbox" value="Tibag"
                                                        class="filterCheckbox mr-1">Tibag</label></li>
                                            <li><label><input type="checkbox" value="Udalo"
                                                        class="filterCheckbox mr-1">Udalo</label></li>
                                            <li><label><input type="checkbox" value="Wawa"
                                                        class="filterCheckbox mr-1">Wawa</label></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="">
                            <?php if ($totalRows > 0): ?>
                                <table id="yourTableId"
                                    class="w-full text-xs text-left text-gray-500 bg-white shadow rounded-lg ">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0 z-10">
                                        <tr>
                                            <th class=" px-4 py-3">No</th>
                                            <th class="px-4 py-3">Name</th>
                                            <th class="px-4 py-3">Address</th>
                                            <th class="px-4 py-3">Contact</th>
                                            <th class="px-4 py-3">Age</th>
                                            <th class="px-4 py-3">DOB</th>
                                            <th class="px-4 py-3">Gender</th>
                                            <th class="px-4 py-3">Farm Size</th>
                                            <th class="px-4 py-3">Type of Commodity</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportTableContainer" class="yourTableId tbody results">
                                        <?php
                                        $counter = 1;

                                        while ($row = mysqli_fetch_assoc($result)):
                                            // Calculate age
                                            $dob = new DateTime($row['dob']);
                                            $today = new DateTime();
                                            $age = $today->diff($dob)->y;

                                            // Middle initial & full name
                                            $middleInitial = !empty($row['m_name']) ? strtoupper(substr($row['m_name'], 0, 1)) . "." : "";
                                            $fullName = "{$row['s_name']}, {$row['f_name']} {$middleInitial}";

                                            // Collect all livelihoods into a single array
                                            $livelihoods = [];

                                            foreach (['for_farmer', 'for_farmerworker', 'for_fisherfolk', 'for_agri'] as $field) {
                                                if (!empty($row[$field])) {
                                                    $items = array_map('trim', explode(",", $row[$field]));
                                                    $livelihoods = array_merge($livelihoods, $items);
                                                }
                                            }

                                            $livelihoods = !empty($row['livelihoodsList']) ? $row['livelihoodsList'] : 'N/A';
                                            ?>
                                            <tr class="border-b" data-id="<?= $row['id'] ?>">
                                                <td class="px-4 py-3 rowNumber"><?= (($page - 1) * $limit) + $counter ?>
                                                </td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($fullName) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['brgy']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['mobile']) ?></td>
                                                <td class="px-4 py-3"><?= $age ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['dob']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['gender']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['total_farmarea']) ?></td>

                                                <!-- Single column for all livelihoods -->
                                                <td class="px-4 py-3"><?= htmlspecialchars($livelihoods) ?></td>

                                                <td
                                                    class="px-4 py-3 font-bold statusCell text-<?= $row['status'] == 'Pending' ? 'yellow-500' : ($row['status'] == 'Process' ? 'blue-500' : 'green-500') ?>">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </td>

                                                <!-- Actions dropdown -->
                                                <td class="px-4 py-3 relative">
                                                    <button type="button"
                                                        class="dropdown-toggle relative inline-flex items-center p-0.5 text-sm text-gray-500 hover:text-gray-800 rounded-lg">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </button>
                                                    <div
                                                        class="dropdown-menu hidden absolute right-0 z-50 w-48 max-h-60 overflow-y-auto bg-white rounded shadow-lg divide-y divide-gray-100">
                                                        <ul class="py-1 text-sm text-gray-700">
                                                            <li>
                                                                <a href="#" data-modal-target="view-farmer-modal"
                                                                    data-modal-toggle="view-farmer-modal"
                                                                    data-registration-id="<?= htmlspecialchars($row['id']) ?>"
                                                                    onclick="fetchFarmerData('<?= htmlspecialchars($row['id']) ?>'); setFarmerIdToEditButton('<?= htmlspecialchars($row['id']) ?>'); document.getElementById('farmer_id').value='<?= htmlspecialchars($row['id']) ?>'; return false;"
                                                                    class="block py-2 px-4 hover:bg-gray-100">
                                                                    View
                                                                </a>
                                                            </li>
                                                            <li><a href="#"
                                                                    class="archiveAction block py-2 px-4 hover:bg-gray-100"
                                                                    data-id="<?= $row['id'] ?>">Archive</a></li>
                                                            <li><a href="#"
                                                                    class="block py-2 px-4 hover:bg-gray-100 border-t border-gray-200">Generate
                                                                    ID</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter++; endwhile; ?>
                                    </tbody>
                                </table>
                                <?php if ($totalPages > 1): ?>
                                    <nav class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-4"
                                        aria-label="Table navigation">
                                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                            Showing
                                            <span class="font-semibold text-gray-900 dark:text-white">
                                                <?php echo $page; ?>
                                            </span>
                                            of
                                            <span class="font-semibold text-gray-900 dark:text-white">
                                                <?php echo $totalPages; ?>
                                            </span>
                                        </span>

                                        <ul class="inline-flex items-stretch -space-x-px">
                                            <!-- Prev Button -->
                                            <li>
                                                <?php if ($page > 1): ?>
                                                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>"
                                                        class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-500 bg-white rounded-l-lg border border-gray-300 
                               hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 
                               dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                                        <span class="sr-only">Previous</span>
                                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor"
                                                            viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 
                                01-1.414 1.414l-4-4a1 1 0 
                                010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span
                                                        class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-400 bg-gray-100 rounded-l-lg border border-gray-300 dark:bg-gray-700 dark:text-gray-500">
                                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 
                                01-1.414 1.414l-4-4a1 1 0 
                                010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                <?php endif; ?>
                                            </li>

                                            <!-- Page Numbers -->
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li>
                                                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"
                                                        class="flex items-center justify-center text-sm py-2 px-3 leading-tight 
                                        border border-gray-300 
                                        <?php echo ($i == $page)
                                            ? 'bg-blue-500 text-white dark:border-blue-600'
                                            : 'text-gray-500 bg-white hover:bg-gray-100 hover:text-gray-700 
                                                dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 
                                                dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Next Button -->
                                            <li>
                                                <?php if ($page < $totalPages): ?>
                                                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>"
                                                        class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-500 
                               bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 
                               dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 
                               dark:hover:bg-gray-700 dark:hover:text-white">
                                                        <span class="sr-only">Next</span>
                                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor"
                                                            viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 
                                7.293 6.707a1 1 0 
                                011.414-1.414l4 4a1 1 0 
                                010 1.414l-4 4a1 1 0-1.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-400 
                                bg-gray-100 rounded-r-lg border border-gray-300 dark:bg-gray-700 dark:text-gray-500">
                                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 
                                7.293 6.707a1 1 0 
                                011.414-1.414l4 4a1 1 0 
                                010 1.414l-4 4a1 1 0-1.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- NO RESULTS -->
                                <div class="p-6 bg-white shadow rounded-lg text-center">
                                    <p class="text-red-500 font-semibold text-lg">
                                        <?php if (!empty($search)): ?>
                                            No records found for "<span class="italic"><?= htmlspecialchars($search) ?></span>"
                                        <?php else: ?>
                                            No records available.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
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

        // Handle profile menu auto-opening when returning from profile pages
        document.addEventListener('DOMContentLoaded', function () {
            // Check if profileMenu parameter is in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('profileMenu') === 'open') {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    // Use the same animation as toggleUserDropdown
                    dropdown.classList.remove("hidden");
                    setTimeout(() => {
                        dropdown.classList.remove("opacity-0", "scale-95");
                        dropdown.classList.add("opacity-100", "scale-100");
                    }, 10);
                }

                // Remove the parameter from URL without reloading
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
            }

            // Also check sessionStorage as backup
            if (sessionStorage.getItem('keepProfileOpen') === 'true') {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    dropdown.classList.remove("hidden");
                    setTimeout(() => {
                        dropdown.classList.remove("opacity-0", "scale-95");
                        dropdown.classList.add("opacity-100", "scale-100");
                    }, 10);
                }
                sessionStorage.removeItem('keepProfileOpen');
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {

            // ✅ Utility: get selected values from checkboxes
            const getSelectedValues = (selector) => {
                return Array.from(document.querySelectorAll(selector + ':checked')).map(cb => cb.value);
            };

            // ✅ Update filter labels dynamically
            const updateLabels = () => {
                const selectedYears = getSelectedValues('.yearCheckbox');
                const selectedMonths = getSelectedValues('.monthCheckbox');
                const selectedBrgy = getSelectedValues('.filterCheckbox');

                document.getElementById("yearLabel").textContent = selectedYears.length
                    ? (selectedYears.length > 3 ? `${selectedYears.length} selected` : selectedYears.join(", "))
                    : "Year";
                document.getElementById("monthLabel").textContent = selectedMonths.length
                    ? (selectedMonths.length > 3 ? `${selectedMonths.length} selected` : selectedMonths.join(", "))
                    : "Month";
                document.getElementById("barangayLabel").textContent = selectedBrgy.length
                    ? (selectedBrgy.length > 3 ? `${selectedBrgy.length} selected` : selectedBrgy.join(", "))
                    : "Barangay";
            };

            // ✅ Fetch filtered table via AJAX
            const fetchTable = () => {
                const selectedYears = getSelectedValues('.yearCheckbox');
                const selectedMonths = getSelectedValues('.monthCheckbox');
                const selectedBrgy = getSelectedValues('.filterCheckbox');

                if (!selectedYears.length && !selectedMonths.length && !selectedBrgy.length) {
                    document.getElementById("reportTableContainer").innerHTML =
                        "<p class='text-gray-500 text-center py-3'>Please select a filter to view report.</p>";
                    return;
                }

                const params = new URLSearchParams();
                if (selectedYears.length) params.append('year', selectedYears.join(','));
                if (selectedMonths.length) params.append('month', selectedMonths.join(','));
                if (selectedBrgy.length) params.append('brgy', selectedBrgy.join(','));

                fetch(`fetch_report.php?${params.toString()}`)
                    .then(res => res.text())
                    .then(html => {
                        document.getElementById("reportTableContainer").innerHTML = html;
                    })
                    .catch(err => {
                        console.error("Fetch error:", err);
                        document.getElementById("reportTableContainer").innerHTML =
                            "<p class='text-red-600 text-center py-3'>Error loading data.</p>";
                    });
            };

            // ✅ Attach change event to all regular filters
            document.querySelectorAll('.yearCheckbox, .monthCheckbox, .filterCheckbox')
                .forEach(cb => cb.addEventListener('change', () => {
                    updateLabels();
                    fetchTable();
                }));

            // ✅ Add Select-All Logic for Each Dropdown
            const setupSelectAll = (selectAllId, checkboxClass) => {
                const selectAllBox = document.getElementById(selectAllId);
                const checkboxes = document.querySelectorAll(`.${checkboxClass}`);

                // When "Select All" is toggled
                selectAllBox?.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateLabels();
                    fetchTable();
                });

                // If all individual boxes are checked manually → check Select All
                checkboxes.forEach(cb => cb.addEventListener('change', () => {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    selectAllBox.checked = allChecked;
                }));
            };

            // Apply Select All to each group
            setupSelectAll('selectAllYears', 'yearCheckbox');
            setupSelectAll('selectAllMonths', 'monthCheckbox');
            setupSelectAll('selectAllBarangays', 'filterCheckbox');

            // ✅ Dropdown toggle
            document.querySelectorAll('[data-dropdown-toggle]').forEach(button => {
                button.addEventListener('click', () => {
                    const dropdownId = button.getAttribute('data-dropdown-toggle');
                    const dropdown = document.getElementById(dropdownId);
                    dropdown.classList.toggle('hidden');
                });
            });

            // ✅ Click outside to close dropdowns
            document.addEventListener('click', (event) => {
                document.querySelectorAll('[id$="Dropdown"]').forEach(dropdown => {
                    const button = document.querySelector(`[data-dropdown-toggle="${dropdown.id}"]`);
                    if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
            });

        });
    </script>

    <script>
        function generateReport() {
            // ✅ Get selected report type (only one radio)
            const selectedReport = document.querySelector('.reportTypeCheckbox:checked');
            if (!selectedReport) {
                alert("⚠️ Please select a report type before generating the report.");
                return;
            }

            const reportType = selectedReport.value;

            // ✅ Get all checked checkbox values (multi-select supported)
            const selectedYears = Array.from(document.querySelectorAll('.yearCheckbox:checked')).map(cb => cb.value);
            const selectedMonths = Array.from(document.querySelectorAll('.monthCheckbox:checked')).map(cb => cb.value);
            const selectedBrgys = Array.from(document.querySelectorAll('.filterCheckbox:checked')).map(cb => cb.value);

            // ✅ Validation for required filters
            if (!selectedYears.length || !selectedMonths.length || !selectedBrgys.length) {
                alert("⚠️ Please select at least one Year, Month, and Barangay before generating the report.");
                return;
            }

            // ✅ Build query string parameters
            const params = new URLSearchParams({
                report: reportType,
                year: selectedYears.join(','),
                month: selectedMonths.join(','),
                brgy: selectedBrgys.join(',')
            });

            // ✅ Decide which PHP file to open based on the selected report type
            let reportPage = '';

            switch (reportType) {
                case 'farmerList':
                    reportPage = 'printreport.php';
                    break;

                case 'livelihood':
                    reportPage = 'printreport_livelihood.php';
                    break;

                case 'submission':
                    reportPage = 'printreport_brgy.php';
                    break;

                case 'summary':
                    reportPage = 'summary_report.php';
                    break;

                // ✅ NEW AGE REPORT CASE
                case 'ageReport':
                    reportPage = 'age_report.php';
                    break;

                // ✅ NEW AGE REPORT CASE
                case 'genderReport':
                    reportPage = 'printreport_gender.php';
                    break;

                default:
                    alert("⚠️ Unknown report type selected.");
                    return;
            }

            // ✅ Open the corresponding print page in a new tab
            const url = `${reportPage}?${params.toString()}`;
            window.open(url, '_blank');
        }
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
</body>

</html>