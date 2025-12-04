<?php
require_once "../../db/conn.php";

$staff_id = $_GET['staff_id'] ?? '';
if (!$staff_id) {
    die("Invalid request");
}

$cookie_name = "staff_token_" . $staff_id;
$token = $_COOKIE[$cookie_name] ?? '';
if (!$token) {
    header("Location: stafflogin.php");
    exit;
}

// Validate token
$sql = "SELECT u.fullname 
        FROM staff_sessions s
        JOIN user_accounts u ON u.id = s.staff_id
        WHERE s.session_token=? AND s.staff_id=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $token, $staff_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows < 1) {
    $stmt->close();
    setcookie($cookie_name, "", time() - 3600, "/");
    header("Location: stafflogin.php");
    exit;
}

$stmt->bind_result($fullname);
$stmt->fetch();
$stmt->close();

// Update last activity
$stmt = $conn->prepare("UPDATE staff_sessions SET last_activity=NOW() WHERE session_token=?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->close();

echo "Welcome, " . htmlspecialchars($fullname) . " (Staff ID: $staff_id)";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RSBSA Enrollment System - Municipal Agriculture Office">
    <title>RSBSA Enrollment - Add Data</title>

    <!-- CSS Resources -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../../css/output.css" rel="stylesheet">

    <style>
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .required-field::after {
            content: " *";
            color: #ef4444;
        }

        .custom-dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 100%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }

        .dropdown-item:hover {
            background-color: #f9fafb;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .photo-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-upload-area:hover {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .photo-upload-area.dragover {
            border-color: #10b981;
            background-color: #dcfce7;
        }

        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .success-notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #10b981;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .error-notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #ef4444;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

                <li>
                    <a href="datalist.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
                        <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-3 flex-1">Data List</span>
                        <svg class="w-4 h-4 ml-auto text-gray-700" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <main class="md:ml-64 pt-20 p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                RSBSA ENROLLMENT
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Add new farmer data to the system
            </p>
        </div>

        <!-- Progress Indicator -->
        <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="w-full max-w-4xl mx-auto">
                <ol class="flex items-center justify-between w-full text-sm font-medium text-gray-600">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <li class="flex items-center <?= $i < 6 ? 'flex-1' : '' ?>">
                            <span class="flex items-center justify-center w-8 h-8 mr-2 border-2 rounded-full 
                            <?= $i == 1 ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-gray-100 text-gray-500' ?> 
                            transition-all duration-200 step-indicator" data-step="<?= $i ?>">
                                <?= $i == 1 ? '1' : $i ?>
                            </span>
                            <span class="hidden sm:block text-xs">Step <?= $i ?></span>
                            <?php if ($i < 6): ?>
                                <span class="flex-1 h-0.5 bg-gray-200 mx-4 transition-colors duration-200 step-connector"
                                    data-step="<?= $i ?>"></span>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ol>
            </div>
        </div>

        <section class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <!-- Form Container -->
            <div class="p-6">
                <form id="farmerForm" action="../../php/process.php" method="POST" enctype="multipart/form-data"
                    novalidate>

                    <!-- Step 1: Personal Information -->
                    <div class="step-content active" id="step1" data-step="1">
                        <div class="form-section">
                            <h2 class="form-section-title">Personal Information</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="reg_date"
                                        class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                        Date
                                    </label>
                                    <input type="date" id="reg_date" name="reg_date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div>
                                    <label for="reference"
                                        class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                        Reference No.
                                    </label>
                                    <input type="text" id="reference" name="reference"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        placeholder="Enter reference number" required>
                                </div>
                            </div>

                            <!-- Name Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="s_name"
                                        class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                        Surname
                                    </label>
                                    <input type="text" id="s_name" name="s_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        required>
                                </div>
                                <div>
                                    <label for="f_name"
                                        class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                        First Name
                                    </label>
                                    <input type="text" id="f_name" name="f_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="m_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Middle Name
                                    </label>
                                    <input type="text" id="m_name" name="m_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                                <div>
                                    <label for="e_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Extension Name
                                    </label>
                                    <input type="text" id="e_name" name="e_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                            </div>

                            <!-- Gender -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                    Sex
                                </label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="gender" value="Male"
                                            class="text-green-600 focus:ring-green-500" required>
                                        <span class="ml-2">Male</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="gender" value="Female"
                                            class="text-green-600 focus:ring-green-500">
                                        <span class="ml-2">Female</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Address Section -->
                            <div class="mb-4">
                                <h3 class="text-md font-medium text-gray-900 mb-3">Address</h3>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="house" class="block text-sm font-medium text-gray-700 mb-2">
                                                House/Lot/Bldg No./Purok
                                            </label>
                                            <input type="text" id="house" name="house"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        </div>
                                        <div>
                                            <label for="sitio" class="block text-sm font-medium text-gray-700 mb-2">
                                                Street/Sitio/Subdivision
                                            </label>
                                            <input type="text" id="sitio" name="sitio"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="brgy"
                                                class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                                Barangay
                                            </label>
                                            <select id="brgy" name="brgy"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                                required>
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
                                            <label for="municipal" class="block text-sm font-medium text-gray-700 mb-2">
                                                Municipality/City
                                            </label>
                                            <select id="municipal" name="municipal"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                                <option value="Abra de Ilog">Abra de Ilog</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="province" class="block text-sm font-medium text-gray-700 mb-2">
                                                Province
                                            </label>
                                            <select id="province" name="province"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                                <option value="Occidental Mindoro">Occidental Mindoro</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="region" class="block text-sm font-medium text-gray-700 mb-2">
                                                Region
                                            </label>
                                            <select id="region" name="region"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                                <option value="4-B">4-B</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Contact & Birth Information -->
                    <div class="step-content" id="step2" data-step="2">
                        <div class="form-section">
                            <h2 class="form-section-title">Contact & Birth Information</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">
                                        Mobile Number
                                    </label>
                                    <input type="tel" id="mobile" name="mobile"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        placeholder="Enter mobile number">
                                </div>
                                <div>
                                    <label for="landline" class="block text-sm font-medium text-gray-700 mb-2">
                                        Landline Number
                                    </label>
                                    <input type="tel" id="landline" name="landline"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        placeholder="Enter landline number">
                                </div>
                            </div>

                            <div class="mb-6">
                                <label for="dob" class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                    Date of Birth
                                </label>
                                <input type="date" id="dob" name="dob"
                                    class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                    required>
                            </div>

                            <!-- Place of Birth -->
                            <div class="mb-4">
                                <h3 class="text-md font-medium text-gray-900 mb-3">Place of Birth</h3>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                                                Country
                                            </label>
                                            <select id="country" name="country"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                                <option value="">Select Country</option>
                                                <option value="Philippines">Philippines</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="province_birth"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Province
                                            </label>
                                            <input type="text" id="province_birth" name="province_birth"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        </div>
                                    </div>

                                    <div class="w-full md:w-1/2">
                                        <label for="municipality_birth"
                                            class="block text-sm font-medium text-gray-700 mb-2">
                                            Municipality
                                        </label>
                                        <input type="text" id="municipality_birth" name="municipality_birth"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Household Information -->
                    <div class="step-content" id="step3" data-step="3">
                        <div class="form-section">
                            <h2 class="form-section-title">Household Information</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="mother_maiden" class="block text-sm font-medium text-gray-700 mb-2">
                                        Mother's Maiden Name
                                    </label>
                                    <input type="text" id="mother_maiden" name="mother_maiden"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required-field">
                                        Household Head?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="household_head" value="Yes"
                                                class="text-green-600 focus:ring-green-500" required>
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="household_head" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6" id="householdHeadSection" style="display: none;">
                                <label for="if_nohousehold" class="block text-sm font-medium text-gray-700 mb-2">
                                    If no, name of household head:
                                </label>
                                <input type="text" id="if_nohousehold" name="if_nohousehold"
                                    class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="relationship" class="block text-sm font-medium text-gray-700 mb-2">
                                        Relationship to Household Head
                                    </label>
                                    <select id="relationship" name="relationship"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
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
                                <div>
                                    <label for="no_livinghousehold"
                                        class="block text-sm font-medium text-gray-700 mb-2">
                                        No. of Living Household Members
                                    </label>
                                    <input type="number" id="no_livinghousehold" name="no_livinghousehold"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        min="0">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="no_male" class="block text-sm font-medium text-gray-700 mb-2">
                                        No. of Male Members
                                    </label>
                                    <input type="number" id="no_male" name="no_male"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        min="0">
                                </div>
                                <div>
                                    <label for="no_female" class="block text-sm font-medium text-gray-700 mb-2">
                                        No. of Female Members
                                    </label>
                                    <input type="number" id="no_female" name="no_female"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        min="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Education & Socio-Economic Information -->
                    <div class="step-content" id="step4" data-step="4">
                        <div class="form-section">
                            <h2 class="form-section-title">Education & Socio-Economic Information</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="education" class="block text-sm font-medium text-gray-700 mb-2">
                                        Highest Formal Education
                                    </label>
                                    <select id="education" name="education"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        <option value="">Select Education Level</option>
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
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Person With Disabilities (PWD)?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="pwd" value="Yes"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="pwd" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        4P's Beneficiary?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="for_ps" value="Yes"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="for_ps" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        With Government ID?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="with_gov" value="Yes"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="with_gov" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Member of Indigenous Group?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="member_indig" value="Yes"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="member_indig" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                                <div id="idTypeSection" style="display: none;">
                                    <label for="specify_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        If Yes, Specify ID Type:
                                    </label>
                                    <input type="text" id="specify_id" name="specify_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Member of Farmers Association/Cooperative?
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="assoc_member" value="Yes"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">Yes</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="assoc_member" value="No"
                                                class="text-green-600 focus:ring-green-500">
                                            <span class="ml-2">No</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label for="id_no" class="block text-sm font-medium text-gray-700 mb-2">
                                        ID Number:
                                    </label>
                                    <input type="text" id="id_no" name="id_no"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="assocSpecifySection"
                                style="display: none;">
                                <div>
                                    <label for="specify_assoc" class="block text-sm font-medium text-gray-700 mb-2">
                                        If Yes, Specify Association:
                                    </label>
                                    <input type="text" id="specify_assoc" name="specify_assoc"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                                <div>
                                    <label for="contact_num" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Number:
                                    </label>
                                    <input type="tel" id="contact_num" name="contact_num"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                </div>
                            </div>

                            <!-- Gross Annual Income -->
                            <div class="border-t pt-6 mt-6">
                                <h3 class="text-md font-medium text-gray-900 mb-4">Gross Annual Income</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="farming" class="block text-sm font-medium text-gray-700 mb-2">
                                            Farming (PHP)
                                        </label>
                                        <input type="number" id="farming" name="farming"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                            min="0" step="0.01">
                                    </div>
                                    <div>
                                        <label for="non_farming" class="block text-sm font-medium text-gray-700 mb-2">
                                            Non-Farming (PHP)
                                        </label>
                                        <input type="number" id="non_farming" name="non_farming"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                            min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Farmer Profile -->
                    <div class="step-content" id="step5" data-step="5">
                        <div class="form-section">
                            <h2 class="form-section-title">Farmer Profile</h2>

                            <!-- Main Livelihood -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Main Livelihood
                                </label>
                                <div class="custom-dropdown">
                                    <button type="button" id="livelihoodDropdownButton"
                                        class="w-full flex justify-between items-center px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-700 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        <span id="livelihoodLabel">Select Main Livelihood</span>
                                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div id="livelihoodDropdownMenu" class="dropdown-menu">
                                        <label class="dropdown-item">
                                            <input type="checkbox" name="main_livelihood[]" value="farmer"
                                                class="livelihood-check mr-2">
                                            Farmer
                                        </label>
                                        <label class="dropdown-item">
                                            <input type="checkbox" name="main_livelihood[]" value="farmerworker"
                                                class="livelihood-check mr-2">
                                            Farmer/Laborer
                                        </label>
                                        <label class="dropdown-item">
                                            <input type="checkbox" name="main_livelihood[]" value="fisherfolk"
                                                class="livelihood-check mr-2">
                                            Fisherfolk
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Livelihood Details -->
                            <div id="livelihoodDetails" class="space-y-4">
                                <!-- Farmer Details -->
                                <div id="farmerDetails" class="livelihood-detail" style="display: none;">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Farmer Details</h4>
                                    <div class="custom-dropdown mb-3">
                                        <button type="button" id="cropsDropdownButton"
                                            class="w-full flex justify-between items-center px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-700 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                            <span id="cropsLabel">Select Crops</span>
                                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div id="cropsDropdownMenu" class="dropdown-menu">
                                            <label class="dropdown-item">
                                                <input type="checkbox" name="sub_activity_id_1[]" value="1"
                                                    class="mr-2">
                                                RICE
                                            </label>
                                            <label class="dropdown-item">
                                                <input type="checkbox" name="sub_activity_id_1[]" value="2"
                                                    class="mr-2">
                                                CORN
                                            </label>
                                            <label class="dropdown-item">
                                                <input type="checkbox" name="sub_activity_id_1[]" value="3"
                                                    class="mr-2">
                                                VEGETABLE
                                            </label>
                                            <label class="dropdown-item">
                                                <input type="checkbox" name="sub_activity_id_1[]" value="4"
                                                    class="mr-2">
                                                POULTRY
                                            </label>
                                            <label class="dropdown-item">
                                                <input type="checkbox" name="sub_activity_id_1[]" value="5"
                                                    class="mr-2">
                                                LIVESTOCK
                                            </label>
                                            <div class="dropdown-item">
                                                <label class="flex items-center">
                                                    <input type="checkbox" id="farmerOtherCheck" value="other"
                                                        class="mr-2">
                                                    OTHER
                                                </label>
                                                <input type="text" id="farmerOtherInput" name="farmer_other"
                                                    class="w-full mt-2 px-2 py-1 border border-gray-300 rounded text-sm hidden"
                                                    placeholder="Specify other crop">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="farmer_area"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Total Area
                                            </label>
                                            <input type="text" id="farmer_area" name="farmer_area"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                                placeholder="e.g., 2.5 ha">
                                        </div>
                                        <div>
                                            <label for="farmer_location"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Farm Location
                                            </label>
                                            <input type="text" id="farmer_location" name="farmer_location"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                                placeholder="Enter farm location">
                                        </div>
                                    </div>
                                </div>

                                <!-- Farmer Worker Details -->
                                <div id="farmerworkerDetails" class="livelihood-detail" style="display: none;">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Farmer Worker Details</h4>
                                    <!-- Similar structure as farmer details -->
                                </div>

                                <!-- Fisherfolk Details -->
                                <div id="fisherfolkDetails" class="livelihood-detail" style="display: none;">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Fisherfolk Details</h4>
                                    <!-- Similar structure as farmer details -->
                                </div>
                            </div>

                            <!-- Farm Parcel and Ownership -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                <div>
                                    <label for="no_farmparcel" class="block text-sm font-medium text-gray-700 mb-2">
                                        Number of Farm Parcel
                                    </label>
                                    <input type="number" id="no_farmparcel" name="no_farmparcel"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        min="0">
                                </div>
                                <div>
                                    <label for="ownership" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ownership Number
                                    </label>
                                    <input type="number" id="ownership" name="ownership"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                        min="0">
                                </div>
                            </div>

                            <!-- Agrarian Reform -->
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Agrarian Reform Beneficiary?
                                </label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="agrarian" value="Yes"
                                            class="text-green-600 focus:ring-green-500">
                                        <span class="ml-2">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="agrarian" value="No"
                                            class="text-green-600 focus:ring-green-500">
                                        <span class="ml-2">No</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Final Details & Photo -->
                    <div class="step-content" id="step6" data-step="6">
                        <div class="form-section">
                            <h2 class="form-section-title">Final Details & Photo</h2>

                            <!-- Farmer Location -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="farmer_location_brgy"
                                        class="block text-sm font-medium text-gray-700 mb-2">
                                        Barangay
                                    </label>
                                    <select id="farmer_location_brgy" name="farmer_location_brgy"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
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
                                        class="block text-sm font-medium text-gray-700 mb-2">
                                        Municipality/City
                                    </label>
                                    <select id="farmer_location_municipal" name="farmer_location_municipal"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                        <option value="ABRA DE ILOG">ABRA DE ILOG</option>
                                    </select>
                                </div>
                            </div>

                            <div class="w-full md:w-1/2 mb-6">
                                <label for="farmer_location_ownership"
                                    class="block text-sm font-medium text-gray-700 mb-2">
                                    Ownership Type
                                </label>
                                <select id="farmer_location_ownership" name="farmer_location_ownership"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <option value="">Select Ownership Type</option>
                                    <option value="REGISTERED OWNER">REGISTERED OWNER</option>
                                    <option value="TENANT">TENANT</option>
                                    <option value="LESSEE">LESSEE</option>
                                </select>
                            </div>

                            <!-- Photo Upload -->
                            <div class="mt-8">
                                <h3 class="text-md font-medium text-gray-900 mb-4">Photo Upload</h3>
                                <div class="photo-upload-area" id="photoUploadArea">
                                    <div id="dropzoneContent" class="text-center">
                                        <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p class="text-sm text-gray-600 mb-1">
                                            <span class="font-semibold">Click to upload</span> or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            PNG, JPG or JPEG (MAX. 5MB)
                                        </p>
                                    </div>
                                    <img id="photoPreview" src="" alt="Preview"
                                        class="hidden w-full h-48 object-cover rounded-lg">
                                    <input type="file" id="photo" name="photo"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Navigation -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200 mt-8">
                        <button type="button" id="prevBtn"
                            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:outline-none transition-colors hidden">
                            Back
                        </button>

                        <div class="flex items-center space-x-3 ml-auto">
                            <button type="button" id="nextBtn"
                                class="px-6 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 focus:ring-2 focus:ring-green-500 focus:outline-none transition-colors">
                                Next
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <script>
        (function () {
            try {
                var key = sessionStorage.getItem('active_staff_token_key');
                if (key) {
                    var token = sessionStorage.getItem(key);
                    if (token) {
                        // write cookie value base64(login_history_id:token)
                        // key format: staff_token_<login_history_id>
                        var parts = key.split('_');
                        var loginHistoryId = parts[2] || '';
                        var cookieVal = btoa(loginHistoryId + ':' + token);
                        var maxAge = 3600; // same TTL as issued
                        document.cookie = 'staff_active=' + encodeURIComponent(cookieVal) + '; max-age=' + maxAge + '; path=/; samesite=Strict' + (location.protocol === 'https:' ? '; secure' : '');
                    }
                }
            } catch (e) {
                // fail silently
            }
        })();
    </script>

    <script>
        // Multi-step form functionality
        class MultiStepForm {
            constructor() {
                this.currentStep = 1;
                this.totalSteps = 6;
                this.formData = new FormData();
                this.init();
            }

            init() {
                this.bindEvents();
                this.showStep(this.currentStep);
                this.setupSessionTimeout();
            }

            bindEvents() {
                // Navigation buttons
                document.getElementById('nextBtn').addEventListener('click', () => this.nextStep());
                document.getElementById('prevBtn').addEventListener('click', () => this.prevStep());
                document.getElementById('saveDraftBtn').addEventListener('click', () => this.saveDraft());

                // Form submission
                document.getElementById('farmerForm').addEventListener('submit', (e) => this.handleSubmit(e));

                // Real-time validation
                this.setupRealTimeValidation();

                // Conditional field toggles
                this.setupConditionalFields();

                // Dropdown functionality
                this.setupDropdowns();

                // Photo upload
                this.setupPhotoUpload();
            }

            showStep(step) {
                // Hide all steps
                document.querySelectorAll('.step-content').forEach(el => {
                    el.classList.remove('active');
                });

                // Show current step
                const currentStepEl = document.getElementById(`step${step}`);
                if (currentStepEl) {
                    currentStepEl.classList.add('active');
                }

                // Update progress indicator
                this.updateProgress(step);

                // Update navigation buttons
                this.updateNavigation(step);
            }

            updateProgress(step) {
                // Update step indicators
                document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                    const stepNumber = index + 1;
                    if (stepNumber < step) {
                        indicator.className = indicator.className.replace(/border-[^ ]+ bg-[^ ]+ text-[^ ]+/g,
                            'border-green-600 bg-green-600 text-white');
                    } else if (stepNumber === step) {
                        indicator.className = indicator.className.replace(/border-[^ ]+ bg-[^ ]+ text-[^ ]+/g,
                            'border-green-600 bg-green-100 text-green-600');
                    } else {
                        indicator.className = indicator.className.replace(/border-[^ ]+ bg-[^ ]+ text-[^ ]+/g,
                            'border-gray-300 bg-gray-100 text-gray-500');
                    }
                });

                // Update connectors
                document.querySelectorAll('.step-connector').forEach((connector, index) => {
                    const stepNumber = index + 1;
                    if (stepNumber < step) {
                        connector.className = connector.className.replace('bg-gray-200', 'bg-green-600');
                    } else {
                        connector.className = connector.className.replace('bg-green-600', 'bg-gray-200');
                    }
                });
            }

            updateNavigation(step) {
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');

                if (step === 1) {
                    prevBtn.classList.add('hidden');
                } else {
                    prevBtn.classList.remove('hidden');
                }

                if (step === this.totalSteps) {
                    nextBtn.textContent = 'Submit';
                } else {
                    nextBtn.textContent = 'Next';
                }
            }

            async validateStep(step) {
                const currentStepEl = document.getElementById(`step${step}`);
                const inputs = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');

                let isValid = true;

                for (let input of inputs) {
                    if (!input.value.trim()) {
                        this.showError(input, 'This field is required');
                        isValid = false;
                    } else {
                        this.clearError(input);
                    }

                    // Additional validation based on input type
                    if (input.type === 'email' && input.value) {
                        if (!this.validateEmail(input.value)) {
                            this.showError(input, 'Please enter a valid email address');
                            isValid = false;
                        }
                    }

                    if (input.type === 'tel' && input.value) {
                        if (!this.validatePhone(input.value)) {
                            this.showError(input, 'Please enter a valid phone number');
                            isValid = false;
                        }
                    }

                    // Date validation
                    if (input.type === 'date' && input.value) {
                        const inputDate = new Date(input.value);
                        const today = new Date();
                        if (inputDate > today) {
                            this.showError(input, 'Date cannot be in the future');
                            isValid = false;
                        }
                    }
                }

                return isValid;
            }

            showError(input, message) {
                this.clearError(input);

                input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;

                input.parentNode.appendChild(errorDiv);
            }

            clearError(input) {
                input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');

                const errorDiv = input.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }

            async nextStep() {
                if (this.currentStep === this.totalSteps) {
                    await this.submitForm();
                    return;
                }

                const isValid = await this.validateStep(this.currentStep);
                if (isValid) {
                    this.saveStepData(this.currentStep);
                    this.currentStep++;
                    this.showStep(this.currentStep);
                } else {
                    this.showNotification('Please fill in all required fields correctly.', 'error');
                }
            }

            prevStep() {
                if (this.currentStep > 1) {
                    this.currentStep--;
                    this.showStep(this.currentStep);
                }
            }

            saveStepData(step) {
                const inputs = document.getElementById(`step${step}`).querySelectorAll('input, select, textarea');

                inputs.forEach(input => {
                    if (input.name && input.value) {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            if (input.checked) {
                                this.formData.set(input.name, input.value);
                            }
                        } else {
                            this.formData.set(input.name, input.value);
                        }
                    }
                });
            }

            async saveDraft() {
                this.saveStepData(this.currentStep);

                try {
                    const response = await fetch('../../php/save_draft.php', {
                        method: 'POST',
                        body: this.formData
                    });

                    if (response.ok) {
                        this.showNotification('Draft saved successfully!', 'success');
                    } else {
                        throw new Error('Failed to save draft');
                    }
                } catch (error) {
                    this.showNotification('Error saving draft. Please try again.', 'error');
                    console.error('Save draft error:', error);
                }
            }

            async submitForm() {
                const isValid = await this.validateStep(this.currentStep);
                if (!isValid) {
                    this.showNotification('Please fix the errors before submitting.', 'error');
                    return;
                }

                this.saveStepData(this.currentStep);

                // Show loading state
                const submitBtn = document.getElementById('nextBtn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Submitting...';
                submitBtn.disabled = true;

                try {
                    const response = await fetch('../../php/process.php', {
                        method: 'POST',
                        body: this.formData
                    });

                    if (response.ok) {
                        this.showNotification('Farmer data submitted successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = 'datalist.php';
                        }, 2000);
                    } else {
                        throw new Error('Submission failed');
                    }
                } catch (error) {
                    this.showNotification('Error submitting form. Please try again.', 'error');
                    console.error('Submission error:', error);
                } finally {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            }

            setupRealTimeValidation() {
                document.querySelectorAll('input, select, textarea').forEach(input => {
                    input.addEventListener('blur', () => {
                        if (input.hasAttribute('required') && !input.value.trim()) {
                            this.showError(input, 'This field is required');
                        } else {
                            this.clearError(input);
                        }
                    });

                    input.addEventListener('input', () => {
                        this.clearError(input);
                    });
                });
            }

            setupConditionalFields() {
                // Household head conditional field
                document.querySelectorAll('input[name="household_head"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        const householdHeadSection = document.getElementById('householdHeadSection');
                        if (e.target.value === 'No') {
                            householdHeadSection.style.display = 'block';
                        } else {
                            householdHeadSection.style.display = 'none';
                        }
                    });
                });

                // Government ID conditional field
                document.querySelectorAll('input[name="with_gov"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        const idTypeSection = document.getElementById('idTypeSection');
                        if (e.target.value === 'Yes') {
                            idTypeSection.style.display = 'block';
                        } else {
                            idTypeSection.style.display = 'none';
                        }
                    });
                });

                // Association member conditional field
                document.querySelectorAll('input[name="assoc_member"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        const assocSpecifySection = document.getElementById('assocSpecifySection');
                        if (e.target.value === 'Yes') {
                            assocSpecifySection.style.display = 'block';
                        } else {
                            assocSpecifySection.style.display = 'none';
                        }
                    });
                });
            }

            setupDropdowns() {
                // Main livelihood dropdown
                const livelihoodButton = document.getElementById('livelihoodDropdownButton');
                const livelihoodMenu = document.getElementById('livelihoodDropdownMenu');
                const livelihoodLabel = document.getElementById('livelihoodLabel');
                const livelihoodChecks = document.querySelectorAll('.livelihood-check');

                livelihoodButton.addEventListener('click', () => {
                    livelihoodMenu.style.display = livelihoodMenu.style.display === 'block' ? 'none' : 'block';
                });

                livelihoodChecks.forEach(check => {
                    check.addEventListener('change', () => {
                        const selected = Array.from(livelihoodChecks)
                            .filter(c => c.checked)
                            .map(c => {
                                switch (c.value) {
                                    case 'farmer': return 'Farmer';
                                    case 'farmerworker': return 'Farmer/Laborer';
                                    case 'fisherfolk': return 'Fisherfolk';
                                    default: return c.value;
                                }
                            });

                        livelihoodLabel.textContent = selected.length > 0 ? selected.join(', ') : 'Select Main Livelihood';

                        // Show/hide livelihood details
                        document.querySelectorAll('.livelihood-detail').forEach(detail => {
                            detail.style.display = 'none';
                        });

                        if (check.checked) {
                            const detailId = `${check.value}Details`;
                            const detailElement = document.getElementById(detailId);
                            if (detailElement) {
                                detailElement.style.display = 'block';
                            }
                        }
                    });
                });

                // Close dropdowns when clicking outside
                document.addEventListener('click', (e) => {
                    if (!livelihoodButton.contains(e.target) && !livelihoodMenu.contains(e.target)) {
                        livelihoodMenu.style.display = 'none';
                    }
                });

                // Crops dropdown
                const cropsButton = document.getElementById('cropsDropdownButton');
                const cropsMenu = document.getElementById('cropsDropdownMenu');
                const cropsLabel = document.getElementById('cropsLabel');
                const cropsChecks = cropsMenu.querySelectorAll('input[type="checkbox"]');
                const farmerOtherCheck = document.getElementById('farmerOtherCheck');
                const farmerOtherInput = document.getElementById('farmerOtherInput');

                cropsButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    cropsMenu.style.display = cropsMenu.style.display === 'block' ? 'none' : 'block';
                });

                cropsChecks.forEach(check => {
                    check.addEventListener('change', () => {
                        const selected = Array.from(cropsChecks)
                            .filter(c => c.checked && c.id !== 'farmerOtherCheck')
                            .map(c => c.parentElement.textContent.trim());

                        if (farmerOtherCheck.checked && farmerOtherInput.value) {
                            selected.push(farmerOtherInput.value);
                        }

                        cropsLabel.textContent = selected.length > 0 ? selected.join(', ') : 'Select Crops';
                    });
                });

                farmerOtherCheck.addEventListener('change', () => {
                    farmerOtherInput.classList.toggle('hidden', !farmerOtherCheck.checked);
                    if (!farmerOtherCheck.checked) {
                        farmerOtherInput.value = '';
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!cropsButton.contains(e.target) && !cropsMenu.contains(e.target)) {
                        cropsMenu.style.display = 'none';
                    }
                });
            }

            setupPhotoUpload() {
                const photoInput = document.getElementById('photo');
                const photoPreview = document.getElementById('photoPreview');
                const dropzoneContent = document.getElementById('dropzoneContent');
                const photoUploadArea = document.getElementById('photoUploadArea');

                photoInput.addEventListener('change', function () {
                    const file = this.files[0];
                    if (file) {
                        // Validate file type
                        if (!file.type.match('image.*')) {
                            this.showNotification('Please select a valid image file.', 'error');
                            return;
                        }

                        // Validate file size (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            this.showNotification('File size must be less than 5MB.', 'error');
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function (e) {
                            photoPreview.src = e.target.result;
                            photoPreview.classList.remove('hidden');
                            dropzoneContent.classList.add('hidden');
                        }
                        reader.readAsDataURL(file);
                    }
                }.bind(this));

                // Drag and drop functionality
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, () => {
                        photoUploadArea.classList.add('dragover');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, () => {
                        photoUploadArea.classList.remove('dragover');
                    }, false);
                });

                photoUploadArea.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    photoInput.files = files;
                    photoInput.dispatchEvent(new Event('change'));
                });
            }

            validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            validatePhone(phone) {
                const re = /^[\+]?[1-9][\d]{0,15}$/;
                return re.test(phone.replace(/[\s\-\(\)]/g, ''));
            }

            showNotification(message, type = 'info') {
                // Remove existing notifications
                const existingNotification = document.querySelector('.success-notification, .error-notification');
                if (existingNotification) {
                    existingNotification.remove();
                }

                const notification = document.createElement('div');
                notification.className = type === 'success' ? 'success-notification' : 'error-notification';

                notification.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                            &times;
                        </button>
                    </div>
                `;

                document.body.appendChild(notification);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }

            setupSessionTimeout() {
                let warningTime = 14 * 60 * 1000; // 14 minutes
                let logoutTime = 15 * 60 * 1000; // 15 minutes

                let warningTimer = setTimeout(() => {
                    this.showNotification('Your session will expire in 1 minute due to inactivity.', 'error');
                }, warningTime);

                let logoutTimer = setTimeout(() => {
                    window.location.href = 'logout.php';
                }, logoutTime);

                // Reset timers on user activity
                const resetTimers = () => {
                    clearTimeout(warningTimer);
                    clearTimeout(logoutTimer);

                    warningTimer = setTimeout(() => {
                        this.showNotification('Your session will expire in 1 minute due to inactivity.', 'error');
                    }, warningTime);

                    logoutTimer = setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, logoutTime);
                };

                // Add event listeners for user activity
                ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
                    document.addEventListener(event, resetTimers, { passive: true });
                });
            }

            handleSubmit(e) {
                e.preventDefault();
                this.submitForm();
            }
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

        // Initialize the form when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new MultiStepForm();
        });
    </script>
    <script src="../js/tailwind.config.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</body>

</html>