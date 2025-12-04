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

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSBSA Enrollment - Add Data</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/output.css" rel="stylesheet">
    <style>
        html,
        body {
            height: 100%;
            overflow: hidden;
            /* 🔥 Prevents the outer scroll */
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        .form-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .form-section {
            background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 6px 6px 0 0;
            margin: -1rem -1rem 1rem -1rem;
        }

        .input-enhanced {
            transition: all 0.2s ease;
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            width: 100%;
        }

        .input-enhanced:focus {
            border-color: #166534;
            box-shadow: 0 0 0 2px rgba(22, 101, 52, 0.1);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
            cursor: pointer;
        }

        .btn-secondary {
            background: #6b7280;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
            cursor: pointer;
        }

        .progress-step {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .progress-step.active .step-number {
            background: #166534;
            border-color: #166534;
            color: white;
        }

        .progress-step.completed .step-number {
            background: #22c55e;
            border-color: #22c55e;
            color: white;
        }

        .step-number {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .radio-enhanced,
        .checkbox-enhanced {
            accent-color: #166534;
        }

        .photo-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-upload-area:hover {
            border-color: #166534;
        }

        <style>

        /* Step 5 Enhanced Styles */
        .livelihood-option {
            cursor: pointer;
        }

        .livelihood-card {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }

        .livelihood-card:hover {
            border-color: #166534;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .livelihood-check:checked+.livelihood-card {
            border-color: #166534;
            background: #f0fdf4;
            box-shadow: 0 4px 12px rgba(22, 101, 52, 0.15);
        }

        .livelihood-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .livelihood-text {
            font-weight: 600;
            color: #374151;
            display: block;
        }

        .livelihood-checkmark {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 1.25rem;
            height: 1.25rem;
            background: #166534;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .livelihood-check:checked+.livelihood-card .livelihood-checkmark {
            opacity: 1;
        }

        /* Livelihood Sections */
        .livelihood-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-icon {
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Activities Grid */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .activity-option {
            cursor: pointer;
        }

        .activity-card {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            text-align: center;
            transition: all 0.2s ease;
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .activity-card:hover {
            border-color: #166534;
            background: #f9fafb;
        }

        .activity-check:checked+.activity-card {
            border-color: #166534;
            background: #ecfdf5;
            color: #166534;
            font-weight: 600;
        }

        /* Other Input Section */
        .other-input-section {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .other-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .other-text-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .other-text-input:focus {
            outline: none;
            border-color: #166534;
            box-shadow: 0 0 0 2px rgba(22, 101, 52, 0.1);
        }

        .other-text-input.hidden {
            display: none;
        }

        /* Farm Details Grid */
        .farm-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }

        .detail-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .detail-input:focus {
            outline: none;
            border-color: #166534;
            box-shadow: 0 0 0 2px rgba(22, 101, 52, 0.1);
        }

        /* Enhanced Radio Buttons */
        .radio-option {
            cursor: pointer;
        }

        .radio-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .radio-option input:checked+.radio-content {
            border-color: #166534;
            background: #f0fdf4;
        }

        .radio-dot {
            width: 1rem;
            height: 1rem;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            position: relative;
            transition: all 0.2s ease;
        }

        .radio-option input:checked+.radio-content .radio-dot {
            border-color: #166534;
            background: #166534;
        }

        .radio-option input:checked+.radio-content .radio-dot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 0.5rem;
            height: 0.5rem;
            background: white;
            border-radius: 50%;
        }

        .radio-text {
            font-weight: 500;
            color: #374151;
        }
    </style>
    </style>
</head>

<body class="bg-gray-50">
    <div class="antialiased bg-gray-50">
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
                            class="flex items-center p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
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

        <main class="md:ml-64 pt-20 p-4 h-screen overflow-y-auto">
            <!-- Compact Header -->
            <div class="mb-4">
                <h1 class="text-xl font-bold text-gray-900 mb-1">RSBSA ENROLLMENT</h1>
            </div>

            <!-- Compact Form Container -->
            <section class="form-card p-4">
                <!-- Compact Progress Bar -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <div class="progress-step <?php echo $i === 1 ? 'active' : ''; ?>">
                                        <div class="step-number"><?php echo $i; ?></div>
                                        <?php if ($i < 6): ?>
                                            <div class="flex-1 h-1 bg-gray-200 mx-2"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-medium text-green-600">Step <span id="currentStepDisplay">1</span> of
                            6</span>
                    </div>
                </div>

                <form id="regForm" action="../../php/process.php" method="POST" enctype="multipart/form-data">

                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" id="step1">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">Personal Information</h2>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- Left Column -->
                                <div class="space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label for="date"
                                                class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                                            <input type="date" id="date" name="date" class="input-enhanced" required>
                                        </div>
                                        <div>
                                            <label for="reference"
                                                class="block text-xs font-medium text-gray-700 mb-1">Reference
                                                No.</label>
                                            <input type="text" id="reference" name="reference" class="input-enhanced"
                                                required>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label for="s_name"
                                                    class="block text-xs font-medium text-gray-700 mb-1">Surname</label>
                                                <input type="text" id="s_name" name="s_name" class="input-enhanced"
                                                    required>
                                            </div>
                                            <div>
                                                <label for="f_name"
                                                    class="block text-xs font-medium text-gray-700 mb-1">First
                                                    Name</label>
                                                <input type="text" id="f_name" name="f_name" class="input-enhanced"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label for="m_name"
                                                    class="block text-xs font-medium text-gray-700 mb-1">Middle
                                                    Name</label>
                                                <input type="text" id="m_name" name="m_name" class="input-enhanced"
                                                    required>
                                            </div>
                                            <div>
                                                <label for="e_name"
                                                    class="block text-xs font-medium text-gray-700 mb-1">Extension
                                                    Name</label>
                                                <input type="text" id="e_name" name="e_name" class="input-enhanced">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="space-y-3">
                                    <div class="form-card p-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Sex</label>
                                        <div class="space-y-1">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="gender" value="Male" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Male</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="gender" value="Female" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Female</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 class="text-xs font-medium text-gray-700 mb-2">Address</h3>
                                        <div class="space-y-3">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <input type="text" id="house" name="house" class="input-enhanced"
                                                        placeholder="House/Lot/Bldg No." required>
                                                </div>
                                                <div>
                                                    <input type="text" id="sitio" name="sitio" class="input-enhanced"
                                                        placeholder="Street/Sitio" required>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <select id="brgy" name="brgy" class="input-enhanced" required>
                                                        <option value="">Select Barangay</option>
                                                        <option value="ARMADO">ARMADO</option>
                                                        <option value="BALAO">BALAO</option>
                                                        <option value="CABACAO">CABACAO</option>
                                                        <option value="LUMANG BAYAN">LUMANG BAYAN</option>
                                                        <option value="POBLACION">POBLACION</option>
                                                        <option value="SAN VICENTE">SAN VICENTE</option>
                                                        <option value="STA. MARIA">STA. MARIA</option>
                                                        <option value="TIBAG">TIBAG</option>
                                                        <option value="WAWA">WAWA</option>
                                                        <option value="UDALO">UDALO</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <select id="municipal" name="municipal" class="input-enhanced"
                                                        required>
                                                        <option value="">Municipality/City</option>
                                                        <option value="Abra de Ilog">Abra de Ilog</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <select id="province" name="province" class="input-enhanced"
                                                        required>
                                                        <option value="">Select Province</option>
                                                        <option value="Occidental Mindoro">Occidental Mindoro</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <select id="region" name="region" class="input-enhanced" required>
                                                        <option value="">Select Region</option>
                                                        <option value="4-B">4-B</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end mt-4 pt-3 border-t border-gray-200">
                                <button type="button" onclick="nextStep()" class="btn-primary text-sm">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Contact & Birth Information -->
                    <div class="form-step" id="step2">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">Contact & Birth Information</h2>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- Contact Information -->
                                <div class="space-y-3">
                                    <div>
                                        <label for="mobile" class="block text-xs font-medium text-gray-700 mb-1">Mobile
                                            Number</label>
                                        <input type="tel" id="mobile" name="mobile" class="input-enhanced"
                                            placeholder="Mobile number">
                                    </div>
                                    <div>
                                        <label for="landline"
                                            class="block text-xs font-medium text-gray-700 mb-1">Landline Number</label>
                                        <input type="tel" id="landline" name="landline" class="input-enhanced"
                                            placeholder="Landline number">
                                    </div>
                                    <div>
                                        <label for="dob" class="block text-xs font-medium text-gray-700 mb-1">Date of
                                            Birth</label>
                                        <input type="date" id="dob" name="dob" class="input-enhanced" required>
                                    </div>
                                </div>

                                <!-- Birth Place -->
                                <div class="space-y-3">
                                    <h3 class="text-xs font-medium text-gray-700 mb-2">Place of Birth</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <select id="country" name="country" class="input-enhanced" required>
                                                <option value="">Select Country</option>
                                                <option value="Philippines">Philippines</option>
                                            </select>
                                        </div>
                                        <div>
                                            <input type="text" id="province_birth" name="province_birth"
                                                class="input-enhanced" placeholder="Province" required>
                                        </div>
                                        <div>
                                            <input type="text" id="municipality_birth" name="municipality_birth"
                                                class="input-enhanced" placeholder="Municipality" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-4 pt-3 border-t border-gray-200">
                                <button type="button" onclick="prevStep()" class="btn-secondary text-sm">
                                    ← Back
                                </button>
                                <button type="button" onclick="nextStep()" class="btn-primary text-sm">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Household Information -->
                    <div class="form-step" id="step3">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">Household Information</h2>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label for="mother_maiden"
                                            class="block text-xs font-medium text-gray-700 mb-1">Mother's Maiden
                                            Name</label>
                                        <input type="text" id="mother_maiden" name="mother_maiden"
                                            class="input-enhanced" required>
                                    </div>
                                    <div class="form-card p-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Household
                                            Head?</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="household_head" value="Yes"
                                                    class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Yes</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="household_head" value="No"
                                                    class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div id="householdHeadFields" style="display: none;">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div>
                                            <label for="if_nohousehold"
                                                class="block text-xs font-medium text-gray-700 mb-1">If no, name of
                                                household head:</label>
                                            <input type="text" id="if_nohousehold" name="if_nohousehold"
                                                class="input-enhanced">
                                        </div>
                                        <div>
                                            <label for="relationship"
                                                class="block text-xs font-medium text-gray-700 mb-1">Relationship</label>
                                            <select id="relationship" name="relationship" class="input-enhanced">
                                                <option value="">Select Relationship</option>
                                                <option value="MOTHER">MOTHER</option>
                                                <option value="FATHER">FATHER</option>
                                                <option value="BROTHER">BROTHER</option>
                                                <option value="SISTER">SISTER</option>
                                                <option value="AUNTIE">AUNTIE</option>
                                                <option value="UNCLE">UNCLE</option>
                                                <option value="GRAND MOTHER">GRANDMOTHER</option>
                                                <option value="GRAND FATHER">GRANDFATHER</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div>
                                        <label for="no_livinghousehold"
                                            class="block text-xs font-medium text-gray-700 mb-1">No. of Living
                                            Household</label>
                                        <input type="number" id="no_livinghousehold" name="no_livinghousehold"
                                            class="input-enhanced" required>
                                    </div>
                                    <div>
                                        <label for="no_male" class="block text-xs font-medium text-gray-700 mb-1">No.
                                            Male</label>
                                        <input type="number" id="no_male" name="no_male" class="input-enhanced"
                                            required>
                                    </div>
                                    <div>
                                        <label for="no_female" class="block text-xs font-medium text-gray-700 mb-1">No.
                                            of Female</label>
                                        <input type="number" id="no_female" name="no_female" class="input-enhanced"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-4 pt-3 border-t border-gray-200">
                                <button type="button" onclick="prevStep()" class="btn-secondary text-sm">
                                    ← Back
                                </button>
                                <button type="button" onclick="nextStep()" class="btn-primary text-sm">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Education & Status -->
                    <div class="form-step" id="step4">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">Education & Status</h2>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- Left Column -->
                                <div class="space-y-4">
                                    <div>
                                        <label for="education"
                                            class="block text-xs font-medium text-gray-700 mb-1">Highest Formal
                                            Education</label>
                                        <select id="education" name="education" class="input-enhanced" required>
                                            <option value="">Select Education</option>
                                            <option value="PRE-SCHOOL">PRE-SCHOOL</option>
                                            <option value="ELEMENTARY">ELEMENTARY</option>
                                            <option value="HIGH SCHOOL (NON-K-12)">HIGH SCHOOL (NON-K-12)</option>
                                            <option value="JUNIOR SCHOOL (K-12)">JUNIOR SCHOOL (K-12)</option>
                                            <option value="COLLEGE">COLLEGE</option>
                                            <option value="VOCATIONAL">VOCATIONAL</option>
                                            <option value="POST-GRADUATE">POST-GRADUATE</option>
                                            <option value="NONE">NONE</option>
                                        </select>
                                    </div>

                                    <div class="form-card p-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Person With
                                            Disabilities (PWD)?</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="pwd" value="Yes" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Yes</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="pwd" value="No" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">No</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-card p-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">4P's
                                            Beneficiary?</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="for_ps" value="Yes" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Yes</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="for_ps" value="No" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="space-y-4">
                                    <div class="form-card p-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-2">With Government
                                            ID?</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="with_gov" value="Yes" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">Yes</span>
                                            </label>
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="radio" name="with_gov" value="No" class="radio-enhanced">
                                                <span class="text-gray-700 text-sm">No</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="specify_id" class="block text-xs font-medium text-gray-700 mb-1">If
                                            Yes, Specify ID Type:</label>
                                        <input type="text" id="specify_id" name="specify_id" class="input-enhanced">
                                    </div>

                                    <div>
                                        <label for="id_no" class="block text-xs font-medium text-gray-700 mb-1">ID
                                            Number:</label>
                                        <input type="text" id="id_no" name="id_no" class="input-enhanced">
                                    </div>
                                </div>
                            </div>

                            <!-- Income Section -->
                            <div class="mt-4 p-3 border border-gray-200 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-700 mb-3">Gross Annual Income</h3>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label for="farming"
                                            class="block text-xs font-medium text-gray-700 mb-1">Farming</label>
                                        <input type="number" id="farming" name="farming" class="input-enhanced"
                                            placeholder="Amount">
                                    </div>
                                    <div>
                                        <label for="non_farming"
                                            class="block text-xs font-medium text-gray-700 mb-1">Non-Farming</label>
                                        <input type="number" id="non_farming" name="non_farming" class="input-enhanced"
                                            placeholder="Amount">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-4 pt-3 border-t border-gray-200">
                                <button type="button" onclick="prevStep()" class="btn-secondary text-sm">
                                    ← Back
                                </button>
                                <button type="button" onclick="nextStep()" class="btn-primary text-sm">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Farmer Profile - ORIGINAL FUNCTIONALITY PRESERVED -->
                    <!-- Step 5: Farmer Profile - ENHANCED FUNCTIONALITY -->
                    <div class="form-step" id="step5">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">FARMER PROFILE</h2>
                            </div>

                            <div class="space-y-4">
                                <!-- Main Livelihood Section -->
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">Main Livelihood <span
                                            class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <label class="livelihood-option">
                                            <input type="checkbox" name="main_livelihood[]" value="farmer"
                                                class="livelihood-check hidden">
                                            <div class="livelihood-card">
                                                <div class="livelihood-icon">🌾</div>
                                                <span class="livelihood-text">Farmer</span>
                                                <div class="livelihood-checkmark">✓</div>
                                            </div>
                                        </label>
                                        <label class="livelihood-option">
                                            <input type="checkbox" name="main_livelihood[]" value="farmerworker"
                                                class="livelihood-check hidden">
                                            <div class="livelihood-card">
                                                <div class="livelihood-icon">👨‍🌾</div>
                                                <span class="livelihood-text">Farmer Worker</span>
                                                <div class="livelihood-checkmark">✓</div>
                                            </div>
                                        </label>
                                        <label class="livelihood-option">
                                            <input type="checkbox" name="main_livelihood[]" value="fisherfolk"
                                                class="livelihood-check hidden">
                                            <div class="livelihood-card">
                                                <div class="livelihood-icon">🎣</div>
                                                <span class="livelihood-text">Fisherfolk</span>
                                                <div class="livelihood-checkmark">✓</div>
                                            </div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">Select one or more main livelihood activities
                                    </p>
                                </div>

                                <!-- Hidden categories for PHP -->
                                <input type="hidden" name="category_name[]" value="1"> <!-- Farmer -->
                                <input type="hidden" name="category_name[]" value="2"> <!-- Farmworker -->
                                <input type="hidden" name="category_name[]" value="3"> <!-- Fisherfolk -->

                                <!-- FARMER ACTIVITIES -->
                                <div id="farmerSection" class="livelihood-section" style="display:none;">
                                    <div class="section-header">
                                        <div class="section-icon">🌾</div>
                                        <h3 class="section-title">Farmer Activities</h3>
                                    </div>

                                    <div class="activities-grid">
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_1[]" value="1"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>RICE</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_1[]" value="2"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>CORN</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_1[]" value="3"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>VEGETABLE</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_1[]" value="4"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>POULTRY</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_1[]" value="5"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>LIVESTOCK</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Other Input for Farmer -->
                                    <div class="other-input-section">
                                        <label class="other-option">
                                            <input type="checkbox" id="farmerOtherCheck" value="other"
                                                class="other-check">
                                            <span>OTHER (Please specify)</span>
                                        </label>
                                        <input type="text" id="farmerOtherInput" name="farmer_other"
                                            class="other-text-input" placeholder="Specify other farming activity">
                                    </div>

                                    <!-- Farm Details -->
                                    <div class="farm-details-grid">
                                        <div class="detail-group">
                                            <label class="detail-label">🌾 Farm Area</label>
                                            <input type="text" name="farmer_area" class="detail-input"
                                                placeholder="e.g., 2.5 ha or 5000 sq.m">
                                        </div>
                                        <div class="detail-group">
                                            <label class="detail-label">📍 Farm Location</label>
                                            <input type="text" name="farmer_location" class="detail-input"
                                                placeholder="Specific location of farm">
                                        </div>
                                    </div>
                                </div>

                                <!-- FARMER WORKER ACTIVITIES -->
                                <div id="farmerworkerSection" class="livelihood-section" style="display:none;">
                                    <div class="section-header">
                                        <div class="section-icon">👨‍🌾</div>
                                        <h3 class="section-title">Farmer Worker Activities</h3>
                                    </div>

                                    <div class="activities-grid">
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_2[]" value="6"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>LAND PREPARATION</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_2[]" value="7"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>PLANTING</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_2[]" value="8"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>CULTIVATION</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_2[]" value="9"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>HARVESTING</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Other Input for Farmer Worker -->
                                    <div class="other-input-section">
                                        <label class="other-option">
                                            <input type="checkbox" id="fwOtherCheck" value="other" class="other-check">
                                            <span>OTHER (Please specify)</span>
                                        </label>
                                        <input type="text" id="fwOtherInput" name="farmerworker_other"
                                            class="other-text-input" placeholder="Specify other work activity">
                                    </div>

                                    <!-- Farm Details -->
                                    <div class="farm-details-grid">
                                        <div class="detail-group">
                                            <label class="detail-label">🌿 Total Area</label>
                                            <input type="text" name="total_area_2" class="detail-input"
                                                placeholder="e.g., 2.5 ha or 5000 sq.m">
                                        </div>
                                        <div class="detail-group">
                                            <label class="detail-label">📍 Farm Location</label>
                                            <input type="text" name="farm_location_2" class="detail-input"
                                                placeholder="Specific location of work">
                                        </div>
                                    </div>
                                </div>

                                <!-- FISHERFOLK ACTIVITIES -->
                                <div id="fisherfolkSection" class="livelihood-section" style="display:none;">
                                    <div class="section-header">
                                        <div class="section-icon">🎣</div>
                                        <h3 class="section-title">Fisherfolk Activities</h3>
                                    </div>

                                    <div class="activities-grid">
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_3[]" value="10"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>FISH CAPTURE</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_3[]" value="11"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>AQUACULTURE</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_3[]" value="12"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>GLEANING</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_3[]" value="13"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>FISH PROCESSING</span>
                                            </div>
                                        </label>
                                        <label class="activity-option">
                                            <input type="checkbox" name="sub_activity_id_3[]" value="14"
                                                class="activity-check">
                                            <div class="activity-card">
                                                <span>FISH VENDING</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Other Input for Fisherfolk -->
                                    <div class="other-input-section">
                                        <label class="other-option">
                                            <input type="checkbox" id="ffOtherCheck" value="other" class="other-check">
                                            <span>OTHER (Please specify)</span>
                                        </label>
                                        <input type="text" id="ffOtherInput" name="fisherfolk_other"
                                            class="other-text-input" placeholder="Specify other fishing activity">
                                    </div>

                                    <!-- Farm Details -->
                                    <div class="farm-details-grid">
                                        <div class="detail-group">
                                            <label class="detail-label">🌊 Total Area</label>
                                            <input type="text" name="total_area_3" class="detail-input"
                                                placeholder="e.g., 2.5 ha or water area">
                                        </div>
                                        <div class="detail-group">
                                            <label class="detail-label">📍 Location</label>
                                            <input type="text" name="farm_location_3" class="detail-input"
                                                placeholder="Fishing area location">
                                        </div>
                                    </div>
                                </div>

                                <!-- Farm Management Details -->
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mt-4">
                                    <h3 class="text-sm font-medium text-gray-700 mb-3">Farm Management Details</h3>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div>
                                            <label for="no_farmparcel"
                                                class="block text-xs font-medium text-gray-700 mb-1">
                                                Number of Farm Parcels
                                            </label>
                                            <input type="number" id="no_farmparcel" name="no_farmparcel"
                                                class="input-enhanced" placeholder="Enter number of parcels">
                                        </div>
                                        <div>
                                            <label for="ownership" class="block text-xs font-medium text-gray-700 mb-1">
                                                Ownership/Land Title Number
                                            </label>
                                            <input type="text" id="ownership" name="ownership" class="input-enhanced"
                                                placeholder="Enter ownership number">
                                        </div>
                                    </div>
                                </div>

                                <!-- Agrarian Reform -->
                                <div class="form-card p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Agrarian Reform Beneficiary?
                                    </label>
                                    <div class="flex gap-6">
                                        <label class="radio-option">
                                            <input type="radio" name="agrarian" value="Yes" class="radio-enhanced"
                                                required>
                                            <div class="radio-content">
                                                <div class="radio-dot"></div>
                                                <span class="radio-text">Yes</span>
                                            </div>
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="agrarian" value="No" class="radio-enhanced"
                                                required>
                                            <div class="radio-content">
                                                <div class="radio-dot"></div>
                                                <span class="radio-text">No</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-6 pt-4 border-t border-gray-200">
                                <button type="button" onclick="prevStep()" class="btn-secondary text-sm">
                                    ← Back
                                </button>
                                <button type="button" onclick="nextStep()" class="btn-primary text-sm">
                                    Next →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Final Details -->
                    <div class="form-step" id="step6">
                        <div class="w-full">
                            <div class="form-section">
                                <h2 class="text-lg font-bold">Final Details</h2>
                            </div>

                            <div class="space-y-4">
                                <!-- Farmer Location -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label for="farmer_location_brgy"
                                            class="block text-xs font-medium text-gray-700 mb-1">Barangay</label>
                                        <select id="farmer_location_brgy" name="farmer_location_brgy"
                                            class="input-enhanced" required>
                                            <option value="">Select Barangay</option>
                                            <option value="ARMADO">ARMADO</option>
                                            <option value="BALAO">BALAO</option>
                                            <option value="CAMURONG">CAMURONG</option>
                                            <option value="CABACAO">CABACAO</option>
                                            <option value="LUMANGBAYAN">LUMANGBAYAN</option>
                                            <option value="POBLACION">POBLACION</option>
                                            <option value="SAN VICENTE">SAN VICENTE</option>
                                            <option value="STA. MARIA">STA. MARIA</option>
                                            <option value="UDALO">UDALO</option>
                                            <option value="WAWA">WAWA</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="farmer_location_municipal"
                                            class="block text-xs font-medium text-gray-700 mb-1">Municipality/City</label>
                                        <select id="farmer_location_municipal" name="farmer_location_municipal"
                                            class="input-enhanced" required>
                                            <option value="">Select Municipality</option>
                                            <option value="ABRA DE ILOG">ABRA DE ILOG</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="farmer_location_ownership"
                                        class="block text-xs font-medium text-gray-700 mb-1">Ownership Type</label>
                                    <select id="farmer_location_ownership" name="farmer_location_ownership"
                                        class="input-enhanced" required>
                                        <option value="">Select Ownership Type</option>
                                        <option value="REGISTERED OWNER">REGISTERED OWNER</option>
                                        <option value="TENANT">TENANT</option>
                                        <option value="LESSEE">LESSEE</option>
                                    </select>
                                </div>

                                <!-- Photo Upload -->
                                <div class="mt-4">
                                    <label class="block text-xs font-medium text-gray-700 mb-2">Photo Upload</label>
                                    <div class="photo-upload-area">
                                        <input type="file" id="photo" name="photo" class="hidden" accept="image/*">
                                        <div class="text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                            <p class="text-sm text-gray-600">Click to upload photo</p>
                                            <p class="text-xs text-gray-500">PNG, JPG up to 2MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-4 pt-3 border-t border-gray-200">
                                <button type="button" onclick="prevStep()" class="btn-secondary text-sm">
                                    ← Back
                                </button>
                                <button type="submit" class="btn-primary text-sm">
                                    Submit Enrollment
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Enhanced Step 5 Functionality
        document.addEventListener("DOMContentLoaded", function () {
            // Main livelihood checkboxes
            const livelihoodChecks = document.querySelectorAll(".livelihood-check");

            // Sections
            const farmerSection = document.getElementById("farmerSection");
            const farmerworkerSection = document.getElementById("farmerworkerSection");
            const fisherfolkSection = document.getElementById("fisherfolkSection");

            // Map livelihood to sections
            const sections = [
                { type: "farmer", section: farmerSection, otherCheck: "farmerOtherCheck", otherInput: "farmerOtherInput" },
                { type: "farmerworker", section: farmerworkerSection, otherCheck: "fwOtherCheck", otherInput: "fwOtherInput" },
                { type: "fisherfolk", section: fisherfolkSection, otherCheck: "ffOtherCheck", otherInput: "ffOtherInput" }
            ];

            // Initialize other inputs
            sections.forEach(({ otherCheck, otherInput }) => {
                const checkbox = document.getElementById(otherCheck);
                const input = document.getElementById(otherInput);

                if (checkbox && input) {
                    checkbox.addEventListener("change", function () {
                        input.classList.toggle("hidden", !this.checked);
                        if (!this.checked) input.value = "";
                    });
                }
            });

            // Handle main livelihood selection
            livelihoodChecks.forEach(cb => {
                cb.addEventListener("change", function () {
                    const match = sections.find(s => s.type === this.value);
                    if (!match) return;

                    if (this.checked) {
                        match.section.style.display = "block";
                        // Scroll to the section smoothly
                        setTimeout(() => {
                            match.section.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }, 300);
                    } else {
                        match.section.style.display = "none";
                        // Reset all inputs in the section
                        const inputs = match.section.querySelectorAll('input');
                        inputs.forEach(input => {
                            if (input.type === 'checkbox' || input.type === 'radio') {
                                input.checked = false;
                            } else {
                                input.value = "";
                            }
                        });

                        // Hide other inputs
                        const otherCheckbox = document.getElementById(match.otherCheck);
                        const otherInput = document.getElementById(match.otherInput);
                        if (otherCheckbox && otherInput) {
                            otherCheckbox.checked = false;
                            otherInput.classList.add("hidden");
                        }
                    }

                    updateStep5Progress();
                });
            });

            // Activity selection counter
            function updateStep5Progress() {
                const activeSections = sections.filter(s =>
                    s.section.style.display !== "none" &&
                    s.section.style.display !== ""
                ).length;

                const totalActivities = document.querySelectorAll('.activity-check:checked').length;

                // You can add progress indicators here if needed
                console.log(`Active sections: ${activeSections}, Selected activities: ${totalActivities}`);
            }

            // Add change listeners to activity checkboxes for progress tracking
            document.querySelectorAll('.activity-check').forEach(cb => {
                cb.addEventListener('change', updateStep5Progress);
            });

            // Enhanced input validation for step 5
            function validateStep5() {
                const selectedLivelihoods = document.querySelectorAll('.livelihood-check:checked').length;
                if (selectedLivelihoods === 0) {
                    alert('Please select at least one main livelihood activity.');
                    return false;
                }

                // Check if at least one activity is selected in each visible section
                let isValid = true;
                sections.forEach(({ section, type }) => {
                    if (section.style.display !== "none" && section.style.display !== "") {
                        const activities = section.querySelectorAll('.activity-check:checked');
                        const otherCheckbox = document.getElementById(`${type}OtherCheck`);
                        const otherInput = document.getElementById(`${type}OtherInput`);

                        const hasActivities = activities.length > 0;
                        const hasOther = otherCheckbox && otherCheckbox.checked && otherInput && otherInput.value.trim() !== "";

                        if (!hasActivities && !hasOther) {
                            isValid = false;
                            // Highlight the section
                            section.style.borderColor = '#ef4444';
                            section.style.backgroundColor = '#fef2f2';

                            // Reset after 2 seconds
                            setTimeout(() => {
                                section.style.borderColor = '';
                                section.style.backgroundColor = '';
                            }, 2000);
                        }
                    }
                });

                if (!isValid) {
                    alert('Please select at least one activity for each livelihood type you have chosen.');
                    return false;
                }

                return true;
            }

            // Override nextStep for step 5 validation
            const originalNextStep = window.nextStep;
            window.nextStep = function () {
                if (currentStep === 5) {
                    if (!validateStep5()) {
                        return;
                    }
                }
                originalNextStep();
            };
        });
    </script>
    <script>
        let currentStep = 1;
        const totalSteps = 6;

        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.form-step').forEach(stepEl => {
                stepEl.classList.remove('active');
            });

            // Show current step
            document.getElementById(`step${step}`).classList.add('active');

            // Update progress bar
            updateProgressBar(step);

            // Update current step display
            document.getElementById('currentStepDisplay').textContent = step;

            currentStep = step;

            // Scroll to top of form
            document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function updateProgressBar(step) {
            document.querySelectorAll('.progress-step').forEach((stepEl, index) => {
                const stepNumber = index + 1;
                stepEl.classList.remove('active', 'completed');

                if (stepNumber === step) {
                    stepEl.classList.add('active');
                } else if (stepNumber < step) {
                    stepEl.classList.add('completed');
                }
            });
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                showStep(currentStep + 1);
            } else {
                document.getElementById("regForm").submit();
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }

        // Household head conditional field
        document.addEventListener('DOMContentLoaded', function () {
            const householdHeadRadios = document.querySelectorAll('input[name="household_head"]');
            const householdHeadFields = document.getElementById('householdHeadFields');

            householdHeadRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (this.value === 'No') {
                        householdHeadFields.style.display = 'block';
                    } else {
                        householdHeadFields.style.display = 'none';
                    }
                });
            });

            // Photo upload click handler
            const photoUploadArea = document.querySelector('.photo-upload-area');
            const photoInput = document.getElementById('photo');

            if (photoUploadArea && photoInput) {
                photoUploadArea.addEventListener('click', function () {
                    photoInput.click();
                });

                photoInput.addEventListener('change', function (e) {
                    if (e.target.files.length > 0) {
                        photoUploadArea.innerHTML = `
                            <div class="text-center text-green-600">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <p class="text-sm">Photo selected</p>
                                <p class="text-xs">${e.target.files[0].name}</p>
                            </div>
                        `;
                    }
                });
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            showStep(1);
        });

        // Session timeout
        let warningTime = 4 * 60 * 1000;
        let logoutTime = 5 * 60 * 1000;
        let warningTimer, logoutTimer;

        function resetTimers() {
            clearTimeout(warningTimer);
            clearTimeout(logoutTimer);

            warningTimer = setTimeout(() => {
                if (confirm('⚠️ You will be logged out in 1 minute due to inactivity. Click OK to stay logged in.')) {
                    resetTimers();
                }
            }, warningTime);

            logoutTimer = setTimeout(() => {
                window.location.href = "logout.php";
            }, logoutTime);
        }

        window.onload = resetTimers;
        document.onmousemove = resetTimers;
        document.onkeypress = resetTimers;
        document.onscroll = resetTimers;
        document.onclick = resetTimers;

        // Enhanced logout confirmation
        function confirmLogout(event) {
            if (!confirm("Are you sure you want to logout?")) {
                event.preventDefault();
                return false;
            }
        }

        // Toggle user dropdown
        function toggleUserDropdown() {
            // Your existing dropdown toggle code
        }
        // Step 5 Dropdown Functionality - ORIGINAL PRESERVED
        document.addEventListener("DOMContentLoaded", function () {
            // Main livelihood checkboxes
            const livelihoodChecks = document.querySelectorAll(".livelihood-check");

            // Divs
            const farmerDiv = document.getElementById("farmerDiv");
            const farmerworkerDiv = document.getElementById("farmerworkerDiv");
            const fisherfolkDiv = document.getElementById("fisherfolkDiv");

            // Map livelihood to dropdown container
            const dropdowns = [
                { type: "farmer", div: farmerDiv, otherCheck: "farmerOtherCheck", otherInput: "farmerOtherInput" },
                { type: "farmerworker", div: farmerworkerDiv, otherCheck: "fwOtherCheck", otherInput: "fwOtherInput" },
                { type: "fisherfolk", div: fisherfolkDiv, otherCheck: "ffOtherCheck", otherInput: "ffOtherInput" }
            ];

            function hideAll() {
                dropdowns.forEach(d => d.div.style.display = "none");
            }
            hideAll();

            // Setup Dropdown Controls
            function setupDropdown({ div, otherCheck, otherInput }) {
                const button = div.querySelector("button");
                const menu = div.querySelector(".dropdown-menu");
                const label = button.querySelector("span");
                const checkboxes = menu.querySelectorAll("input[type='checkbox']");
                const otherCheckbox = document.getElementById(otherCheck);
                const otherTextbox = document.getElementById(otherInput);

                // Toggle dropdown
                button.addEventListener("click", (e) => {
                    e.stopPropagation();
                    menu.classList.toggle("hidden");
                });

                // Handle selection changes
                checkboxes.forEach(cb => {
                    cb.addEventListener("change", () => {
                        updateLabel();
                        if (cb === otherCheckbox) {
                            otherTextbox.classList.toggle("hidden", !cb.checked);
                            if (!cb.checked) otherTextbox.value = "";
                        }
                    });
                });

                function updateLabel() {
                    const selected = [...checkboxes]
                        .filter(cb => cb.checked)
                        .map(cb => {
                            if (cb === otherCheckbox && otherTextbox.value.trim() !== "")
                                return otherTextbox.value.trim();
                            return cb.parentElement.textContent.trim();
                        });
                    label.textContent = selected.length ? selected.join(", ") : button.textContent.includes("Select") ? button.textContent : "Select Options";
                }

                // Hide dropdown when clicking outside
                document.addEventListener("click", (e) => {
                    if (!div.contains(e.target)) {
                        menu.classList.add("hidden");
                        updateLabel();
                    }
                });
            }

            // Init each dropdown
            dropdowns.forEach(d => setupDropdown(d));

            // Handle checking/unchecking main livelihood
            livelihoodChecks.forEach(cb => {
                cb.addEventListener("change", () => {
                    const match = dropdowns.find(d => d.type === cb.value);
                    if (!match) return;

                    const div = match.div;
                    const menu = div.querySelector(".dropdown-menu");
                    const buttonLabel = div.querySelector("button span");
                    const otherTextbox = document.getElementById(match.otherInput);
                    const otherCheckbox = document.getElementById(match.otherCheck);

                    if (cb.checked) {
                        div.style.display = "block";
                    } else {
                        div.style.display = "none";
                        menu.classList.add("hidden");
                        div.querySelectorAll("input[type='checkbox']").forEach(opt => opt.checked = false);
                        otherTextbox.classList.add("hidden");
                        otherTextbox.value = "";
                        otherCheckbox.checked = false;
                        buttonLabel.textContent = buttonLabel.textContent.includes("Select") ? buttonLabel.textContent : "Select Options";
                    }
                });
            });

            // Main livelihood dropdown
            const mainDropdownBtn = document.getElementById("dropdownButton");
            const mainDropdownMenu = document.getElementById("dropdownMenu");

            if (mainDropdownBtn && mainDropdownMenu) {
                mainDropdownBtn.addEventListener("click", () => {
                    mainDropdownMenu.classList.toggle("hidden");
                });

                document.addEventListener("click", (e) => {
                    if (!mainDropdownBtn.contains(e.target) && !mainDropdownMenu.contains(e.target)) {
                        mainDropdownMenu.classList.add("hidden");
                    }
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
</body>

</html>