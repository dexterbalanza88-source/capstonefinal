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

include "../../db/conn.php";
$sql = "SELECT * FROM archived_register ORDER BY archived_at ASC";
$result = mysqli_query($conn, $sql);
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

// 5. Count total archived records
$countSql = "SELECT COUNT(*) as total FROM archived_register $whereClause";
$countResult = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ($totalRows > 0) ? ceil($totalRows / $limit) : 1;

// 6. Calculate starting row
$offset = ($page - 1) * $limit;

// 7. Fetch paginated data
$sql = "SELECT * FROM archived_register $whereClause ORDER BY id DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
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
WHERE f.status != 'Archived'
GROUP BY f.id
ORDER BY f.id DESC
LIMIT ? OFFSET ?
";
?>


<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/output.css" rel="stylesheet">
</head>

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
                            class="flex items-center p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group">
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
        <main class="md:ml-64 pt-20 p-2 width-full ">
            <!-- Start coding here -->
            <section class="bg-gray-50 dark:bg-gray-900 p-2 sm:p-4">
                <div class="mx-auto max-w-screen-xl px-4">
                    <!-- Start coding here -->
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
                        <div
                            class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class=" md:w-1/2" style="width: 500px;">
                                <form class="flex items-center">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                                fill="currentColor" viewbox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="Search" required="">
                                    </div>
                                </form>
                            </div>
                            <div
                                class=" md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <div class="flex items-center space-x-3 w-full md:w-auto">
                                    <div class="mb-3">
                                        <button id="restoreAllBtn"
                                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 pointer-events-none opacity-50">
                                            Restore Selected
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if ($totalRows > 0): ?>
                                <table id="yourTableId"
                                    class="w-full text-xs text-left text-gray-500 bg-white shadow rounded-lg overflow-hidden">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3">
                                                <input type="checkbox" id="selectAll"
                                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                            </th>
                                            <th class="px-4 py-3">ID</th>
                                            <th class="px-4 py-3">Name</th>
                                            <th class="px-4 py-3">Address</th>
                                            <th class="px-4 py-3">Contact</th>
                                            <th class="px-4 py-3">Age</th>
                                            <th class="px-4 py-3">DOB</th>
                                            <th class="px-4 py-3">Gender</th>
                                            <th class="px-4 py-3">Farm Size</th>
                                            <th class="px-4 py-3">Type of Commodity</th>
                                            <th class="px-4 py-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" class="rowCheckbox" data-id="<?= $row['id'] ?>"
                                                        value="<?= $row['id'] ?>">
                                                </td>
                                                <td class="px-4 py-3 rowNumber"><?= (($page - 1) * $limit) + $counter ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($fullName) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['brgy']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['mobile']) ?></td>
                                                <td class="px-4 py-3"><?= $age ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['dob']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['gender']) ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars($row['total_farmarea']) ?></td>

                                                <!-- Single column for all livelihoods -->
                                                <td class="px-4 py-3"><?= htmlspecialchars($livelihoods) ?></td>
                                                <td class="px-4 py-3 text-center">
                                                    <button data-id="<?= $row['id']; ?>" class="archiveAction flex items-center justify-center gap-2 
                                                                px-4 py-2 bg-green-500 text-white font-medium 
                                                                rounded-lg shadow-sm hover:bg-green-600 hover:shadow 
                                                                transition-all duration-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6" />
                                                        </svg>
                                                        Restore
                                                    </button>
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
                                            ? 'bg-blue-500 text-white dark:bg-blue-600 dark:border-blue-600'
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
        document.addEventListener("DOMContentLoaded", () => {

            /* -------------------------------------------------
               SELECTION STORAGE
            -------------------------------------------------- */
            let selectedIds = new Set(JSON.parse(localStorage.getItem("selectedIds") || "[]"));
            let allSelectedAcrossPages = localStorage.getItem("allSelectedAcrossPages") === "true";

            const selectAllCheckbox = document.getElementById("selectAll");
            const tableBody = document.querySelector("#yourTableId tbody");
            const restoreAllBtn = document.getElementById("restoreAllBtn");

            const getRowId = (cb) => cb.dataset.id ?? cb.value;

            // Enable/disable restore buttons based on selection
            function updateRestoreButtons() {
                const anySelected = selectedIds.size > 0;

                // Update Restore Selected button
                const restoreSelectedBtn = document.getElementById("restoreSelectedBtn");
                if (restoreSelectedBtn) {
                    restoreSelectedBtn.disabled = !anySelected;
                    restoreSelectedBtn.classList.toggle("opacity-50", !anySelected);
                    restoreSelectedBtn.classList.toggle("pointer-events-none", !anySelected);

                    if (anySelected) {
                        restoreSelectedBtn.textContent = `Restore Selected (${selectedIds.size})`;
                    } else {
                        restoreSelectedBtn.textContent = "Restore Selected";
                    }
                }
            }

            /* -------------------------------------------------
               RESTORE CHECKBOX STATES
            -------------------------------------------------- */
            const restoreCheckboxStates = () => {
                document.querySelectorAll(".rowCheckbox").forEach(cb => {
                    cb.checked = selectedIds.has(String(getRowId(cb)));
                });
                const allRows = document.querySelectorAll(".rowCheckbox");
                selectAllCheckbox.checked =
                    allRows.length > 0 && Array.from(allRows).every(cb => cb.checked);

                updateRestoreButtons();
            };

            const saveSelection = () => {
                localStorage.setItem("selectedIds", JSON.stringify(Array.from(selectedIds)));
                localStorage.setItem("allSelectedAcrossPages", allSelectedAcrossPages ? "true" : "false");
                updateRestoreButtons();
            };

            /* -------------------------------------------------
               SELECT ALL CHECKBOX
            -------------------------------------------------- */
            selectAllCheckbox?.addEventListener("change", async function () {
                const visible = Array.from(document.querySelectorAll(".rowCheckbox"));

                if (this.checked) {
                    try {
                        const res = await fetch("get_archived_ids.php");
                        const json = await res.json();

                        if (json.success && Array.isArray(json.ids)) {
                            selectedIds = new Set(json.ids.map(id => String(id)));
                            visible.forEach(cb => (cb.checked = true));
                            allSelectedAcrossPages = true;
                            saveSelection();
                        } else {
                            alert("Failed to fetch all IDs.");
                            this.checked = false;
                        }
                    } catch (err) {
                        console.error(err);
                        alert("Error contacting server.");
                        this.checked = false;
                    }
                } else {
                    selectedIds.clear();
                    allSelectedAcrossPages = false;
                    visible.forEach(cb => (cb.checked = false));
                    saveSelection();
                }
            });

            /* -------------------------------------------------
               INDIVIDUAL CHECKBOX
            -------------------------------------------------- */
            document.addEventListener("change", (e) => {
                if (!e.target.classList.contains("rowCheckbox")) return;

                const id = String(getRowId(e.target));
                if (e.target.checked) selectedIds.add(id);
                else {
                    selectedIds.delete(id);
                    allSelectedAcrossPages = false;
                    selectAllCheckbox.checked = false;
                }

                saveSelection();
            });

            /* -------------------------------------------------
               NOTIFICATION FUNCTION
            -------------------------------------------------- */
            function showNotification(message, type = 'success') {
                const colors = {
                    success: 'bg-green-500',
                    error: 'bg-red-500',
                    info: 'bg-blue-500',
                    warning: 'bg-yellow-500'
                };

                const icons = {
                    success: 'fas fa-check',
                    error: 'fas fa-times',
                    info: 'fas fa-info-circle',
                    warning: 'fas fa-exclamation-triangle'
                };

                // Remove existing notifications
                document.querySelectorAll('.custom-notification').forEach(n => n.remove());

                const notification = document.createElement('div');
                notification.className = `custom-notification fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center max-w-md`;
                notification.style.animation = 'slideIn 0.3s ease-out';
                notification.innerHTML = `
                <i class="${icons[type]} mr-3 text-lg"></i>
                <span class="flex-1">${message}</span>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

                document.body.appendChild(notification);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.animation = 'slideOut 0.3s ease-out';
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 5000);
            }

            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
            document.head.appendChild(style);

            /* -------------------------------------------------
               RESTORE SINGLE RECORD
            -------------------------------------------------- */
            document.addEventListener("click", async (e) => {
                // Check if it's a restore button - look for button with archiveAction class (your current setup)
                const target = e.target.closest('.archiveAction');
                if (!target) return;

                e.preventDefault();

                // Get the ID from data-id attribute
                const id = target.dataset.id;
                if (!id) {
                    console.error('No ID found for restore button');
                    return;
                }

                const row = target.closest('tr');
                const farmerName = row?.querySelector('td:nth-child(3)')?.textContent?.trim() || 'this record';

                if (!confirm(`Are you sure you want to restore ${farmerName}?`)) return;

                // Disable button and show loading
                const originalHTML = target.innerHTML;
                target.disabled = true;
                target.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Restoring...';

                const formData = new FormData();
                formData.append("id", id);

                try {
                    const res = await fetch("restore.php", {
                        method: "POST",
                        body: formData
                    });

                    const json = await res.json();

                    if (json.success) {
                        // Remove from selectedIds if it was selected
                        selectedIds.delete(String(id));
                        saveSelection();

                        // Remove row with animation
                        if (row) {
                            row.style.opacity = '0';
                            row.style.transition = 'opacity 0.3s';
                            setTimeout(() => {
                                row.remove();

                                // Show success notification
                                showNotification(`✅ ${json.farmer_name || farmerName} restored successfully!`, 'success');

                                // Update table if empty
                                if (tableBody && tableBody.children.length === 0) {
                                    tableBody.innerHTML = `
                                    <tr>
                                        <td colspan="11" class="px-6 py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center gap-2">
                                                <i class="fas fa-archive text-3xl text-gray-300"></i>
                                                <p class="text-lg font-medium text-gray-400">No Archived Records</p>
                                                <p class="text-sm text-gray-500">All records have been restored.</p>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                                }
                            }, 300);
                        }
                    } else {
                        showNotification(`❌ Restore failed: ${json.error || "Unknown error"}`, 'error');
                        target.disabled = false;
                        target.innerHTML = originalHTML;
                    }
                } catch (error) {
                    console.error(error);
                    showNotification('❌ Network error. Please try again.', 'error');
                    target.disabled = false;
                    target.innerHTML = originalHTML;
                }
            });

            /* -------------------------------------------------
               RESTORE SELECTED RECORDS (Batch restore)
            -------------------------------------------------- */
            document.getElementById("restoreSelectedBtn")?.addEventListener("click", async () => {
                if (selectedIds.size === 0) {
                    showNotification("Please select at least one record to restore.", 'info');
                    return;
                }

                if (!confirm(`Are you sure you want to restore ${selectedIds.size} selected record(s)?`)) return;

                const button = document.getElementById("restoreSelectedBtn");
                const originalHTML = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Restoring...';

                try {
                    // For batch restore, we'll restore one by one to handle errors properly
                    const ids = Array.from(selectedIds);
                    let restoredCount = 0;
                    let failedCount = 0;
                    let restoredIds = [];

                    for (let id of ids) {
                        try {
                            const formData = new FormData();
                            formData.append("id", id);

                            const res = await fetch("restore.php", {
                                method: "POST",
                                body: formData
                            });

                            const json = await res.json();

                            if (json.success) {
                                restoredCount++;
                                restoredIds.push(id);

                                // Remove row with animation
                                const row = document.querySelector(`tr[data-id='${id}']`);
                                if (row) {
                                    row.style.opacity = '0';
                                    row.style.transition = 'opacity 0.3s';
                                    setTimeout(() => row.remove(), 300);
                                }
                            } else {
                                failedCount++;
                                console.error(`Failed to restore ID ${id}:`, json.error);
                            }
                        } catch (error) {
                            failedCount++;
                            console.error(`Error restoring ID ${id}:`, error);
                        }
                    }

                    // Clear selection
                    selectedIds.clear();
                    allSelectedAcrossPages = false;
                    if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    saveSelection();

                    // Show results
                    if (restoredCount > 0) {
                        showNotification(`✅ Successfully restored ${restoredCount} record(s)${failedCount > 0 ? `, ${failedCount} failed` : ''}`,
                            failedCount > 0 ? 'warning' : 'success');

                        // Update table if empty
                        if (tableBody && tableBody.children.length === 0) {
                            tableBody.innerHTML = `
                            <tr>
                                <td colspan="11" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-archive text-3xl text-gray-300"></i>
                                        <p class="text-lg font-medium text-gray-400">No Archived Records</p>
                                        <p class="text-sm text-gray-500">All records have been restored.</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                        }
                    } else {
                        showNotification('❌ No records were restored. Please try again.', 'error');
                    }
                } catch (error) {
                    console.error(error);
                    showNotification('❌ Network error. Please try again.', 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalHTML;
                }
            });

            /* -------------------------------------------------
               RESTORE ALL BUTTON
            -------------------------------------------------- */
            document.getElementById("restoreAllBtn")?.addEventListener("click", async () => {
                // First get count of records
                try {
                    const countRes = await fetch("get_archived_count.php");
                    const countJson = await countRes.json();

                    if (!countJson.success || countJson.total === 0) {
                        showNotification("No archived records to restore.", 'info');
                        return;
                    }

                    if (!confirm(`Are you sure you want to restore ALL ${countJson.total} archived records?`)) return;

                    const button = document.getElementById("restoreAllBtn");
                    const originalHTML = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Restoring All...';

                    // Send restore_all request
                    const formData = new FormData();
                    formData.append("restore_all", "true");

                    const res = await fetch("restore.php", {
                        method: "POST",
                        body: formData
                    });

                    const json = await res.json();

                    if (json.success) {
                        // Clear selection
                        selectedIds.clear();
                        allSelectedAcrossPages = false;
                        if (selectAllCheckbox) selectAllCheckbox.checked = false;
                        saveSelection();

                        // Remove all rows with animation
                        const rows = document.querySelectorAll("#yourTableId tbody tr:not(:first-child)");
                        rows.forEach((row, index) => {
                            setTimeout(() => {
                                row.style.opacity = '0';
                                row.style.transition = 'opacity 0.3s';
                                setTimeout(() => row.remove(), 300);
                            }, index * 50); // Stagger animations
                        });

                        // Show empty state after animations
                        setTimeout(() => {
                            if (tableBody && tableBody.children.length === 0) {
                                tableBody.innerHTML = `
                                <tr>
                                    <td colspan="11" class="px-6 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <i class="fas fa-archive text-3xl text-gray-300"></i>
                                            <p class="text-lg font-medium text-gray-400">No Archived Records</p>
                                            <p class="text-sm text-gray-500">All records have been restored.</p>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            }
                        }, (rows.length * 50) + 500);

                        showNotification(`✅ ${json.message || "All records restored successfully!"}`, 'success');
                    } else {
                        showNotification(`❌ Restore all failed: ${json.error || "Unknown error"}`, 'error');
                    }
                } catch (error) {
                    console.error(error);
                    showNotification('❌ Network error. Please try again.', 'error');
                } finally {
                    const button = document.getElementById("restoreAllBtn");
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = originalHTML;
                    }
                }
            });

            /* -------------------------------------------------
               FETCH TABLE CONTENT (for refresh)
            -------------------------------------------------- */
            async function fetchTableContent() {
                try {
                    const res = await fetch("get_archived_table.php");
                    const html = await res.text();

                    if (tableBody && html.trim() !== "") {
                        tableBody.innerHTML = html;
                        restoreCheckboxStates();
                    }
                } catch (error) {
                    console.error("Failed to fetch table content:", error);
                }
            }

            /* -------------------------------------------------
               INITIALIZE
            -------------------------------------------------- */
            restoreCheckboxStates();

            // Make restoreAllBtn clickable initially
            if (restoreAllBtn) {
                restoreAllBtn.classList.remove("pointer-events-none", "opacity-50");
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

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add("opacity-0", "scale-95");
                setTimeout(() => dropdown.classList.add("hidden"), 150);
            }
        });
        // Add this to every page's JavaScript section
        document.addEventListener('DOMContentLoaded', function () {
            // Check if profileMenu parameter is in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('profileMenu') === 'open') {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    dropdown.classList.remove('hidden', 'opacity-0', 'scale-95');
                    dropdown.classList.add('block', 'opacity-100', 'scale-100');
                }

                // Remove the parameter from URL without reloading
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
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
</body>

</html>