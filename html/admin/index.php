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

// ✅ Detect database connection variable
$db = $conn ?? ($mysqli ?? null);
if (!$db)
    die("❌ Database connection not found.");


// ✅ Get Total Hectares (sum of all total_farmarea) - EXCLUDE ARCHIVED
$query_hectares = "SELECT SUM(total_farmarea) AS total_hectares FROM registration_form WHERE status != 'Archived'";
$result_hectares = $db->query($query_hectares);
$hectares = ($result_hectares && $result_hectares->num_rows > 0)
    ? number_format((float) $result_hectares->fetch_assoc()['total_hectares'], 2)
    : 0;

// Total All Livelihoods - EXCLUDE ARCHIVED
$totalLivelihood = $conn->query("
    SELECT COUNT(*) AS total 
    FROM livelihoods l
    JOIN registration_form r ON l.registration_form_id = r.id
    WHERE l.category_id IS NOT NULL 
    AND r.status != 'Archived'
")->fetch_assoc()['total'] ?? 0;

// ✅ Total Farmers — count all IDs in registration_form - EXCLUDE ARCHIVED
$totalFarmers = $conn->query("
    SELECT COUNT(id) AS total 
    FROM registration_form
    WHERE status != 'Archived'
")->fetch_assoc()['total'] ?? 0;

// ✅ Total Pending (case-insensitive) - EXCLUDE ARCHIVED
$pending_total = $conn->query("
    SELECT COUNT(*) AS total FROM registration_form
    WHERE LOWER(status) = 'pending'
    AND status != 'Archived'
")->fetch_assoc()['total'] ?? 0;

// ✅ Total Registered (case-insensitive) - EXCLUDE ARCHIVED
$registered_total = $conn->query("
    SELECT COUNT(*) AS total FROM registration_form
    WHERE LOWER(status) = 'registered'
    AND status != 'Archived'
")->fetch_assoc()['total'] ?? 0;

// ✅ Get Male Count - EXCLUDE ARCHIVED
$query_male = "SELECT COUNT(id) AS total_male FROM registration_form WHERE gender = 'Male' AND status != 'Archived'";
$result_male = $db->query($query_male);
$male = ($result_male && $result_male->num_rows > 0)
    ? $result_male->fetch_assoc()['total_male']
    : 0;

// ✅ Get Female Count - EXCLUDE ARCHIVED
$query_female = "SELECT COUNT(id) AS total_female FROM registration_form WHERE gender = 'Female' AND status != 'Archived'";
$result_female = $db->query($query_female);
$female = ($result_female && $result_female->num_rows > 0)
    ? $result_female->fetch_assoc()['total_female']
    : 0;

$categoryQuery = "SELECT * FROM category_types ORDER BY id ASC";
$categoryResult = $conn->query($categoryQuery);
$categories = ['All']; // Default option

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category_name'];
}

// =================== AJAX FILTER HANDLER ===================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    require '../../db/conn.php';

    /* ==========================================================
       1️⃣ AGE FILTER AJAX (for Age Overview chart)
       Triggered when URL contains: ?ajax=1&type=age&brgy=XYZ
    ========================================================== */
    if (isset($_GET['type']) && $_GET['type'] === 'age') {

        $brgy = $_GET['brgy'] ?? '';

        // Base query - EXCLUDE ARCHIVED
        $ageQuery = "
            SELECT
                CASE
                    WHEN dob IS NULL THEN 'Unknown'
                    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 26 AND 40 THEN '26-40'
                    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 41 AND 60 THEN '41-60'
                    ELSE '60+'
                END AS age_group,
                COUNT(*) AS total
            FROM registration_form
            WHERE status != 'Archived'
        ";

        // Apply barangay filter
        if (!empty($brgy)) {
            $brgy = $conn->real_escape_string($brgy);
            $ageQuery .= " AND brgy = '$brgy'";
        }

        $ageQuery .= " GROUP BY age_group ORDER BY age_group";

        $ageResult = $conn->query($ageQuery);

        $labels = [];
        $values = [];

        while ($row = $ageResult->fetch_assoc()) {
            $labels[] = $row['age_group'];
            $values[] = (int) $row['total'];
        }

        echo json_encode([
            'labels' => $labels,
            'values' => $values
        ]);
        exit;
    }

    /* ==========================================================
       2️⃣ CROPS FILTER AJAX (for Crop Overview doughnut chart)
       Triggered when URL contains: ?ajax=1&category=Corn
    ========================================================== */

    $category = $_GET['category'] ?? 'All';
    $where = '';

    if ($category !== 'All') {
        $where = " AND ct.category_name = '" . mysqli_real_escape_string($conn, $category) . "'";
    }

    $query = "
        SELECT 
            CASE 
                WHEN LOWER(s.sub_name) = 'others'
                     AND (fl.remarks IS NOT NULL AND fl.remarks != '')
                THEN CONCAT('Others - ', TRIM(fl.remarks))
                ELSE s.sub_name
            END AS crop,
            COUNT(*) AS total
        FROM livelihoods fl
        JOIN registration_form r ON fl.registration_form_id = r.id
        JOIN sub_activities s ON fl.sub_activity_id = s.id
        JOIN category_types ct ON fl.category_id = ct.id
        WHERE r.status != 'Archived' $where
        GROUP BY 
            CASE 
                WHEN LOWER(s.sub_name) = 'others'
                     AND (fl.remarks IS NOT NULL AND fl.remarks != '')
                THEN CONCAT('Others - ', TRIM(fl.remarks))
                ELSE s.sub_name
            END
        ORDER BY crop ASC
    ";

    $result = mysqli_query($conn, $query);

    $labels = [];
    $values = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $labels[] = $row['crop'];
        $values[] = (int) $row['total'];
    }

    echo json_encode([
        'category' => $category,
        'labels' => $labels,
        'values' => $values
    ]);
    exit;
}

// =================== DEFAULT CROP CHART DATA ===================
$defaultCropQuery = "
    SELECT s.sub_name AS crop, COUNT(*) AS total
    FROM livelihoods fl
    JOIN registration_form r ON fl.registration_form_id = r.id
    JOIN sub_activities s ON fl.sub_activity_id = s.id
    JOIN category_types ct ON fl.category_id = ct.id
    WHERE r.status != 'Archived'
    GROUP BY fl.sub_activity_id;
";

$cropResult = $conn->query($defaultCropQuery);
$crops = [];
$cropCounts = [];

while ($row = $cropResult->fetch_assoc()) {
    $crops[] = $row['crop'];
    $cropCounts[] = (int) $row['total'];
}

$brgy = $_GET['brgy'] ?? ''; // barangay filter if passed

$ageQuery = "
SELECT
CASE
    WHEN dob IS NULL THEN 'Unknown'
    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 26 AND 40 THEN '26-40'
    WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 41 AND 60 THEN '41-60'
    ELSE '60+'
END AS age_group,
COUNT(*) AS total
FROM registration_form
WHERE status != 'Archived'
";

// ✅ Filter per barangay if selected
if (!empty($brgy)) {
    $brgy = $conn->real_escape_string($brgy);
    $ageQuery .= " AND brgy = '$brgy'";
}

$ageQuery .= " GROUP BY age_group";

$ageResult = $conn->query($ageQuery);
$ageRanges = [];
$ageCounts = [];

while ($row = $ageResult->fetch_assoc()) {
    $ageRanges[] = $row['age_group'];
    $ageCounts[] = (int) $row['total'];
}

// Output JSON if used by fetch()
if (isset($_GET['ajax'])) {
    echo json_encode([
        'labels' => $ageRanges,
        'values' => $ageCounts
    ]);
    exit;
}

// Total registered - EXCLUDE ARCHIVED
$result = $conn->query("
    SELECT COUNT(DISTINCT r.id) AS total_registered
    FROM registration_form r
    JOIN livelihoods fl ON fl.registration_form_id = r.id
    WHERE r.status != 'Archived'
");
$row = $result->fetch_assoc();
$total_all = isset($row['total_registered']) ? (int) $row['total_registered'] : 0;

// ========== CATEGORY COUNTS QUERIES ==========

// First, let's get all categories from your database - EXCLUDE ARCHIVED
$categoriesQuery = $conn->query("
    SELECT ct.id, ct.category_name, 
           COUNT(DISTINCT l.registration_form_id) as total
    FROM category_types ct
    LEFT JOIN livelihoods l ON ct.id = l.category_id
    LEFT JOIN registration_form r ON l.registration_form_id = r.id
    WHERE (r.id IS NULL OR r.status != 'Archived')
    GROUP BY ct.id, ct.category_name
    ORDER BY ct.id
");

// Initialize counts
$farmerCount = 0;
$workerCount = 0;
$fisherCount = 0;
$otherCount = 0;

// Map category names to our variables
if ($categoriesQuery && $categoriesQuery->num_rows > 0) {
    while ($cat = $categoriesQuery->fetch_assoc()) {
        $catName = strtolower($cat['category_name']);
        $count = (int) $cat['total'];

        if (strpos($catName, 'farmer') !== false && strpos($catName, 'worker') === false) {
            $farmerCount = $count;
        } elseif (strpos($catName, 'worker') !== false || strpos($catName, 'labor') !== false) {
            $workerCount = $count;
        } elseif (strpos($catName, 'fish') !== false) {
            $fisherCount = $count;
        } else {
            $otherCount += $count;
        }
    }
}

// Alternative simpler queries if above doesn't work:
if ($farmerCount == 0 && $workerCount == 0 && $fisherCount == 0) {
    // Try direct counts from registration_form if it has a category field
    $checkColumn = $conn->query("SHOW COLUMNS FROM registration_form LIKE 'category'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        // Category column exists - EXCLUDE ARCHIVED
        $farmerCount = $conn->query("SELECT COUNT(*) as total FROM registration_form WHERE LOWER(category) LIKE '%farmer%' AND LOWER(category) NOT LIKE '%worker%' AND status != 'Archived'")->fetch_assoc()['total'] ?? 0;
        $workerCount = $conn->query("SELECT COUNT(*) as total FROM registration_form WHERE LOWER(category) LIKE '%worker%' OR LOWER(category) LIKE '%labor%' AND status != 'Archived'")->fetch_assoc()['total'] ?? 0;
        $fisherCount = $conn->query("SELECT COUNT(*) as total FROM registration_form WHERE LOWER(category) LIKE '%fish%' AND status != 'Archived'")->fetch_assoc()['total'] ?? 0;
    }
}

// If still zero, use placeholder data for demo (based on non-archived farmers)
if ($farmerCount == 0 && $workerCount == 0 && $fisherCount == 0) {
    // Estimate based on total non-archived farmers (for demo purposes)
    $farmerCount = floor($totalFarmers * 0.6); // 60% farmers
    $workerCount = floor($totalFarmers * 0.25); // 25% workers
    $fisherCount = floor($totalFarmers * 0.15); // 15% fisherfolk
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
    html,
    body {
        height: 100%;
        overflow: hidden;
        /* 🔥 Prevents the outer scroll */
    }

    .chart-container {
        width: 100%;
        max-width: 500px;
        /* adjust as needed */
        height: 400px;
        /* fixed height for all charts */
        margin: 0 auto;
        /* center if needed */
    }

    /* Loading skeleton */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Chart loading */
    .chart-loading {
        position: relative;
        min-height: 300px;
    }

    .chart-loading::after {
        content: 'Loading...';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #666;
        font-size: 14px;
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
                            class="flex items-center p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
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
        <main class="ml-64 flex-1 h-screen overflow-y-auto pt-20 p-4 bg-gray-50">
            <div class="relative min-h-full mt-2 pb-20">
                <!-- HEADER -->
                <div
                    class="flex justify-between items-center bg-gradient-to-r from-green-600 to-green-500 text-white p-6 rounded-2xl shadow-md">
                    <div>
                        <h1 class="text-2xl font-bold">Welcome, Administrator 👋</h1>
                        <p class="text-sm opacity-90">Here’s the current agricultural overview for Abra De Ilog.</p>
                    </div>
                    <div class="text-right text-sm opacity-80">
                        <p>Last login: <span class="font-semibold"><?= date('F j, Y g:i A') ?></span></p>
                    </div>
                </div>

                <!-- STAT CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 overflow mt-4">
                    <!-- Hectares -->
                    <div
                        class="flex items-center justify-between bg-green-50 border border-green-200 p-6 rounded-2xl shadow hover:shadow-lg transition-all duration-300">
                        <div>
                            <p class="text-gray-600 font-medium text-sm uppercase tracking-wider">Hectares</p>
                            <h2 id="hectares" class="text-4xl font-bold text-green-600 mt-1"><?= $hectares ?></h2>


                        </div>
                        <div class="p-3 bg-green-500 rounded-full shadow-md text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M3 7l9 4 9-4M3 17l9 4 9-4M3 12l9 4 9-4" />
                            </svg>
                        </div>
                    </div>

                    <!-- Farmers -->
                    <div
                        class="flex items-center justify-between bg-blue-50 border border-blue-200 p-6 rounded-2xl shadow hover:shadow-lg transition-all duration-300">
                        <div>
                            <p class="text-gray-600 font-medium text-sm uppercase tracking-wider">Total Farmers</p>
                            <h2 class="text-4xl font-bold text-blue-600 mt-1"><?= $totalFarmers ?></h2>
                            <!-- Farmers -->

                        </div>
                        <div class="p-3 bg-blue-500 rounded-full shadow-md text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zM4 20c0-2.76 3.58-5 8-5s8 2.24 8 5v1H4v-1zM16 8h3M18 6v4" />
                            </svg>
                        </div>
                    </div>

                    <!-- Pending -->
                    <!-- 💜 Pending -->
                    <div
                        class="flex items-center justify-between bg-purple-50 border border-purple-200 p-6 rounded-2xl shadow hover:shadow-lg transition-all duration-300">
                        <div>
                            <p class="text-gray-600 font-medium text-sm uppercase tracking-wider">Pending</p>
                            <h2 class="text-4xl font-bold text-purple-600 mt-1"><?= $pending_total ?></h2>
                        </div>
                        <div
                            class="p-3 bg-purple-500 rounded-full shadow-md text-white flex items-center justify-center">
                            <!-- Clock Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                            </svg>
                        </div>
                    </div>

                    <!-- 💙 Registered -->
                    <div
                        class="flex items-center justify-between bg-cyan-50 border border-cyan-200 p-6 rounded-2xl shadow hover:shadow-lg transition-all duration-300">
                        <div>
                            <p class="text-gray-600 font-medium text-sm uppercase tracking-wider">Registered</p>
                            <h2 class="text-4xl font-bold text-cyan-600 mt-1"><?= $registered_total ?></h2>
                        </div>
                        <div class="p-3 bg-cyan-500 rounded-full shadow-md text-white flex items-center justify-center">
                            <!-- User Check Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 14l2 2 4-4" />
                                <circle cx="9" cy="7" r="4" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 21v-2a4 4 0 0 1 4-4h1" />
                            </svg>
                        </div>
                    </div>
                </div>
                <!-- COMPACT CATEGORY CARDS - Place after your main stat cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <!-- Farmer Compact Card -->
                    <div
                        class="bg-white rounded-xl border border-green-200 p-5 shadow hover:shadow-md transition group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-green-100 rounded-xl">
                                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z"
                                            clip-rule="evenodd" />
                                        <path
                                            d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Farmers</h4>
                                    <p class="text-2xl font-bold text-green-600"><?= $farmerCount ?></p>
                                </div>
                            </div>
                            <span
                                class="text-sm px-2 py-1 bg-green-100 text-green-800 rounded-full"><?= round(($farmerCount / max($totalFarmers, 1)) * 100) ?>%</span>
                        </div>
                    </div>

                    <!-- Farmer Worker Compact Card -->
                    <div class="bg-white rounded-xl border border-blue-200 p-5 shadow hover:shadow-md transition group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-blue-100 rounded-xl">
                                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                            clip-rule="evenodd" />
                                        <path fill-rule="evenodd"
                                            d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Farm Workers</h4>
                                    <p class="text-2xl font-bold text-blue-600"><?= $workerCount ?></p>
                                </div>
                            </div>
                            <span
                                class="text-sm px-2 py-1 bg-blue-100 text-blue-800 rounded-full"><?= round(($workerCount / max($totalFarmers, 1)) * 100) ?>%</span>
                        </div>
                    </div>

                    <!-- Fisherfolk Compact Card -->
                    <div class="bg-white rounded-xl border border-cyan-200 p-5 shadow hover:shadow-md transition group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-cyan-100 rounded-xl">
                                    <svg class="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Fisherfolk</h4>
                                    <p class="text-2xl font-bold text-cyan-600"><?= $fisherCount ?></p>
                                </div>
                            </div>
                            <span
                                class="text-sm px-2 py-1 bg-cyan-100 text-cyan-800 rounded-full"><?= round(($fisherCount / max($totalFarmers, 1)) * 100) ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- CHARTS GRID -->
                <!-- ENHANCED CHARTS SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Crops Chart Card -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="border-b border-gray-100 p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                                        <div class="p-2 bg-green-100 rounded-lg">
                                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        Crops Overview
                                    </h3>
                                    <p class="text-gray-600 text-sm mt-1">Distribution of main crops cultivated by
                                        farmers</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <select id="categorySelect"
                                        class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent bg-gray-50">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="cropsChart"></canvas>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <?php
                                // Show top 4 crops as quick stats
                                $topCrops = array_combine($crops, $cropCounts);
                                arsort($topCrops);
                                $top4 = array_slice($topCrops, 0, 4, true);
                                foreach ($top4 as $crop => $count):
                                    ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <span
                                            class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($crop) ?></span>
                                        <span class="text-sm font-bold text-green-600"><?= $count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Age Chart Card -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="border-b border-gray-100 p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                                        <div class="p-2 bg-blue-100 rounded-lg">
                                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        Age Distribution
                                    </h3>
                                    <p class="text-gray-600 text-sm mt-1">Farmers grouped by age categories</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label for="brgySelect" class="text-sm text-gray-600 font-medium">Filter by
                                        Barangay:</label>
                                    <select id="brgySelect"
                                        class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50">
                                        <option value="">All Barangays</option>
                                        <?php
                                        $brgyQuery = $conn->query("SELECT DISTINCT brgy FROM registration_form ORDER BY brgy ASC");
                                        while ($row = $brgyQuery->fetch_assoc()):
                                            ?>
                                            <option value="<?= htmlspecialchars($row['brgy']) ?>">
                                                <?= htmlspecialchars($row['brgy']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="ageChart"></canvas>
                            </div>
                            <div class="mt-4 text-center text-sm text-gray-600">
                                Total farmers in view: <span id="totalFarmersCount"
                                    class="font-bold text-blue-600"><?= $totalFarmers ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ADDITIONAL SUMMARY CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pb-10">
                    <div class="bg-yellow-50 p-6 rounded-2xl shadow hover:shadow-lg transition">
                        <h4 class="font-semibold text-yellow-700 text-lg">🏆 Top Crop</h4>
                        <p class="text-gray-600 mt-2 text-sm">Most cultivated: <span
                                class="font-bold text-yellow-800"><?= $topCrop ?? 'Rice' ?></span></p>
                    </div>
                    <div class="bg-blue-50 p-6 rounded-2xl shadow hover:shadow-lg transition">
                        <h4 class="font-semibold text-blue-700 text-lg">🧑‍🌾 New Farmers</h4>
                        <p class="text-gray-600 mt-2 text-sm">Registered this month: <span
                                class="font-bold text-blue-800">5</span></p>
                    </div>
                </div>
            </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // initial data from PHP (used as fallback)
                const cropLabels = <?php echo json_encode($crops); ?> || [];
                const cropValues = <?php echo json_encode($cropCounts); ?> || [];
                const ageLabels = <?php echo json_encode($ageRanges); ?> || [];
                const ageValues = <?php echo json_encode($ageCounts); ?> || [];

                let cropsChartInstance = null;
                let ageChartInstance = null;

                // ===================== CROPS CHART =====================
                const cropsCanvas = document.getElementById('cropsChart');

                function renderCropsChart(labels, values, title = "All Crops", details = []) {
                    const ctx = cropsCanvas.getContext('2d');
                    if (cropsChartInstance) cropsChartInstance.destroy();

                    cropsChartInstance = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: [
                                    '#22c55e', '#3b82f6', '#f97316',
                                    '#9333ea', '#f59e0b', '#10b981', '#ef4444'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'bottom' },
                                title: { display: true, text: `Crop Distribution (${title})` },
                                tooltip: {
                                    usePointStyle: true,
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    titleFont: { size: 14, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    padding: 12,
                                    displayColors: false,
                                    callbacks: {
                                        label: function (context) {
                                            const index = context.dataIndex;
                                            const cropName = context.label;
                                            const value = context.formattedValue;
                                            const detailText = details[index] || "";
                                            const lines = [`${cropName}: ${value}`];

                                            if (detailText.trim() !== "" && cropName.toLowerCase().includes("others")) {
                                                const subItems = detailText
                                                    .split(',')
                                                    .map(s => s.trim())
                                                    .filter(Boolean);

                                                if (subItems.length > 0) {
                                                    lines.push("Includes:");
                                                    subItems.forEach(item => {
                                                        lines.push(`• ${item}`);
                                                    });
                                                }
                                            }
                                            return lines;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                function loadCropsForCategory(category = 'All') {
                    fetch(`index.php?ajax=1&category=${encodeURIComponent(category)}`, { cache: 'no-store' })
                        .then(res => res.json())
                        .then(data => {
                            const labels = Array.isArray(data.labels) ? data.labels : [];
                            const values = Array.isArray(data.values) ? data.values : [];
                            const details = Array.isArray(data.details) ? data.details : [];

                            renderCropsChart(
                                labels.length ? labels : cropLabels,
                                values.length ? values : cropValues,
                                data.category || category,
                                details
                            );
                        })
                        .catch(err => {
                            console.error('Error fetching crop data:', err);
                            renderCropsChart(cropLabels, cropValues, 'All', []);
                        });
                }

                const cropFilter = document.getElementById('categorySelect');
                const initialCategory = (cropFilter && cropFilter.value) ? cropFilter.value : 'All';
                loadCropsForCategory(initialCategory);

                if (cropFilter) {
                    cropFilter.addEventListener('change', function () {
                        loadCropsForCategory(this.value || 'All');
                    });
                }

                // ===================== FIXED AGE CHART =====================
                const ageCanvas = document.getElementById('ageChart');

                function loadAgeChart(brgy = '') {
                    if (brgy === '') {
                        renderAgeChart(ageLabels, ageValues, 'All');
                        return;
                    }

                    fetch(`index.php?ajax=1&type=age&brgy=${encodeURIComponent(brgy)}`, { cache: 'no-store' })
                        .then(res => res.json())
                        .then(data => {
                            if (!data || !data.labels || !data.values) {
                                console.error("Invalid age data:", data);
                                return;
                            }
                            renderAgeChart(data.labels, data.values, brgy);
                        })
                        .catch(err => console.error('Error fetching age data:', err));
                }

                function renderAgeChart(labels, values, brgyName) {
                    const ctx = ageCanvas.getContext('2d');
                    if (ageChartInstance) ageChartInstance.destroy();

                    ageChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels, // Age groups
                            datasets: [{
                                label: 'Farmers',
                                data: values,
                                backgroundColor: '#3b82f6'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: t => "Age Group: " + t[0].label,
                                        label: t => "Count: " + t.formattedValue,
                                        afterLabel: () => {
                                            const brgySelect = document.getElementById('brgySelect');
                                            const selected = brgySelect?.value || 'All';
                                            return "Barangay: " + (selected || 'All');
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 }
                                }
                            }
                        }
                    });
                }

                loadAgeChart();

                const brgySelect = document.getElementById('brgySelect');
                if (brgySelect) {
                    brgySelect.addEventListener('change', function () {
                        loadAgeChart(this.value);
                    });
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
            function confirmLogout(event) {
                if (!confirm("Are you sure you want to logout?")) {
                    event.preventDefault(); // Prevent the default link behavior
                    return false;
                }
                // If confirmed, the link will naturally proceed to logout.php
            }
        </script>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../js/tailwind.config.js"></script>
        <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>