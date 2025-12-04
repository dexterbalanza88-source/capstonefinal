<?php
session_start();

// Enhanced security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in AND has staff role
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: stafflogin.php");
    exit;
}

// Auto logout after 15 minutes (900 seconds)
$timeout = 900;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    echo "<script>
        alert('⏰ Session expired due to inactivity. Please login again.'); 
        window.location.href='stafflogin.php';
    </script>";
    exit;
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

require_once __DIR__ . "../../admin/includes/session.php";

$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "farmer_info";

try {
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    mysqli_set_charset($conn, "utf8mb4");

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

// Get farmer ID from URL
$farmer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($farmer_id <= 0) {
    die("Invalid farmer ID");
}

// Fetch farmer details with comprehensive joins
$sql = "
    SELECT 
        rf.*,
        COALESCE(GROUP_CONCAT(DISTINCT sa.sub_name ORDER BY sa.sub_name SEPARATOR ', '), 'Not specified') AS livelihoods,
        COALESCE(SUM(l.total_area), 0) AS total_farm_area,
        COUNT(DISTINCT l.id) as total_livelihoods,
        GROUP_CONCAT(DISTINCT CONCAT(sa.sub_name, ' (', COALESCE(l.total_area, 0), ' ha)') SEPARATOR '; ') AS livelihood_details
    FROM registration_form rf
    LEFT JOIN livelihoods l ON l.registration_form_id = rf.id
    LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
    WHERE rf.id = ?
    GROUP BY rf.id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Farmer not found");
}

$farmer = $result->fetch_assoc();

// Calculate age from date of birth
$dob = new DateTime($farmer['dob']);
$today = new DateTime();
$age = $today->diff($dob)->y;

// Format dates
$created_date = new DateTime($farmer['created_at']);
$formatted_created = $created_date->format('F j, Y \a\t g:i A');

// Fetch detailed livelihood information
$livelihood_details_sql = "
    SELECT 
        sa.sub_name as livelihood,
        l.total_area,
        l.farm_location,
        l.created_at as livelihood_date
    FROM livelihoods l
    LEFT JOIN sub_activities sa ON sa.id = l.sub_activity_id
    WHERE l.registration_form_id = ?
    ORDER BY sa.sub_name
";

$livelihood_stmt = $conn->prepare($livelihood_details_sql);
$livelihood_stmt->bind_param("i", $farmer_id);
$livelihood_stmt->execute();
$livelihood_result = $livelihood_stmt->get_result();
$livelihoods = [];

while ($row = $livelihood_result->fetch_assoc()) {
    $livelihoods[] = $row;
}

// Get counts for sidebar
$pendingCountQuery = "SELECT COUNT(*) as total FROM registration_form WHERE status = 'pending'";
$registeredCountQuery = "SELECT COUNT(*) as total FROM registration_form WHERE status = 'registered'";
$totalCountQuery = "SELECT COUNT(*) as total FROM registration_form";

$pendingCount = $conn->query($pendingCountQuery)->fetch_assoc()['total'];
$registeredCount = $conn->query($registeredCountQuery)->fetch_assoc()['total'];
$totalCount = $conn->query($totalCountQuery)->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($farmer['f_name'] . ' ' . $farmer['s_name']) ?> - Farmer Details</title>

    <!-- CSS Resources -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/output.css" rel="stylesheet">

    <style>
        .info-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .section-title {
            color: #166534;
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid #dcfce7;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #1f2937;
            font-weight: 500;
            font-size: 1rem;
            line-height: 1.5;
        }

        .info-value-highlight {
            color: #166534;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            gap: 0.5rem;
        }

        .status-pending {
            background-color: #fffbeb;
            color: #d97706;
            border: 2px solid #fcd34d;
        }

        .status-registered {
            background-color: #f0fdf4;
            color: #166534;
            border: 2px solid #86efac;
        }

        .back-btn {
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-3px);
        }

        .action-btn {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            font-weight: 600;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .avatar-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .avatar-yellow {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .info-grid {
            display: grid;
            gap: 1.5rem;
        }

        .livelihood-item {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
        }

        .livelihood-item:hover {
            background: #f0fdf4;
            transform: translateX(4px);
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-in;
        }

        .tab-content.active {
            display: block;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tab-button.active {
            color: #166534;
            border-bottom-color: #166534;
            background: #f0fdf4;
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

        .print-only {
            display: none;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            .info-card {
                box-shadow: none;
                border: 2px solid #e5e7eb;
                page-break-inside: avoid;
            }

            .section-title {
                color: #1f2937;
                border-bottom: 2px solid #1f2937;
            }
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-stat {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .quick-stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #166534;
            line-height: 1;
        }

        .quick-stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <nav class="bg-[#166534] text-white shadow-lg fixed w-full z-50 top-0 left-0 border-b-4 border-[#E6B800] no-print">
        <div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-3">
            <!-- Left: Logo & Drawer Toggle -->
            <div class="flex items-center space-x-3">
                <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                    aria-controls="drawer-navigation"
                    class="p-2 text-white rounded-lg cursor-pointer md:hidden hover:bg-[#14532d] focus:ring-2 focus:ring-[#E6B800]">
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
                        <div class="text-xs text-gray-500 mt-1">
                            Role: <?= htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?>
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
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0 bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700 no-print"
        aria-label="Sidenav">
        <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
            <ul class="space-y-2">
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
                        <svg class="w-4 h-4 ml-auto transition-transform duration-300 rotate-180" id="dropdownArrow"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <ul id="farmersDropdownMenu" class="py-2 space-y-1 ml-8">
                        <li>
                            <a href="datalist.php"
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
                            <a href="datalist.php?table=registered"
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
        <div class="info-card p-8 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center space-x-6 mb-6 lg:mb-0">
                    <div
                        class="farmer-avatar <?= $farmer['status'] === 'pending' ? 'avatar-yellow' : 'avatar-green' ?>">
                        <?= strtoupper(substr($farmer['f_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">
                            <?= htmlspecialchars($farmer['f_name'] . ' ' . $farmer['s_name']) ?>
                        </h1>
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="text-gray-600 font-medium">Farmer ID: <?= $farmer['id'] ?></span>
                            <span
                                class="status-badge <?= $farmer['status'] === 'pending' ? 'status-pending' : 'status-registered' ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <?= $farmer['status'] === 'pending' ?
                                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />' :
                                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />'
                                        ?>
                                </svg>
                                <?= ucfirst($farmer['status']) ?>
                            </span>
                            <?php if (!empty($farmer['reference_no'])): ?>
                                <span class="text-sm text-gray-500">
                                    Ref: <?= htmlspecialchars($farmer['reference_no']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col space-y-2">
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Date Registered</div>
                        <div class="text-gray-900 font-semibold"><?= $formatted_created ?></div>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="history.back()"
                            class="action-btn px-6 py-3 bg-gray-600 text-white hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to List
                        </button>
                        <button onclick="window.print()"
                            class="action-btn px-6 py-3 bg-green-600 text-white hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex space-x-1 bg-white rounded-lg p-2 mb-6 shadow-sm no-print">
            <button class="tab-button active" onclick="showTab('personal')">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Personal Info
            </button>
            <button class="tab-button" onclick="showTab('farming')">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                </svg>
                Farming Details
            </button>
            <button class="tab-button" onclick="showTab('household')">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Household
            </button>
            <button class="tab-button" onclick="showTab('economic')">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                Economic Info
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Main Content Area -->
            <div class="xl:col-span-2 space-y-8">
                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <div class="info-grid">
                        <!-- Basic Information -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Basic Information
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value info-value-highlight">
                                        <?= htmlspecialchars($farmer['f_name'] . ' ' .
                                            (!empty($farmer['m_name']) ? $farmer['m_name'] . ' ' : '') .
                                            $farmer['s_name'] .
                                            (!empty($farmer['e_name']) ? ', ' . $farmer['e_name'] : '')) ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">Gender</div>
                                    <div class="info-value"><?= htmlspecialchars($farmer['gender']) ?></div>
                                </div>

                                <div>
                                    <div class="info-label">Date of Birth</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars(date('F j, Y', strtotime($farmer['dob']))) ?>
                                        <span class="text-gray-500 text-sm ml-2">(<?= $age ?> years old)</span>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">Contact Number</div>
                                    <div class="info-value info-value-highlight">
                                        <?= htmlspecialchars($farmer['mobile'] ?? 'Not provided') ?>
                                        <?php if (!empty($farmer['landline'])): ?>
                                            <div class="text-sm text-gray-600 mt-1">
                                                Landline: <?= htmlspecialchars($farmer['landline']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Address Information
                            </h2>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="info-label">House/Lot/Bldg No.</div>
                                        <div class="info-value">
                                            <?= htmlspecialchars($farmer['house'] ?? 'Not specified') ?></div>
                                    </div>

                                    <div>
                                        <div class="info-label">Street/Sitio/Subdivision</div>
                                        <div class="info-value">
                                            <?= htmlspecialchars($farmer['sitio'] ?? 'Not specified') ?></div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <div class="info-label">Barangay</div>
                                        <div class="info-value info-value-highlight">
                                            <?= htmlspecialchars($farmer['brgy']) ?></div>
                                    </div>

                                    <div>
                                        <div class="info-label">Municipality/City</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['municipal']) ?></div>
                                    </div>

                                    <div>
                                        <div class="info-label">Province</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['province']) ?></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">Region</div>
                                    <div class="info-value"><?= htmlspecialchars($farmer['region']) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Birth Information -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Birth Information
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="info-label">Country of Birth</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($farmer['birth_country'] ?? 'Not specified') ?></div>
                                </div>

                                <div>
                                    <div class="info-label">Province of Birth</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($farmer['birth_province'] ?? 'Not specified') ?></div>
                                </div>

                                <div>
                                    <div class="info-label">Municipality of Birth</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($farmer['birth_municipality'] ?? 'Not specified') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Farming Details Tab -->
                <div id="farming" class="tab-content">
                    <div class="info-grid">
                        <!-- Farm Summary -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                </svg>
                                Farm Summary
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-green-600 mb-2">
                                        <?= number_format($farmer['total_farm_area'], 2) ?>
                                    </div>
                                    <div class="info-label">Total Farm Area (Hectares)</div>
                                </div>

                                <div class="text-center">
                                    <div class="text-3xl font-bold text-blue-600 mb-2">
                                        <?= count($livelihoods) ?>
                                    </div>
                                    <div class="info-label">Number of Livelihoods</div>
                                </div>

                                <div class="text-center">
                                    <div class="text-3xl font-bold text-purple-600 mb-2">
                                        <?= $farmer['no_farmparcel'] ?? '0' ?>
                                    </div>
                                    <div class="info-label">Farm Parcels</div>
                                </div>
                            </div>
                        </div>

                        <!-- Livelihood Details -->
                        <?php if (!empty($livelihoods)): ?>
                            <div class="info-card p-6">
                                <h2 class="section-title">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    Livelihood Activities
                                </h2>
                                <div class="space-y-4">
                                    <?php foreach ($livelihoods as $index => $livelihood): ?>
                                        <div class="livelihood-item">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="font-semibold text-lg text-gray-900">
                                                        <?= ($index + 1) ?>. <?= htmlspecialchars($livelihood['livelihood']) ?>
                                                    </div>
                                                    <?php if (!empty($livelihood['total_area'])): ?>
                                                        <div class="text-green-600 font-medium mt-1">
                                                            Area: <?= number_format($livelihood['total_area'], 2) ?> hectares
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($livelihood['farm_location'])): ?>
                                                        <div class="text-gray-600 text-sm mt-1">
                                                            📍 Location: <?= htmlspecialchars($livelihood['farm_location']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($livelihood['livelihood_date'])): ?>
                                                    <div class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                        Added: <?= date('M j, Y', strtotime($livelihood['livelihood_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Additional Farm Information -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Additional Information
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if (!empty($farmer['no_farmparcel'])): ?>
                                    <div>
                                        <div class="info-label">Number of Farm Parcels</div>
                                        <div class="info-value"><?= $farmer['no_farmparcel'] ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($farmer['ownership'])): ?>
                                    <div>
                                        <div class="info-label">Ownership Number</div>
                                        <div class="info-value"><?= $farmer['ownership'] ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($farmer['agrarian'])): ?>
                                    <div>
                                        <div class="info-label">Agrarian Reform Beneficiary</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['agrarian']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Household Information Tab -->
                <div id="household" class="tab-content">
                    <div class="info-grid">
                        <!-- Household Composition -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Household Composition
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="info-label">Mother's Maiden Name</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($farmer['mother_maiden'] ?? 'Not specified') ?></div>
                                </div>

                                <div>
                                    <div class="info-label">Household Head</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($farmer['household_head'] ?? 'Not specified') ?>
                                        <?php if (isset($farmer['household_head']) && $farmer['household_head'] === 'No' && !empty($farmer['if_nohousehold'])): ?>
                                            <div class="text-sm text-gray-600 mt-1">
                                                Name: <?= htmlspecialchars($farmer['if_nohousehold']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($farmer['relationship'])): ?>
                                    <div>
                                        <div class="info-label">Relationship to Household Head</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['relationship']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="info-label">Living Household Members</div>
                                    <div class="info-value">
                                        <?php if (!empty($farmer['no_livinghousehold'])): ?>
                                            <span
                                                class="text-2xl font-bold text-green-600"><?= $farmer['no_livinghousehold'] ?></span>
                                            total members
                                            <?php if (!empty($farmer['no_male']) || !empty($farmer['no_female'])): ?>
                                                <div class="flex space-x-4 mt-2 text-sm">
                                                    <span class="text-blue-600">♂ <?= $farmer['no_male'] ?? 0 ?> male</span>
                                                    <span class="text-pink-600">♀ <?= $farmer['no_female'] ?? 0 ?> female</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Not specified
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Economic Information Tab -->
                <div id="economic" class="tab-content">
                    <div class="info-grid">
                        <!-- Socio-Economic Profile -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Socio-Economic Profile
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if (!empty($farmer['education'])): ?>
                                    <div>
                                        <div class="info-label">Highest Education</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['education']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="info-label">Person With Disability</div>
                                    <div class="info-value">
                                        <span
                                            class="<?= !empty($farmer['pwd']) && $farmer['pwd'] === 'Yes' ? 'text-red-600 font-semibold' : 'text-gray-600' ?>">
                                            <?= !empty($farmer['pwd']) ? htmlspecialchars($farmer['pwd']) : 'No' ?>
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">4P's Beneficiary</div>
                                    <div class="info-value">
                                        <?= !empty($farmer['for_ps']) ? htmlspecialchars($farmer['for_ps']) : 'No' ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">With Government ID</div>
                                    <div class="info-value">
                                        <?= !empty($farmer['with_gov']) ? htmlspecialchars($farmer['with_gov']) : 'No' ?>
                                    </div>
                                </div>

                                <?php if (!empty($farmer['specify_id'])): ?>
                                    <div>
                                        <div class="info-label">ID Type</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['specify_id']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="info-label">Member of Indigenous Group</div>
                                    <div class="info-value">
                                        <?= !empty($farmer['member_indig']) ? htmlspecialchars($farmer['member_indig']) : 'No' ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="info-label">Association Member</div>
                                    <div class="info-value">
                                        <?= !empty($farmer['assoc_member']) ? htmlspecialchars($farmer['assoc_member']) : 'No' ?>
                                    </div>
                                </div>

                                <?php if (!empty($farmer['specify_assoc'])): ?>
                                    <div>
                                        <div class="info-label">Association Name</div>
                                        <div class="info-value"><?= htmlspecialchars($farmer['specify_assoc']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Income Information -->
                        <div class="info-card p-6">
                            <h2 class="section-title">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                </svg>
                                Income Information (Annual)
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if (!empty($farmer['farming'])): ?>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-green-600 mb-2">
                                            ₱<?= number_format($farmer['farming'], 2) ?>
                                        </div>
                                        <div class="info-label">Farming Income</div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($farmer['non_farming'])): ?>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-blue-600 mb-2">
                                            ₱<?= number_format($farmer['non_farming'], 2) ?>
                                        </div>
                                        <div class="info-label">Non-Farming Income</div>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($farmer['farming']) && empty($farmer['non_farming'])): ?>
                                    <div class="col-span-2 text-center py-8">
                                        <div class="text-gray-400 text-lg">No income information provided</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Information -->
            <div class="space-y-8">
                <!-- System Information -->
                <div class="info-card p-6">
                    <h2 class="section-title">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        System Information
                    </h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Reference No:</span>
                            <span
                                class="font-semibold text-gray-900"><?= htmlspecialchars($farmer['reference_no'] ?? 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Registration Date:</span>
                            <span class="font-semibold text-gray-900"><?= $formatted_created ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-gray-600">Status:</span>
                            <span
                                class="status-badge <?= $farmer['status'] === 'pending' ? 'status-pending' : 'status-registered' ?>">
                                <?= ucfirst($farmer['status']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-600">Farmer ID:</span>
                            <span class="font-mono font-bold text-gray-900">#<?= $farmer['id'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="info-card p-6 no-print">
                    <h2 class="section-title">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Quick Actions
                    </h2>
                    <div class="space-y-3">
                        <button onclick="window.print()"
                            class="w-full action-btn px-4 py-3 bg-green-600 text-white hover:bg-green-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Farmer Details
                        </button>
                        <button onclick="history.back()"
                            class="w-full action-btn px-4 py-3 bg-gray-600 text-white hover:bg-gray-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to Farmer List
                        </button>
                        <button onclick="window.close()"
                            class="w-full action-btn px-4 py-3 bg-blue-600 text-white hover:bg-blue-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Close Window
                        </button>
                    </div>
                </div>

                <!-- Print Summary (Print Only) -->
                <div class="info-card p-6 print-only">
                    <h2 class="section-title">Farmer Summary</h2>
                    <div class="space-y-2 text-sm">
                        <div><strong>Name:</strong> <?= htmlspecialchars($farmer['f_name'] . ' ' . $farmer['s_name']) ?>
                        </div>
                        <div><strong>ID:</strong> <?= $farmer['id'] ?></div>
                        <div><strong>Status:</strong> <?= ucfirst($farmer['status']) ?></div>
                        <div><strong>Barangay:</strong> <?= htmlspecialchars($farmer['brgy']) ?></div>
                        <div><strong>Total Farm Area:</strong> <?= number_format($farmer['total_farm_area'], 2) ?> ha
                        </div>
                        <div><strong>Livelihoods:</strong> <?= count($livelihoods) ?> activities</div>
                        <div><strong>Printed:</strong> <?= date('F j, Y \a\t g:i A') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab button
            event.target.classList.add('active');
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Escape key to go back
            if (e.key === 'Escape') {
                history.back();
            }
            // Ctrl+P or Cmd+P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            // Number keys for tabs (1-4)
            if (e.key >= '1' && e.key <= '4') {
                const tabs = ['personal', 'farming', 'household', 'economic'];
                showTab(tabs[parseInt(e.key) - 1]);
            }
        });

        // Print functionality
        function setupPrint() {
            window.addEventListener('beforeprint', function () {
                document.body.classList.add('printing');
            });

            window.addEventListener('afterprint', function () {
                document.body.classList.remove('printing');
            });
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function () {
            setupPrint();

            // Keep sidebar dropdown always open
            const farmersDropdownMenu = document.getElementById("farmersDropdownMenu");
            const dropdownArrow = document.getElementById("dropdownArrow");
            if (farmersDropdownMenu && dropdownArrow) {
                farmersDropdownMenu.classList.remove("hidden");
                dropdownArrow.classList.add("rotate-180");
            }
        });

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function () {
            // Add loading animation to cards
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
        });
    </script>
    <script src="../js/tailwind.config.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</body>

</html>