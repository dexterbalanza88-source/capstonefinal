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

// Get farmer ID from query string
if (!isset($_GET['id'])) {
    die("Farmer ID not provided.");
}
$farmer_id = intval($_GET['id']);

// Fetch farmer data
$stmt = $conn->prepare("
    SELECT 
        f.*,
        -- Farmer commodities (category_id = 1)
        GROUP_CONCAT(DISTINCT CASE WHEN sa.category_id = 1 THEN sa.sub_name END SEPARATOR ', ') AS type_commodity,
        -- Farmer Worker commodities (category_id = 2)
        GROUP_CONCAT(DISTINCT CASE WHEN sa.category_id = 2 THEN sa.sub_name END SEPARATOR ', ') AS for_farmerworker,
        -- Fisherfolk commodities (category_id = 3)
        GROUP_CONCAT(DISTINCT CASE WHEN sa.category_id = 3 THEN sa.sub_name END SEPARATOR ', ') AS for_fisherfolk
    FROM registration_form f
    LEFT JOIN livelihoods l ON f.id = l.registration_form_id
    LEFT JOIN sub_activities sa ON l.sub_activity_id = sa.id
    WHERE f.id = ?
    GROUP BY f.id
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$farmer = $result->fetch_assoc();

if (!$farmer) {
    die("Farmer not found.");
}

$farmer_id = $farmer['id'] ?? null;
$commodities = [];
$total_farm_area = 0;
$total_farm_parcel = 0;
$farm_barangays = '';

if (!empty($farmer_id)) {
    // ✅ Fetch all livelihood & commodity records
    $query = "
    SELECT 
        l.id,  -- ✅ unique identifier
        l.category_id,
        l.registration_form_id,
        CASE l.category_id
            WHEN 1 THEN 'Farmer'
            WHEN 2 THEN 'Farmer Worker'
            WHEN 3 THEN 'Fisherfolk'
            ELSE 'Unknown'
        END AS livelihood,
        sa.sub_name AS commodity,
        l.remarks,
        l.total_area,
        l.farm_location
    FROM livelihoods AS l
    LEFT JOIN sub_activities AS sa ON l.sub_activity_id = sa.id
    WHERE l.registration_form_id = ?
    ORDER BY l.created_at DESC
";


    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $unique_parcels = [];
    $barangays = [];

    while ($row = $result->fetch_assoc()) {
        $commodities[] = $row;

        $cat = (int) $row['category_id'];
        $area = (float) $row['total_area'];
        $brgy_raw = $row['farm_location'];

        // ✅ Normalize barangay name deeply
        if (!empty($brgy_raw)) {
            $normalized = strtolower($brgy_raw);
            // remove extra spaces, tabs, newlines, commas, invisible unicode spaces
            $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized); // remove punctuation
            $normalized = preg_replace('/\s+/u', ' ', $normalized);            // normalize spaces
            $normalized = trim($normalized);                                  // trim final string

            if ($normalized !== '') {
                $barangays[$normalized] = true;
            }
        }

        // ✅ Unique parcel tracking
        if ($area > 0) {
            $parcel_key = "{$cat}_{$area}";
            if (!isset($unique_parcels[$parcel_key])) {
                $unique_parcels[$parcel_key] = $area;
            }
        }
    }

    $stmt->close();

    // ✅ Compute totals
    $total_farm_area = array_sum($unique_parcels);
    $total_farm_parcel = count($unique_parcels);

    // ✅ Barangay count only
    $unique_barangay_count = count($barangays);
    if ($unique_barangay_count > 0) {
        $farm_barangays = "{$unique_barangay_count} barangay" . ($unique_barangay_count > 1 ? "s" : "");
    } else {
        $farm_barangays = "No barangay listed";
    }
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
</style>
<style>
    .form-input {
        @apply bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white;
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
                <div class="flex items-center space-x-3">
                    <button id="user-menu-button" data-dropdown-toggle="dropdown"
                        class="flex items-center text-sm bg-white rounded-full focus:ring-2 focus:ring-[#E6B800]">
                        <img class="w-9 h-9 rounded-full" src="../../img/profile.png" alt="User photo">
                    </button>

                    <!-- Dropdown -->
                    <div id="dropdown"
                        class="hidden z-50 my-4 w-56 text-base list-none bg-white divide-y divide-gray-100 shadow rounded-xl">
                        <div class="py-3 px-4 text-center">
                            <span class="block text-sm font-semibold text-gray-900">
                                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                            </span>
                            <span class="block text-sm text-gray-500 truncate">
                                <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700" aria-labelledby="dropdown">
                            <li>
                                <a href="profile.html" class="block py-2 px-4 text-sm hover:bg-gray-100">My profile</a>
                            </li>
                            <li>
                                <a href="../login.html" class="block py-2 px-4 text-sm hover:bg-gray-100">Sign out</a>
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
                        <a href="datalist.php"
                            class="flex items-center p-2 text-base font-medium text-[#166534]  rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Data List</span>
                        </a>
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
        <main class="md:ml-64 pt-20 p-2 width-full">

            <div class="bg-gray-50 p-6 rounded-lg ">
                <div class="overflow-y-auto h-130">
                    <!-- Header -->
                    <div class="border-b border-green-600 mb-6 flex justify-between items-center">
                        <h1 class="text-3xl font-bold text-green-700 flex items-center gap-2">
                            👨‍🌾 Farmer Profile
                        </h1>
                        <a href="datalist.php"
                            class="inline-block bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow transition">
                            ← Back to Farmer List
                        </a>
                    </div>

                    <!-- Edit Button in Personal Information Section -->
                    <section class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Personal Information</h2>
                            <div>
                                <button class="edit-btn flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 
                                    bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 
                                    focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-900/30 
                                    dark:hover:bg-blue-900/50 dark:text-blue-300" data-id="<?= $farmer['id'] ?>"
                                    data-section="personal" data-modal-target="edit-personal-modal"
                                    data-modal-toggle="edit-personal-modal" type="button">
                                    ✏️ Edit
                                </button>

                            </div>
                        </div>

                        <div id="personal" class="grid grid-cols-2 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Date</p>
                                <p><span data-field="date"><?= htmlspecialchars($farmer['date']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Reference</p>
                                <p><span data-field="reference"><?= htmlspecialchars($farmer['reference']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Surname</p>
                                <p><span data-field="s_name"><?= htmlspecialchars($farmer['s_name']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">First Name</p>
                                <p><span data-field="f_name"><?= htmlspecialchars($farmer['f_name']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Middle Name</p>
                                <p><span data-field="m_name"><?= htmlspecialchars($farmer['m_name']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Extension</p>
                                <p><span data-field="e_name"><?= htmlspecialchars($farmer['e_name']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Gender</p>
                                <p><span data-field="gender"><?= htmlspecialchars($farmer['gender']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">House No.</p>
                                <p><span data-field="house"><?= htmlspecialchars($farmer['house']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Sitio</p>
                                <p><span data-field="sitio"><?= htmlspecialchars($farmer['sitio']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Barangay</p>
                                <p><span data-field="brgy"><?= htmlspecialchars($farmer['brgy']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Municipality</p>
                                <p><span data-field="municipal"><?= htmlspecialchars($farmer['municipal']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Province</p>
                                <p><span data-field="province"><?= htmlspecialchars($farmer['province']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Region</p>
                                <p><span data-field="region"><?= htmlspecialchars($farmer['region']) ?></span></p>
                            </div>
                        </div>
                    </section>

                    <!-- Edit Personal Information Modal -->
                    <div id="edit-personal-modal" tabindex="-1" aria-hidden="true"
                        class="hidden overflow-y-auto overflow-x-hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div class="relative p-4 w-full max-w-2xl max-h-full">
                            <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                                <!-- Header -->
                                <div
                                    class="flex items-center justify-between p-4 md:p-5 border-b border-gray-200 dark:border-gray-600 rounded-t">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                        Edit Personal Information
                                    </h3>
                                    <button type="button"
                                        class="end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                        data-modal-hide="edit-personal-modal">
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 14 14">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                                        </svg>
                                        <span class="sr-only">Close modal</span>
                                    </button>
                                </div>

                                <!-- Body -->
                                <form class="p-4 md:p-5 space-y-4" id="editPersonalForm">
                                    <!-- Date & Reference -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="date"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Date</label>
                                            <input type="date" name="date" id="date"
                                                value="<?= htmlspecialchars($farmer['date']) ?>"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="reference"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Reference</label>
                                            <input type="text" name="reference" id="reference"
                                                value="<?= htmlspecialchars($farmer['reference']) ?>"
                                                placeholder="Reference No."
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                    </div>

                                    <!-- Name Fields -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label for="s_name"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Surname</label>
                                            <input type="text" name="s_name" id="s_name"
                                                value="<?= htmlspecialchars($farmer['s_name']) ?>" placeholder="Surname"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="f_name"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">First
                                                Name</label>
                                            <input type="text" name="f_name" id="f_name"
                                                value="<?= htmlspecialchars($farmer['f_name']) ?>"
                                                placeholder="First Name"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="m_name"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Middle
                                                Name</label>
                                            <input type="text" name="m_name" id="m_name"
                                                value="<?= htmlspecialchars($farmer['m_name']) ?>"
                                                placeholder="Middle Name"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                    </div>

                                    <!-- Extension, Gender, House -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label for="e_name"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Extension</label>
                                            <input type="text" name="e_name" id="e_name"
                                                value="<?= htmlspecialchars($farmer['e_name']) ?>"
                                                placeholder="Extension"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="gender"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Gender</label>
                                            <select name="gender" id="gender"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                                <option <?= $farmer['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                                <option <?= $farmer['gender'] == 'Female' ? 'selected' : '' ?>>Female
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="house"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">House
                                                No.</label>
                                            <input type="text" name="house" id="house"
                                                value="<?= htmlspecialchars($farmer['house']) ?>"
                                                placeholder="House Number"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label for="sitio"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Sitio</label>
                                            <input type="text" name="sitio" id="sitio"
                                                value="<?= htmlspecialchars($farmer['sitio']) ?>" placeholder="Sitio"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="brgy"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Barangay</label>
                                            <input type="text" name="brgy" id="brgy"
                                                value="<?= htmlspecialchars($farmer['brgy']) ?>" placeholder="Barangay"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="municipal"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Municipality</label>
                                            <input type="text" name="municipal" id="municipal"
                                                value="<?= htmlspecialchars($farmer['municipal']) ?>"
                                                placeholder="Municipality"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                    </div>

                                    <!-- Province / Region -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="province"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Province</label>
                                            <input type="text" name="province" id="province"
                                                value="<?= htmlspecialchars($farmer['province']) ?>"
                                                placeholder="Province"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                        <div>
                                            <label for="region"
                                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Region</label>
                                            <input type="text" name="region" id="region"
                                                value="<?= htmlspecialchars($farmer['region']) ?>" placeholder="Region"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" />
                                        </div>
                                    </div>

                                    <!-- Buttons -->
                                    <div
                                        class="flex justify-end space-x-2 pt-5 border-t border-gray-200 dark:border-gray-600">
                                        <!-- Cancel Button -->
                                        <button type="button" data-modal-hide="edit-personal-modal" class="px-5 py-2.5 text-sm font-medium rounded-lg 
               text-gray-700 bg-gray-100 hover:bg-gray-200 
               focus:outline-none focus:ring-2 focus:ring-gray-300
               transition-all duration-200 
               dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus:ring-gray-500">
                                            Cancel
                                        </button>

                                        <!-- Save Changes Button -->
                                        <button type="submit" class="px-5 py-2.5 text-sm font-medium rounded-lg 
               text-white bg-blue-700 hover:bg-blue-800 
               focus:outline-none focus:ring-4 focus:ring-blue-300 
               transition-all duration-200 
               dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                            💾 Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Family & Contact -->
                    <section class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Family & Contact Details</h2>
                            <button class="edit-btn flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 
           bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 
           focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-900/30 
           dark:hover:bg-blue-900/50 dark:text-blue-300" data-id="<?= $farmer['id'] ?>" data-section="family"
                                data-modal-target="edit-family-modal" data-modal-toggle="edit-family-modal"
                                type="button">
                                ✏️ Edit
                            </button>
                        </div>

                        <div id="family" class="grid grid-cols-2 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Mobile Number</p>
                                <p><span data-field="mobile"><?= htmlspecialchars($farmer['mobile']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Landline</p>
                                <p><span data-field="landline"><?= htmlspecialchars($farmer['landline']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Date of Birth</p>
                                <p><span data-field="dob"><?= htmlspecialchars($farmer['dob']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Mother's Maiden Name</p>
                                <p><span
                                        data-field="mother_maiden"><?= htmlspecialchars($farmer['mother_maiden']) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">No. Household Members</p>
                                <p><span
                                        data-field="no_livinghousehold"><?= htmlspecialchars($farmer['no_livinghousehold']) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">No. Male</p>
                                <p><span data-field="no_male"><?= htmlspecialchars($farmer['no_male']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">No. Female</p>
                                <p><span data-field="no_female"><?= htmlspecialchars($farmer['no_female']) ?></span></p>
                            </div>
                        </div>
                    </section>
                    <!-- Edit Family & Contact Modal -->
                    <div id="edit-family-modal" tabindex="-1" aria-hidden="true"
                        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div class="relative w-full max-w-2xl bg-white rounded-lg shadow dark:bg-gray-700">
                            <!-- Header -->
                            <div class="flex items-center justify-between p-4 border-b dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Edit Family & Contact Details
                                </h3>
                                <button type="button" data-modal-hide="edit-family-modal"
                                    class="text-gray-400 hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-2 dark:hover:bg-gray-600 dark:hover:text-white">
                                    ✖
                                </button>
                            </div>

                            <!-- Body -->
                            <form id="editFamilyForm" class="p-4 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="mobile"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Mobile
                                            Number</label>
                                        <input type="text" id="mobile" name="mobile" placeholder="09XXXXXXXXX"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                    <div>
                                        <label for="landline"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Landline</label>
                                        <input type="text" id="landline" name="landline" placeholder="Landline Number"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="dob"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Date of
                                            Birth</label>
                                        <input type="date" id="dob" name="dob"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                    <div>
                                        <label for="mother_maiden"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Mother's
                                            Maiden Name</label>
                                        <input type="text" id="mother_maiden" name="mother_maiden"
                                            placeholder="Mother's Maiden Name"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label for="no_livinghousehold"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">No.
                                            Household Members</label>
                                        <input type="number" id="no_livinghousehold" name="no_livinghousehold"
                                            placeholder="0"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                    <div>
                                        <label for="no_male"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">No.
                                            Male</label>
                                        <input type="number" id="no_male" name="no_male" placeholder="0"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                    <div>
                                        <label for="no_female"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">No.
                                            Female</label>
                                        <input type="number" id="no_female" name="no_female" placeholder="0"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white" />
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div
                                    class="flex justify-end space-x-2 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <button type="button" data-modal-hide="edit-family-modal"
                                        class="px-5 py-2.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700">
                                        💾 Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Education & ID -->
                    <section class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Education & ID</h2>
                            <button class="edit-btn flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 
                                bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 
                                focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-900/30 
                                dark:hover:bg-blue-900/50 dark:text-blue-300" data-id="<?= $farmer['id'] ?>"
                                data-section="education" data-modal-target="edit-education-modal"
                                data-modal-toggle="edit-education-modal" type="button">
                                ✏️ Edit
                            </button>
                        </div>

                        <div id="education" class="grid grid-cols-2 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Education</p>
                                <p><span data-field="education"><?= htmlspecialchars($farmer['education']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">PWD</p>
                                <p><span data-field="pwd"><?= htmlspecialchars($farmer['pwd']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">4P's</p>
                                <p><span data-field="for_ps"><?= htmlspecialchars($farmer['for_ps']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">With Gov ID</p>
                                <p><span data-field="with_gov"><?= htmlspecialchars($farmer['with_gov']) ?></span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">ID Type</p>
                                <p><span data-field="specify_id"><?= htmlspecialchars($farmer['specify_id']) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">ID Number</p>
                                <p><span data-field="id_no"><?= htmlspecialchars($farmer['id_no']) ?></span></p>
                            </div>
                        </div>
                    </section>
                    <!-- Edit Education & ID Modal -->
                    <div id="edit-education-modal" tabindex="-1"
                        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl p-6">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Edit Education & ID
                            </h2>

                            <form id="editEducationForm" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Highest Formal Education -->
                                    <div>
                                        <label for="education"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            Highest Formal Education
                                        </label>
                                        <select id="education" name="education" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="PRE-SCHOOL" <?= $farmer['education'] == 'PRE-SCHOOL' ? 'selected' : '' ?>>PRE-SCHOOL</option>
                                            <option value="ELEMENTARY" <?= $farmer['education'] == 'ELEMENTARY' ? 'selected' : '' ?>>ELEMENTARY</option>
                                            <option value="HIGH SCHOOL (NON-K-12)" <?= $farmer['education'] == 'HIGH SCHOOL (NON-K-12)' ? 'selected' : '' ?>>HIGH SCHOOL (NON-K-12)</option>
                                            <option value="JUNIOR SCHOOL (K-12)" <?= $farmer['education'] == 'JUNIOR SCHOOL (K-12)' ? 'selected' : '' ?>>JUNIOR SCHOOL (K-12)</option>
                                            <option value="COLLEGE" <?= $farmer['education'] == 'COLLEGE' ? 'selected' : '' ?>>COLLEGE</option>
                                            <option value="VOCATIONAL" <?= $farmer['education'] == 'VOCATIONAL' ? 'selected' : '' ?>>VOCATIONAL</option>
                                            <option value="POST-GRADUATE" <?= $farmer['education'] == 'POST-GRADUATE' ? 'selected' : '' ?>>POST-GRADUATE</option>
                                            <option value="NONE" <?= $farmer['education'] == 'NONE' ? 'selected' : '' ?>>
                                                NONE</option>
                                        </select>
                                    </div>

                                    <!-- PWD -->
                                    <div>
                                        <label for="pwd"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            PWD
                                        </label>
                                        <select id="pwd" name="pwd" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select</option>
                                            <option value="Yes" <?= $farmer['pwd'] == 'Yes' ? 'selected' : '' ?>>Yes
                                            </option>
                                            <option value="No" <?= $farmer['pwd'] == 'No' ? 'selected' : '' ?>>No</option>
                                        </select>
                                    </div>

                                    <!-- 4P's Member -->
                                    <div>
                                        <label for="for_ps"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            4P's Member
                                        </label>
                                        <select id="for_ps" name="for_ps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select</option>
                                            <option value="Yes" <?= $farmer['for_ps'] == 'Yes' ? 'selected' : '' ?>>Yes
                                            </option>
                                            <option value="No" <?= $farmer['for_ps'] == 'No' ? 'selected' : '' ?>>No
                                            </option>
                                        </select>
                                    </div>

                                    <!-- With Government ID -->
                                    <div>
                                        <label for="with_gov"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            With Government ID
                                        </label>
                                        <select id="with_gov" name="with_gov" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select</option>
                                            <option value="Yes" <?= $farmer['with_gov'] == 'Yes' ? 'selected' : '' ?>>Yes
                                            </option>
                                            <option value="No" <?= $farmer['with_gov'] == 'No' ? 'selected' : '' ?>>No
                                            </option>
                                        </select>
                                    </div>

                                    <!-- ID Type -->
                                    <div>
                                        <label for="specify_id"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            ID Type
                                        </label>
                                        <input type="text" id="specify_id" name="specify_id"
                                            value="<?= htmlspecialchars($farmer['specify_id'] ?? '') ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="e.g., PhilHealth, SSS, UMID" />
                                    </div>

                                    <!-- ID Number -->
                                    <div>
                                        <label for="id_no"
                                            class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-200">
                                            ID Number
                                        </label>
                                        <input type="text" id="id_no" name="id_no"
                                            value="<?= htmlspecialchars($farmer['id_no'] ?? '') ?>" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                        focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                        dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Enter ID number" />
                                    </div>
                                </div>

                                <div
                                    class="flex justify-end space-x-2 pt-5 border-t border-gray-200 dark:border-gray-600">
                                    <button type="button" data-modal-hide="edit-education-modal" class="px-5 py-2.5 text-sm font-medium rounded-lg 
                    text-gray-700 bg-gray-100 hover:bg-gray-200 
                    focus:outline-none focus:ring-2 focus:ring-gray-300
                    transition-all duration-200 
                    dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus:ring-gray-500">
                                        Cancel
                                    </button>

                                    <button type="submit" class="px-5 py-2.5 text-sm font-medium rounded-lg 
                    text-white bg-blue-700 hover:bg-blue-800 
                    focus:outline-none focus:ring-4 focus:ring-blue-300 
                    transition-all duration-200 
                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        💾 Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Farm Details -->
                    <section class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Farm Location</h2>
                            <button class="edit-btn flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 
                                bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 
                                focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-900/30 
                                dark:hover:bg-blue-900/50 dark:text-blue-300" data-id="<?= $farmer['id'] ?>"
                                data-section="farm" data-modal-target="edit-farm-modal"
                                data-modal-toggle="edit-farm-modal" type="button">
                                ✏️ Edit
                            </button>
                        </div>

                        <div id="farm" class="grid grid-cols-2 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Barangay</p>
                                <p><span data-field="farm_barangay"><?= htmlspecialchars($farm_barangays) ?></span></p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 uppercase">Municipality</p>
                                <p><span
                                        data-field="farm_location_municipal"><?= htmlspecialchars($farmer['farmer_location_municipal']) ?></span>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 uppercase">Farm Size</p>
                                <p><span data-field="farm_size">
                                        <?= htmlspecialchars($total_farm_area) ?> ha
                                    </span></p>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 uppercase">Ownership Type</p>
                                <p><span
                                        data-field="farm_ownership"><?= htmlspecialchars($farmer['farmer_location_ownership']) ?></span>
                                </p>
                            </div>

                            <div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase">Total of Farm Parcel</p>
                                    <p><span data-field="farm_parcel">
                                            <?= htmlspecialchars($total_farm_parcel) ?>
                                        </span></p>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!--Edit Farm Details Modal-->
                    <div id="edit-farm-modal" tabindex="-1" aria-hidden="true"
                        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Farm Details</h3>
                            <form id="editFarmForm" class="space-y-4">
                                <input type="hidden" name="id" id="farm_id">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Barangay</label>
                                        <input type="text" name="farm_barangay" id="farm_barangay"
                                            class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Municipality</label>
                                        <input type="text" name="farm_location_municipal" id="farm_location_municipal"
                                            class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Farm Size (ha)</label>
                                        <input type="number" name="farm_size" id="farm_size" step="0.01"
                                            class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Ownership Type</label>
                                        <input type="text" name="farm_ownership" id="farm_ownership"
                                            class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Total Farm Parcel</label>
                                        <input type="number" name="farm_parcel" id="farm_parcel"
                                            class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
                                    <button type="button" data-modal-hide="edit-farm-modal"
                                        class="px-5 py-2.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-blue-700 hover:bg-blue-800">
                                        💾 Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Livelihood & Commodity -->
                    <section class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Livelihood & Commodity</h2>
                            <div class="space-x-2">
                                <button id="open-livelihood-modal" data-modal-target="default-modal"
                                    data-modal-toggle="default-modal"
                                    data-registration-id="<?= htmlspecialchars($farmer['id']) ?>"
                                    class="bg-green-600 text-white px-3 py-1 rounded text-sm">
                                    ➕ Add Commodity
                                </button>
                            </div>
                        </div>

                        <div id="livelihood" class="space-y-4">
                            <p class="text-sm text-gray-500 font-semibold mb-2">
                                Main Livelihood, Commodity, Area, and Location
                            </p>

                            <div class="bg-gray-50 border rounded p-4">
                                <table class="w-full text-sm text-left text-gray-700">
                                    <thead class="text-xs uppercase text-gray-500 border-b">
                                        <tr>
                                            <th class="py-2 px-3">Livelihood</th>
                                            <th class="py-2 px-3">Commodity</th>
                                            <th class="py-2 px-3">Total Area (ha)</th>
                                            <th class="py-2 px-3">Farm Location</th>
                                            <th class="py-2 px-3 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php if (!empty($commodities)): ?>
                                            <?php foreach ($commodities as $c): ?>
                                                <tr class="hover:bg-gray-100">
                                                    <td class="py-2 px-3 font-medium"><?= htmlspecialchars($c['livelihood']) ?>
                                                    </td>

                                                    <td class="py-2 px-3">
                                                        <?php
                                                        if (strtolower($c['commodity']) === 'others' && !empty($c['remarks'])) {
                                                            echo htmlspecialchars($c['commodity'] . ' - ' . $c['remarks']);
                                                        } else {
                                                            echo htmlspecialchars($c['commodity']);
                                                        }
                                                        ?>
                                                    </td>

                                                    <!-- Show area only for Farmer and Farmer Worker, not for Fisherfolk -->
                                                    <td class="py-2 px-3">
                                                        <?php
                                                        // Check if livelihood is Fisherfolk (you might need to adjust this condition)
                                                        $isFisherfolk = strtolower($c['livelihood']) === 'fisherfolk' ||
                                                            strtolower($c['livelihood']) === 'fisherman' ||
                                                            $c['category_id'] == 3; // Assuming 3 is Fisherfolk category ID
                                                
                                                        if ($isFisherfolk) {
                                                            echo '<span class="text-gray-400">-</span>';
                                                        } else {
                                                            echo htmlspecialchars($c['total_area']);
                                                        }
                                                        ?>
                                                    </td>

                                                    <td class="py-2 px-3"><?= htmlspecialchars($c['farm_location']) ?></td>

                                                    <!-- ✅ Action Buttons -->
                                                    <td class="py-2 px-3 text-center space-x-2">
                                                        <button type="button"
                                                            class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs"
                                                            data-modal-target="edit-modal" data-modal-toggle="edit-modal"
                                                            data-id="<?php echo htmlspecialchars($c['id']); ?>"
                                                            data-registration-id="<?php echo htmlspecialchars($c['registration_form_id']); ?>"
                                                            data-category-id="<?php echo htmlspecialchars($c['category_id']); ?>">
                                                            ✏️ Edit
                                                        </button>
                                                        <button
                                                            onclick="confirmDelete('<?php echo htmlspecialchars($c['id']); ?>')"
                                                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                                                            🗑️ Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="py-3 text-center text-gray-500">
                                                    No commodity records available.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                    <!-- ✅ Edit Commodity Modal -->
                    <div id="edit-modal" tabindex="-1" aria-hidden="true"
                        class="hidden overflow-y-auto overflow-x-hidden fixed inset-0 z-50 flex justify-center items-center w-full h-full bg-black/50">
                        <div class="relative bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                            <button type="button" class="absolute top-3 right-3 text-gray-400 hover:text-gray-900"
                                data-modal-hide="edit-modal">
                                ✖
                            </button>

                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Commodity</h3>

                            <form id="editForm" method="POST" action="update_commodity.php" class="space-y-4">
                                <!-- 🧑‍🌾 Farmer (Registration Form) ID -->
                                <input type="hidden" name="registration_form_id" id="edit_registration_form_id">

                                <!-- 📂 Category ID -->
                                <input type="hidden" name="category_id" id="edit_category_id">

                                <input type="hidden" name="livelihood_id" id="edit_livelihood_id">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Livelihood</label>
                                    <input type="text" name="livelihood" id="edit_livelihood"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2 text-sm"
                                        readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Commodity</label>
                                    <input type="text" name="commodity" id="edit_commodity"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2 text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Remarks</label>
                                    <input type="text" name="remarks" id="edit_remarks"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2 text-sm"
                                        placeholder="Specify if 'Others' was selected">
                                </div>

                                <!-- Total Area Field - Hidden for Fisherfolk -->
                                <div id="total-area-container">
                                    <label class="block text-sm font-medium text-gray-700">Total Area (ha)</label>
                                    <input type="number" step="0.01" min="0" name="total_area" id="edit_total_area"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2 text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        <span id="location-label">Farm Location</span>
                                    </label>
                                    <input type="text" name="farm_location" id="edit_farm_location"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2 text-sm">
                                </div>

                                <div class="flex justify-end space-x-2 pt-4">
                                    <button type="button" data-modal-hide="edit-modal"
                                        class="bg-gray-300 text-gray-800 px-3 py-1 rounded text-sm hover:bg-gray-400">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="bg-green-600 text-white px-4 py-1 rounded text-sm hover:bg-green-700">
                                        💾 Save Changes
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                    <!-- Add Type of Commodity Modal -->
                    <div id="default-modal" tabindex="-1" aria-hidden="true"
                        class="hidden fixed inset-0 z-50 flex justify-center items-center w-full h-full bg-black/40 backdrop-blur-sm">
                        <div class="relative bg-white rounded-lg shadow w-full max-w-md">
                            <!-- HEADER -->
                            <div class="flex items-center justify-between border-b p-4">
                                <h3 class="text-xl font-semibold text-gray-800">Add Type of Commodity</h3>
                                <button type="button" data-modal-hide="default-modal"
                                    class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
                            </div>

                            <div class="p-4 space-y-4">
                                <!-- INSTRUCTIONS -->
                                <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    Select your main livelihood(s), choose or type the corresponding commodity, then
                                    specify area and location.
                                </div>

                                <form id="commodity-form" class="space-y-4">
                                    <input type="hidden" id="registration_form_id" name="registration_form_id" value="">

                                    <!-- FARMER -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <label for="chk_farmer" class="font-semibold text-green-800">
                                                Farmer
                                            </label>
                                            <input id="chk_farmer" type="checkbox" name="category_types[]" value="1"
                                                class="w-4 h-4 text-green-600 border-gray-300 rounded">
                                        </div>

                                        <div id="farmer_fields" class="hidden space-y-3">
                                            <select id="select_farmer" name="sub_activity_id_1"
                                                class="w-full border rounded px-3 py-2 text-sm focus:ring-green-400 focus:border-green-400">
                                                <option value="">Select Commodity</option>
                                                <option value="1">Rice</option>
                                                <option value="2">Corn</option>
                                                <option value="21">Other Crops</option>
                                                <option value="4">Livestock</option>
                                                <option value="5">Poultry</option>
                                            </select>

                                            <input type="text" id="input_farmer_othercrop" name="farmer_othercrop"
                                                placeholder="Specify crop type (e.g. Tomato, Onion, etc.)"
                                                class="border px-3 py-2 text-sm rounded w-full hidden focus:ring-green-400 focus:border-green-400">

                                            <div class="grid grid-cols-2 gap-3">
                                                <input type="number" step="0.01" min="0" name="farmer_area"
                                                    placeholder="Total Area (ha)"
                                                    class="border px-3 py-2 text-sm rounded w-full focus:ring-green-400 focus:border-green-400">
                                                <input type="text" name="farmer_location" placeholder="Farm Location"
                                                    class="border px-3 py-2 text-sm rounded w-full focus:ring-green-400 focus:border-green-400">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- FARMER WORKER -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <label for="chk_farmerworker" class="font-semibold text-yellow-800">
                                                Farmer Worker
                                            </label>
                                            <input id="chk_farmerworker" type="checkbox" name="category_types[]"
                                                value="2" class="w-4 h-4 text-yellow-600 border-gray-300 rounded">
                                        </div>

                                        <div id="farmerworker_fields" class="hidden space-y-3">
                                            <select id="select_farmerworker" name="sub_activity_id_2"
                                                class="w-full border rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400">
                                                <option value="">Select Type of Work</option>
                                                <option value="6">Land Preparation</option>
                                                <option value="7">Planting/Transplanting</option>
                                                <option value="8">Cultivation</option>
                                                <option value="9">Harvesting</option>
                                                <option value="10">Others</option>
                                            </select>

                                            <input type="text" id="input_farmerworker_other" name="farmerworker_other"
                                                placeholder="Specify type of work..."
                                                class="border px-3 py-2 text-sm rounded w-full hidden focus:ring-yellow-400 focus:border-yellow-400">

                                            <div class="grid grid-cols-2 gap-3">
                                                <input type="number" step="0.01" min="0" name="farmerworker_area"
                                                    placeholder="Total Area (ha)"
                                                    class="border px-3 py-2 text-sm rounded w-full focus:ring-yellow-400 focus:border-yellow-400">
                                                <input type="text" name="farmerworker_location"
                                                    placeholder="Work Location"
                                                    class="border px-3 py-2 text-sm rounded w-full focus:ring-yellow-400 focus:border-yellow-400">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- FISHERFOLK -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <label for="chk_fisherfolk" class="font-semibold text-blue-800">
                                                Fisherfolk
                                            </label>
                                            <input id="chk_fisherfolk" type="checkbox" name="category_types[]" value="3"
                                                class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                        </div>

                                        <div id="fisherfolk_fields" class="hidden space-y-3">
                                            <select id="select_fisherfolk" name="sub_activity_id_3"
                                                class="w-full border rounded px-3 py-2 text-sm focus:ring-blue-400 focus:border-blue-400">
                                                <option value="">Select Commodity</option>
                                                <option value="11">Fish Capture</option>
                                                <option value="12">Aquaculture</option>
                                                <option value="13">Gleaning</option>
                                                <option value="14">Fish Processing</option>
                                                <option value="15">Fish Vending</option>
                                                <option value="16">Others</option>
                                            </select>

                                            <input type="text" id="input_fisherfolk_other" name="fisherfolk_other"
                                                placeholder="Specify type of livelihood..."
                                                class="border px-3 py-2 text-sm rounded w-full hidden focus:ring-blue-400 focus:border-blue-400">

                                            <!-- Only location field for Fisherfolk (no area) -->
                                            <div>
                                                <input type="text" name="fisherfolk_location" placeholder="Fishing Area"
                                                    class="w-full border px-3 py-2 text-sm rounded focus:ring-blue-400 focus:border-blue-400">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BUTTONS -->
                                    <div class="flex justify-end gap-3 pt-4 border-t">
                                        <button type="button" id="save-btn"
                                            class="bg-green-700 hover:bg-green-800 text-white px-5 py-2.5 rounded-md text-sm shadow-md transition">
                                            Save
                                        </button>
                                        <button type="button" data-modal-hide="default-modal"
                                            class="px-5 py-2.5 border rounded-md text-sm text-gray-700 hover:bg-gray-100">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script>
            // Edit button click handler
            document.querySelectorAll('[data-modal-target="edit-modal"]').forEach(button => {
                button.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const registrationId = this.getAttribute('data-registration-id');
                    const categoryId = this.getAttribute('data-category-id');

                    // Get row data
                    const row = this.closest('tr');
                    const livelihood = row.querySelector('td:nth-child(1)').textContent.trim();
                    const commodityCell = row.querySelector('td:nth-child(2)').textContent.trim();
                    const totalArea = row.querySelector('td:nth-child(3)').textContent.trim();
                    const farmLocation = row.querySelector('td:nth-child(4)').textContent.trim();

                    // Parse commodity and remarks
                    let commodityValue = commodityCell;
                    let remarksValue = '';

                    if (commodityCell.includes(' - ')) {
                        const parts = commodityCell.split(' - ');
                        commodityValue = parts[0].trim();
                        remarksValue = parts[1].trim();
                    }

                    // Populate form
                    document.getElementById('edit_livelihood_id').value = id;
                    document.getElementById('edit_registration_form_id').value = registrationId;
                    document.getElementById('edit_category_id').value = categoryId;
                    document.getElementById('edit_livelihood').value = livelihood;
                    document.getElementById('edit_commodity').value = commodityValue;
                    document.getElementById('edit_remarks').value = remarksValue;
                    document.getElementById('edit_farm_location').value = farmLocation;

                    // Handle total area based on category
                    const totalAreaContainer = document.getElementById('total-area-container');
                    const locationLabel = document.getElementById('location-label');

                    if (categoryId == 3) { // Fisherfolk
                        // Hide total area field
                        totalAreaContainer.classList.add('hidden');
                        // Clear total area value (set to empty for Fisherfolk)
                        document.getElementById('edit_total_area').value = '';
                        // Change location label
                        locationLabel.textContent = 'Fishing Area';
                    } else {
                        // Show total area field for Farmer (1) and Farmer Worker (2)
                        totalAreaContainer.classList.remove('hidden');
                        // Set total area value
                        document.getElementById('edit_total_area').value = totalArea === '-' ? '' : totalArea;
                        // Reset location label
                        locationLabel.textContent = categoryId == 2 ? 'Work Location' : 'Farm Location';
                    }

                    // Show the modal
                    const editModal = document.getElementById('edit-modal');
                    editModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                });
            });

            // Close modal functionality
            document.querySelectorAll('[data-modal-hide="edit-modal"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('edit-modal').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                });
            });

            // Close modal when clicking outside
            document.getElementById('edit-modal').addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        </script>
        <script>
            document.querySelectorAll('[data-modal-target="edit-modal"]').forEach(button => {
                button.addEventListener('click', function () {
                    const row = this.closest('tr');
                    const categoryId = this.getAttribute('data-id');
                    const farmerId = this.getAttribute('data-farmer-id');
                    const livelihood = row.children[0].textContent.trim();
                    const commodityText = row.children[1].textContent.trim();
                    const totalArea = row.children[2].textContent.trim();
                    const farmLocation = row.children[3].textContent.trim();

                    let [commodity, remarks] = commodityText.split(' - ');
                    if (!remarks) remarks = '';

                    document.getElementById('edit_category_id').value = categoryId;
                    document.getElementById('edit_registration_form_id').value = farmerId;
                    document.getElementById('edit_livelihood').value = livelihood;
                    document.getElementById('edit_commodity').value = commodity;
                    document.getElementById('edit_remarks').value = remarks;
                    document.getElementById('edit_total_area').value = totalArea;
                    document.getElementById('edit_farm_location').value = farmLocation;

                    console.log("🧾 Edit Modal Opened — Farmer ID:", farmerId, "Category ID:", categoryId);
                });
            });

            // ✅ Handle form submission with alert feedback
            document.getElementById('editForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                try {
                    const response = await fetch('update_commodity.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message); // ✅ Localhost alert message

                    if (result.status === 'success') {
                        // Optional: close modal if using Flowbite
                        const closeBtn = document.querySelector('[data-modal-hide="edit-modal"]');
                        if (closeBtn) closeBtn.click();
                        location.reload();
                    }
                } catch (error) {
                    alert('❌ Localhost Error: ' + error.message);
                    console.error('Error:', error);
                }
            });
            document.querySelectorAll('[data-modal-toggle="edit-modal"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('edit_livelihood_id').value = btn.dataset.id;
                    document.getElementById('edit_registration_form_id').value = btn.dataset.registrationId;
                    document.getElementById('edit_category_id').value = btn.dataset.categoryId;
                    // populate other fields as needed...
                });
            });
        </script>


        <script>
            async function confirmDelete(id) {
                if (!confirm("⚠️ Are you sure you want to delete this record?")) return;

                const formData = new FormData();
                formData.append("id", id);

                try {
                    const response = await fetch("delete_commodity.php", {
                        method: "POST",
                        body: formData
                    });

                    const result = await response.json();
                    alert(result.message);

                    if (result.status === "success") {
                        // ✅ remove only this row instantly
                        document.querySelector(`button[onclick="confirmDelete('${id}')"]`).closest("tr").remove();
                    }
                } catch (error) {
                    alert("❌ Network error: " + error.message);
                }
            }
        </script>


        <!-- for edit -->
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.querySelectorAll(".edit-btn").forEach(btn => {
                    btn.addEventListener("click", () => {
                        const farmerId = btn.getAttribute("data-id");
                        const section = btn.getAttribute("data-section");

                        // Determine form and modal IDs based on section
                        const formId =
                            section === "family" ? "editFamilyForm" :
                                section === "education" ? "editEducationForm" :
                                    section === "livelihood" ? "editLivelihoodForm" :
                                        "editPersonalForm";

                        const modalId =
                            section === "family" ? "edit-family-modal" :
                                section === "education" ? "edit-education-modal" :
                                    section === "livelihood" ? "edit-livelihood-modal" :
                                        "edit-personal-modal";

                        // Store info on the form
                        const form = document.getElementById(formId);
                        form.dataset.id = farmerId;
                        form.dataset.section = section;

                        // Show the correct modal
                        document.getElementById(modalId).classList.remove("hidden");
                    });
                });

                // Handle submit for all forms
                ["editPersonalForm", "editFamilyForm", "editEducationForm", "editLivelihoodForm"].forEach(formId => {
                    const form = document.getElementById(formId);
                    if (!form) return;

                    form.addEventListener("submit", async (e) => {
                        e.preventDefault();

                        const recordId = form.dataset.id;
                        const section = form.dataset.section || "personal";

                        if (!recordId) {
                            alert("❌ Missing ID");
                            return;
                        }

                        const formData = Object.fromEntries(new FormData(form).entries());
                        formData.id = recordId;
                        formData.section = section;

                        // Choose backend file dynamically
                        const endpoint = section === "livelihood" ? "update_livelihood.php" : "update_farmer.php";

                        try {
                            const res = await fetch(endpoint, {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify(formData)
                            });

                            const data = await res.json();
                            if (data.success) {
                                alert("✅ Updated successfully!");
                                form.closest(".modal")?.classList.add("hidden");
                                location.reload();
                            } else {
                                alert(data.message || "❌ Something went wrong!");
                            }
                        } catch (err) {
                            console.error("Error:", err);
                            alert("❌ Request failed");
                        }
                    });
                });
            });
        </script>
        <!-- for adding livelihood -->
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                ["farmer", "farmerworker", "fisherfolk"].forEach(type => {
                    const chk = document.getElementById(`chk_${type}`);
                    const fields = document.getElementById(`${type}_fields`);
                    const select = document.getElementById(`select_${type}`);
                    const inputOther = document.getElementById(
                        type === "farmer" ? "input_farmer_othercrop" : `input_${type}_other`
                    );

                    // ✅ Toggle visibility of fields
                    chk.addEventListener("change", () => {
                        fields.classList.toggle("hidden", !chk.checked);
                        if (!chk.checked) {
                            // Clear fields when unchecked
                            select.value = "";
                            inputOther.value = "";
                            inputOther.classList.add("hidden");

                            // Clear area and location if they exist
                            const area = document.querySelector(`[name="${type}_area"]`);
                            const location = document.querySelector(`[name="${type}_location"]`);
                            if (area) area.value = "";
                            if (location) location.value = "";
                        }
                    });

                    // ✅ Show "Other" input if specific option selected
                    select.addEventListener("change", () => {
                        const showInput =
                            select.value === "21" ||  // Farmer - Other Crops
                            select.value === "10" ||  // Farmer Worker - Others
                            select.value === "16";    // Fisherfolk - Others
                        inputOther.classList.toggle("hidden", !showInput);
                        if (!showInput) inputOther.value = "";
                    });
                });

                // ✅ SINGLE SAVE HANDLER
                document.getElementById("save-btn").addEventListener("click", async function () {
                    const form = document.getElementById("commodity-form");
                    const formData = new FormData(form);
                    let valid = true;
                    let missing = [];

                    // Clear any existing form data and rebuild it properly
                    const newFormData = new FormData();
                    newFormData.append('registration_form_id', formData.get('registration_form_id'));

                    let hasValidEntries = false;

                    ["farmer", "farmerworker", "fisherfolk"].forEach(type => {
                        const chk = document.getElementById(`chk_${type}`);
                        if (chk.checked) {
                            const select = document.getElementById(`select_${type}`);
                            const area = document.querySelector(`[name="${type}_area"]`);
                            const location = document.querySelector(`[name="${type}_location"]`);
                            const inputOther = document.getElementById(
                                type === "farmer" ? "input_farmer_othercrop" : `input_${type}_other`
                            );

                            let entryValid = true;
                            let entryMissing = [];

                            // Validate commodity selection
                            if (!select.value) {
                                entryValid = false;
                                entryMissing.push("Commodity");
                            }

                            // Validate "Other" specification
                            if ((select.value === "21" || select.value === "10" || select.value === "16") &&
                                !inputOther.value.trim()) {
                                entryValid = false;
                                entryMissing.push("Specify other");
                            }

                            // Validate area (not required for fisherfolk)
                            if (type !== "fisherfolk") {
                                if (!area.value) {
                                    entryValid = false;
                                    entryMissing.push("Area");
                                }
                            }

                            // Validate location
                            if (!location.value.trim()) {
                                entryValid = false;
                                entryMissing.push("Location");
                            }

                            if (!entryValid) {
                                valid = false;
                                missing.push(`${capitalize(type)}: ${entryMissing.join(", ")}`);
                            } else {
                                // Add complete entry to form data
                                hasValidEntries = true;
                                newFormData.append(`category_types[]`, chk.value);
                                newFormData.append(`sub_activity_id_${type === "farmer" ? 1 : type === "farmerworker" ? 2 : 3}`, select.value);

                                if (location.value.trim()) {
                                    newFormData.append(`${type}_location`, location.value.trim());
                                }

                                if (type !== "fisherfolk" && area.value) {
                                    newFormData.append(`${type}_area`, area.value);
                                }

                                if (inputOther.value.trim() && (select.value === "21" || select.value === "10" || select.value === "16")) {
                                    const otherFieldName = type === "farmer" ? "farmer_othercrop" : `${type}_other`;
                                    newFormData.append(otherFieldName, inputOther.value.trim());
                                }
                            }
                        }
                    });

                    if (!valid) {
                        alert("⚠️ Please complete the following fields:\n\n" + missing.join("\n"));
                        return;
                    }

                    if (!hasValidEntries) {
                        alert("⚠️ Please select at least one livelihood and fill in all required fields.");
                        return;
                    }

                    try {
                        // ✅ POST form data to backend
                        const response = await fetch("add_livelihood.php", {
                            method: "POST",
                            body: newFormData
                        });

                        const data = await response.json();
                        console.log("Server Response:", data);

                        if (data.status === "success") {
                            // ✅ User feedback
                            alert(data.message || "✅ Saved successfully!");

                            // Close modal
                            const closeBtn = document.querySelector('[data-modal-hide="default-modal"]');
                            if (closeBtn) closeBtn.click();

                            // Reset form
                            form.reset();

                            // Hide all sections
                            ["farmer_fields", "farmerworker_fields", "fisherfolk_fields"].forEach(id => {
                                document.getElementById(id).classList.add("hidden");
                            });

                            // Reset checkboxes
                            ["farmer", "farmerworker", "fisherfolk"].forEach(type => {
                                document.getElementById(`chk_${type}`).checked = false;
                            });

                            // Reload or update the page content
                            if (data.redirect) {
                                window.location.reload();
                            }
                        } else {
                            alert(data.message || "❌ Failed to save. Please try again.");
                        }
                    } catch (err) {
                        console.error("Error saving form:", err);
                        alert("❌ Server error while saving. Please try again.");
                    }
                });

                function capitalize(str) {
                    return str.charAt(0).toUpperCase() + str.slice(1);
                }
            });
        </script>
        <!-- farmer id fetch -->
        <script>
            // Handle modal open and set registration ID
            document.addEventListener('click', function (e) {
                if (e.target && e.target.id === 'open-livelihood-modal') {
                    const regId = e.target.getAttribute('data-registration-id');
                    const regInput = document.getElementById('registration_form_id');
                    if (regId && regInput) {
                        regInput.value = regId;
                        console.log("✅ registration_form_id set to:", regId);
                    } else {
                        console.warn("⚠️ registration_form_id not found or missing in button attribute.");
                    }
                }
            });
        </script>


        <script src="../js/tailwind.config.js"></script>
        <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</body>

</html>