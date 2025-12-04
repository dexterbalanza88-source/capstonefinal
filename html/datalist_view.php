<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// 5. Count total records
$countSql = "SELECT COUNT(*) as total FROM registration_form $whereClause";
$countResult = mysqli_query($conn, $countSql);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ($totalRows > 0) ? ceil($totalRows / $limit) : 1;

// 6. Calculate starting row
$offset = ($page - 1) * $limit;

// 7. Fetch data with limit + search
$sql = "SELECT id, s_name, f_name, m_name, brgy, mobile, dob, gender, total_farmarea, 
               main_livelihood, for_farmer, for_farmerworker, for_fisherfolk, for_agri, status 
        FROM registration_form
        $whereClause
        ORDER BY id ASC
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
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
</style>

<body>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <nav
            class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50">
            <div class="flex flex-wrap justify-between items-center">
                <div class="flex justify-start items-center">
                    <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                        aria-controls="drawer-navigation"
                        class="p-2 mr-2 text-gray-600 rounded-lg cursor-pointer md:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100 dark:focus:bg-gray-700 focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <svg aria-hidden="true" class="hidden w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="https://flowbite.com" class="flex items-center justify-between mr-4">
                        <img src="../img/logo.png" class="mr-3 h-12" alt="Flowbite Logo" />
                        <span class="self-center text-1xl font-semibold whitespace-nowrap dark:text-white">Digital RSBSA
                            in Abra de Ilog Occidental Mindoro</span>
                    </a>
                </div>
                <div class="flex items-center lg:order-2">
                    <button type="button" data-drawer-toggle="drawer-navigation" aria-controls="drawer-navigation"
                        class="p-2 mr-1 text-gray-500 rounded-lg md:hidden hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600">
                        <span class="sr-only">Toggle search</span>
                        <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
                            </path>
                        </svg>
                    </button>
                    <!-- Dropdown menu -->
                    <button type="button"
                        class="flex mx-3 text-sm bg-white-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-white-100"
                        id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown">
                        <span class="sr-only">Open user menu</span>
                        <img class="w-8 h-8 rounded-full" src="../img/profile.png" alt="user photo" />
                    </button>
                    <!-- Dropdown menu -->
                    <div class="hidden z-50 my-4 w-56 text-base list-none bg-white divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600 rounded-xl"
                        id="dropdown">
                        <div class="py-3 px-4">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white text-center">
                                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                            </span>
                            <span class="block text-sm text-gray-900 truncate dark:text-white text-center">
                                <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="profile.html"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My
                                    profile</a>
                            </li>
                            <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                                <li>
                                    <a href="login.html"
                                        class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Sign
                                        out</a>
                                </li>
                            </ul>
                    </div>
                </div>
            </div>
        </nav>
        <!-- Sidebar -->
        <aside
            class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
            aria-label="Sidenav" id="drawer-navigation">
            <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
                <ul class="space-y-2">
                    <li>
                        <a href="./index.html"
                            class="flex items-center p-2 text-base font-normal text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                            </svg>
                            <span class="ml-3">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="adddata.php"
                            class="flex items-center p-2 text-base font-normal text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg class="w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M9 2.221V7H4.221a2 2 0 0 1 .365-.5L8.5 2.586A2 2 0 0 1 9 2.22ZM11 2v5a2 2 0 0 1-2 2H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-7Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="ml-3">Add Data</span>
                        </a>
                    </li>
                    <li>
                    <li>
                        <a href="#datalist.php" style="color:blue;"
                            class="flex items-center p-2 text-base font-normal text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Data List</span>
                        </a>
                    </li>
                </ul>
                </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="report.html"
                            class="flex items-center p-2 text-base font-normal text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Reports </span>
                        </a>
                    </li>
                    <li>
                        <a href="archived.php"
                            class="flex items-center p-2 text-base font-normal text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z">
                                </path>
                            </svg>
                            <span class="ml-3">Archived</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        <main class="md:ml-64 pt-20 p-2 width-full ">
            <!-- ✅ VIEW FARMER MODAL -->
            <div id="view-farmer-modal"
                class=" w-full ">
                <div class="relative p-4 w-full ">
                    <div class="relative bg-white/95 rounded-lg shadow-lg">
                        <!-- Header -->
                        <div class="flex justify-between items-center p-4 border-b border-gray-200">
                            <h3 class="text-xl font-semibold text-gray-800">👨‍🌾 Farmer Details</h3>
                        </div>
                        <div class="bg-white rounded-sm p-2 shadow h-[450px] ">
                            <!-- Hidden Farmer ID -->
                            <input type="hidden" id="farmerId" value="" />
                            <!-- step1 -->
                            <div class="p-6 bg-white " id="step1">
                                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-300">
                                    <h2 class="text-xl font-bold text-gray-800">Farmer Details</h2>
                                    <img id="farmerPhoto" src="assets/default-avatar.png" alt="Farmer Photo"
                                        class="w-20 h-20 object-cover rounded-md border border-gray-300 shadow-sm" />
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <p class="text-sm text-gray-500">Date</p>
                                        <p class="fetch-field" data-field="date"></p>
                                        <input type="text" class="hidden update-field" id="update_date_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Reference No.</p>
                                        <p class="fetch-field" data-field="reference"></p>
                                        <input type="text" class="hidden update-field" id="update_reference_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Surname</p>
                                        <p class="fetch-field" data-field="s_name"></p>
                                        <input type="text" class="hidden update-field" id="update_s_name_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">First Name</p>
                                        <p class="fetch-field" data-field="f_name"></p>
                                        <input type="text" class="hidden update-field" id="update_f_name_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Middle Name</p>
                                        <p class="fetch-field" data-field="m_name"></p>
                                        <input type="text" class="hidden update-field" id="update_m_name_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Gender</p>
                                        <p class="fetch-field" data-field="gender"></p>
                                        <input type="text" class="hidden update-field" id="update_gender_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">HOUSE/LOT/BLDG NO./PUROK</p>
                                        <p class="fetch-field" data-field="house"></p>
                                        <input type="text" class="hidden update-field" id="update_house_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">STREET/SITIO/SUBDIV.</p>
                                        <p class="fetch-field" data-field="sitio"></p>
                                        <input type="text" class="hidden update-field" id="update_sitio_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Barangay</p>
                                        <p class="fetch-field" data-field="brgy"></p>
                                        <input type="text" class="hidden update-field" id="update_brgy_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Municipality/City</p>
                                        <p class="fetch-field" data-field="municipal"></p>
                                        <input type="text" class="hidden update-field" id="update_municipal_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Province</p>
                                        <p class="fetch-field" data-field="province"></p>
                                        <input type="text" class="hidden update-field" id="update_province_step1" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Region</p>
                                        <p class="fetch-field" data-field="region"></p>
                                        <input type="text" class="hidden update-field" id="update_region_step1" />
                                    </div>

                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="nextStep()">Next</button>
                                </div>
                            </div>
                            <!-- step2 -->
                            <div class="p-6 bg-white rounded-2xl shadow-lg w-full max-w-3xl mx-auto" id="step2"
                                style="display: none;">
                                <div class="flex items-center gap-2 border-b pb-3 mb-4">
                                    <h2 class="text-xl font-bold text-gray-800">Farmer Details</h2>
                                </div>
                                <!-- Step 2: Additional Farmer Info -->
                                <div class="grid grid-cols-2 gap-6 p-2">

                                    <div>
                                        <p class="text-sm text-gray-500">Mobile Number</p>
                                        <p class="fetch-field" data-field="mobile"></p>
                                        <input type="text" class="hidden update-field" id="update_mobile_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Landline Number</p>
                                        <p class="fetch-field" data-field="landline"></p>
                                        <input type="text" class="hidden update-field" id="update_landline_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Date of Birth</p>
                                        <p class="fetch-field" data-field="dob"></p>
                                        <input type="text" class="hidden update-field" id="update_dob_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Country</p>
                                        <p class="fetch-field" data-field="country"></p>
                                        <input type="text" class="hidden update-field" id="update_country_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Province of Birth</p>
                                        <p class="fetch-field" data-field="province_birth"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_province_birth_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Municipality of Birth</p>
                                        <p class="fetch-field" data-field="municipality_birth"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_municipality_birth_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Mother's Maiden Name</p>
                                        <p class="fetch-field" data-field="mother_maiden_name"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_mother_maiden_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">Relationship</p>
                                        <p class="fetch-field" data-field="relationship"></p>
                                        <input type="text" class="hidden update-field" id="update_relationship_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">No. of Living Household Members</p>
                                        <p class="fetch-field" data-field="no_livinghousehold"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_no_livinghousehold_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">No. Male</p>
                                        <p class="fetch-field" data-field="no_male"></p>
                                        <input type="text" class="hidden update-field" id="update_no_male_step2" />
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-500">No. Female</p>
                                        <p class="fetch-field" data-field="no_female"></p>
                                        <input type="text" class="hidden update-field" id="update_no_female_step2" />
                                    </div>
                                </div>
                                <!-- Navigation Buttons -->
                                <div class="mt-6 flex justify-between gap-2">
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="prevStep()">Back</button>
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="nextStep()">Next</button>
                                </div>
                            </div>
                            <!-- step3 -->
                            <div class="p-6 bg-white rounded-2xl shadow-lg w-full max-w-3xl mx-auto" id="step3"
                                style="display: none;">
                                <div class="flex items-center gap-2 border-b pb-3 mb-4">
                                    <h2 class="text-xl font-bold text-gray-800">Farmer Details</h2>
                                </div>
                                <!-- Step 2: Additional Farmer Info -->
                                <div class="grid grid-cols-2 gap-6 p-2">

                                    <!-- Mobile Number -->
                                    <div>
                                        <p class="text-sm text-gray-500">Highest Formal Education</p>
                                        <p class="fetch-field" data-field="education"></p>
                                        <input type="text" class="hidden update-field" id="update_education_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Person with Disabilities?</p>
                                        <p class="fetch-field" data-field="pwd"></p>
                                        <input type="text" class="hidden update-field" id="update_pwd_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">4P's Beneficiary?</p>
                                        <p class="fetch-field" data-field="fourps"></p>
                                        <input type="text" class="hidden update-field" id="update_fourps_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">With Government ID?</p>
                                        <p class="fetch-field" data-field="with_gov_id"></p>
                                        <input type="text" class="hidden update-field" id="update_with_gov_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Specify ID Type</p>
                                        <p class="fetch-field" data-field="specify_id"></p>
                                        <input type="text" class="hidden update-field" id="update_specify_id_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">ID Number</p>
                                        <p class="fetch-field" data-field="id_number"></p>
                                        <input type="text" class="hidden update-field" id="update_id_no_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Assoc Member?</p>
                                        <p class="fetch-field" data-field="assoc_member"></p>
                                        <input type="text" class="hidden update-field" id="update_assoc_member_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">If Yes, Specify</p>
                                        <p class="fetch-field" data-field="specify_assoc"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_specify_assoc_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Contact Number</p>
                                        <p class="fetch-field" data-field="contact_number"></p>
                                        <input type="text" class="hidden update-field" id="update_contact_num_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Farming</p>
                                        <p class="fetch-field" data-field="farming"></p>
                                        <input type="text" class="hidden update-field" id="update_farming_step3" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Non-Farming</p>
                                        <p class="fetch-field" data-field="non_farming"></p>
                                        <input type="text" class="hidden update-field" id="update_non_farming_step3" />
                                    </div>
                                </div>
                                <!-- Navigation Buttons -->
                                <div class="mt-6 flex justify-between gap-2">
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="prevStep()">Back</button>
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="nextStep()">Next</button>
                                </div>
                            </div>
                            <!-- step4 -->
                            <div class="p-6 bg-white rounded-2xl shadow-lg w-full max-w-3xl mx-auto" id="step4"
                                style="display: none;">
                                <div class="flex items-center gap-2 border-b pb-3 mb-4">
                                    <h2 class="text-xl font-bold text-gray-800">Farmer Details</h2>
                                </div>
                                <!-- Step 2: Additional Farmer Info -->
                                <div class="grid grid-cols-2 gap-6 p-2">

                                    <!-- Mobile Number -->
                                    <div>
                                        <p class="text-sm text-gray-500">Main Livelihood</p>
                                        <p class="fetch-field" data-field="main_livelihood"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_main_livelihood_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Type of Commodity</p>
                                        <p class="fetch-field" data-field="type_commodity"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_type_commodity_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Number of Farm Parcel</p>
                                        <p class="fetch-field" data-field="no_farmparcel"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_no_farmparcel_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Total Farm Area (Hectare)</p>
                                        <p class="fetch-field" data-field="total_farmarea"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_total_farmarea_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">With Ancestral Domain?</p>
                                        <p class="fetch-field" data-field="with_ancestral"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_with_ancestral_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Ownership Number</p>
                                        <p class="fetch-field" data-field="ownership"></p>
                                        <input type="text" class="hidden update-field" id="update_ownership_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Agrarian Reform Beneficiary?</p>
                                        <p class="fetch-field" data-field="agrarian"></p>
                                        <input type="text" class="hidden update-field" id="update_agrarian_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Farm Location (Barangay)</p>
                                        <p class="fetch-field" data-field="farmer_location_brgy"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_farmer_location_brgy_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Municipality/City</p>
                                        <p class="fetch-field" data-field="farmer_location_municipal"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_farmer_location_municipal_step4" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Ownership Type</p>
                                        <p class="fetch-field" data-field="farmer_location_ownership"></p>
                                        <input type="text" class="hidden update-field"
                                            id="update_farmer_location_ownership_step4" />
                                    </div>
                                </div>
                                <!-- Navigation Buttons -->
                                <div class="mt-6 flex justify-between gap-2">
                                    <button
                                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        onclick="prevStep()">Back</button>
                                </div>
                            </div>
                        </div>
                        <!-- Footer -->
                        <div class="flex justify-end p-2 border-t border-green-200">
                            <button type="button" id="editBtn"
                                class="px-2 w-12 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600">Edit</button>
                            <div>
                                <button type="button" id="cancelBtn"
                                    class="hidden px-2 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600">Cancel</button>
                                <button type="submit" id="updateBtn"
                                    class="hidden px-2 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600">Update</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>



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
    <!-- farmer selector dropdown -->
    <script>
        function initDropdown(buttonId, menuId, labelId, optionClass, defaultText) {
            const dropdownButton = document.getElementById(buttonId);
            const dropdownMenu = document.getElementById(menuId);
            const dropdownLabel = document.getElementById(labelId);
            const checkboxes = document.querySelectorAll("." + optionClass);

            // Toggle dropdown
            dropdownButton.addEventListener("click", () => {
                dropdownMenu.classList.toggle("hidden");
            });

            // Close when clicking outside
            document.addEventListener("click", (e) => {
                if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.add("hidden");
                    updateLabel(); // update label when dropdown closes
                }
            });

            // Update label dynamically
            function updateLabel() {
                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                dropdownLabel.textContent = selected.length > 0 ? selected.join(", ") : defaultText;
            }
        }

        // Initialize each dropdown separately
        initDropdown("dropdownButton-crops", "dropdownMenu-crops", "dropdownLabel-crops", "cropOption-crops", "Select crops");
        initDropdown("dropdownButton-farmerworker", "dropdownMenu-farmerworker", "dropdownLabel-farmerworker", "cropOption-farmerworker", "Select Farmerworkers");
        initDropdown("dropdownButton-fisherfolk", "dropdownMenu-fisherfolk", "dropdownLabel-fisherfolk", "cropOption-fisherfolk", "Select Fisherfolk");
        initDropdown("dropdownButton-youth", "dropdownMenu-youth", "dropdownLabel-youth", "cropOption-youth", "Select Agri-Youth Type");
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

    <!-- modal edit,update -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const editBtn = document.getElementById("editBtn");
            const cancelBtn = document.getElementById("cancelBtn");
            const updateBtn = document.getElementById("updateBtn");
            const farmerIdInput = document.getElementById("farmerId");

            // Navigation steps
            let currentStep = 1;
            const totalSteps = 4;
            function showStep(step) {
                for (let i = 1; i <= totalSteps; i++) {
                    document.getElementById(`step${i}`).style.display = i === step ? "block" : "none";
                }
                currentStep = step;
            }
            window.nextStep = () => { if (currentStep < totalSteps) showStep(currentStep + 1); }
            window.prevStep = () => { if (currentStep > 1) showStep(currentStep - 1); }

            // Fetch all fields
            const fetchFields = document.querySelectorAll(".fetch-field");
            const updateFields = document.querySelectorAll(".update-field");

            // Store original values
            let originalValues = {};

            // Mapping fetch-field data-field => update-field id
            const updateMapping = {};
            updateFields.forEach(input => {
                const fieldName = input.id.replace(/^update_/, '').replace(/_step\d+$/, '');
                updateMapping[fieldName] = input.id;
            });

            // Fetch farmer data
            window.fetchFarmerData = async (id) => {
                try {
                    const res = await fetch(`get_farmer.php?id=${id}`, { cache: "no-cache" });
                    const text = await res.text();
                    let result;
                    try {
                        result = JSON.parse(text.trim());
                    } catch (err) {
                        console.error("Invalid JSON:", text);
                        alert("❌ Invalid response format");
                        return;
                    }

                    if (!result.success) { alert(result.message || "Failed to fetch"); return; }

                    const data = result.data;
                    farmerIdInput.value = id;

                    fetchFields.forEach(p => {
                        const field = p.dataset.field;
                        const value = data[field] && data[field].trim() !== "" ? data[field] : "N/A";
                        p.textContent = value;
                        // Also populate update input
                        const inputId = updateMapping[field];
                        if (inputId) document.getElementById(inputId).value = value;
                    });

                    // Update photo
                    const photoEl = document.getElementById("farmerPhoto");
                    if (photoEl) {
                        photoEl.src = data.photo && data.photo.trim() !== "" ? `uploads/${data.photo}` : "assets/default-avatar.png";
                    }

                } catch (err) {
                    console.error(err);
                    alert("❌ Fetch error. Check network or server.");
                }
            }

            // Make fields editable
            function makeEditable() {
                fetchFields.forEach(p => p.classList.add("hidden"));
                updateFields.forEach(input => input.classList.remove("hidden"));
                editBtn.classList.add("hidden");
                cancelBtn.classList.remove("hidden");
                updateBtn.classList.remove("hidden");
            }

            // Cancel editing
            function cancelEdit() {
                fetchFields.forEach(p => p.classList.remove("hidden"));
                updateFields.forEach(input => input.classList.add("hidden"));
                editBtn.classList.remove("hidden");
                cancelBtn.classList.add("hidden");
                updateBtn.classList.add("hidden");

                // Restore original values
                updateFields.forEach(input => {
                    const fieldName = input.id.replace(/^update_/, '');
                    const original = originalValues[fieldName];
                    if (original !== undefined) input.value = original;
                });
            }

            editBtn.addEventListener("click", () => {
                // Save original values
                updateFields.forEach(input => { originalValues[input.id.replace(/^update_/, '')] = input.value; });
                makeEditable();
            });

            cancelBtn.addEventListener("click", cancelEdit);

            updateBtn.addEventListener("click", async () => {
                const updatedData = { id: farmerIdInput.value };
                updateFields.forEach(input => {
                    const fieldName = input.id.replace(/^update_/, '').replace(/_step\d+$/, '');
                    updatedData[fieldName] = input.value.trim();
                });

                try {
                    const res = await fetch("updatefarmer.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(updatedData)
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert("✅ Farmer updated successfully!");
                        // Update <p> fields to new values
                        fetchFields.forEach(p => {
                            const field = p.dataset.field;
                            const inputId = updateMapping[field];
                            if (inputId) p.textContent = document.getElementById(inputId).value;
                        });
                        cancelEdit();
                    } else {
                        alert("❌ Update failed: " + (result.message || "Unknown error"));
                    }
                } catch (err) {
                    console.error(err);
                    alert("❌ Something went wrong while updating!");
                }
            });

        });
    </script>
    <!-- modal -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            async function fetchFarmerData(id) {
                try {
                    const response = await fetch(`get_farmer.php?id=${id}`, { cache: "no-cache" });
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

                    const text = await response.text();
                    let result;

                    try {
                        result = JSON.parse(text.trim());
                    } catch (jsonError) {
                        console.error("Invalid JSON from server:", text);
                        alert("❌ Invalid response format from server.");
                        return;
                    }

                    if (result.success) {
                        const data = result.data;

                        // ✅ All data fields (excluding commodity ones)
                        const fields = [
                            "date", "reference", "s_name", "f_name", "m_name", "e_name", "gender",
                            "house", "sitio", "brgy", "municipal", "province", "region",
                            "mobile", "landline", "dob", "country", "province_birth",
                            "municipality_birth", "mother_maiden", "household_head", "relationship",
                            "no_livinghousehold", "no_male", "no_female", "education", "pwd", "non_farming", "farming",
                            "with_gov", "specify_id", "id_no", "member_indig", "assoc_member",
                            "specify_assoc", "main_livelihood",
                            "no_farmparcel", "total_farmarea", "with_ancestral",
                            "ownership", "farmer_location_brgy", "farmer_location_municipal",
                            "farmer_location_ownership", "agrarian", "status", "photo"
                        ];

                        // ✅ Fill all matching fields by ID or data-field
                        fields.forEach(field => {
                            const value = (data[field] && data[field].trim() !== "") ? data[field] : "N/A";

                            // Try ID first
                            const idEl = document.getElementById(field);
                            if (idEl) idEl.textContent = value;

                            // Try data-field second
                            const dataEl = document.querySelector(`[data-field="${field}"]`);
                            if (dataEl) dataEl.textContent = value;
                        });

                        // ✅ Type of Commodity Combination (unchanged)
                        const commodityEl = document.querySelector('[data-field="type_commodity"]');
                        if (commodityEl) {
                            const commodities = [
                                data.for_farmer?.trim(),
                                data.for_farmerworker?.trim(),
                                data.for_fisherfolk?.trim(),
                                data.for_agri?.trim()
                            ].filter(v => v && v !== "");

                            commodityEl.textContent = commodities.length > 0
                                ? commodities.join(", ")
                                : "N/A";

                            console.log("✅ Type of Commodity:", commodities);
                        }

                        // ✅ Handle photo
                        const photoEl = document.getElementById("farmerPhoto");
                        if (photoEl) {
                            photoEl.src = (data.photo && data.photo.trim() !== "")
                                ? `uploads/${data.photo}`
                                : "assets/default-avatar.png";
                        }

                    } else {
                        alert("❌ " + (result.message || "Failed to load farmer data."));
                    }
                } catch (error) {
                    console.error("❌ Fetch error:", error);
                    alert("❌ Failed to load farmer data. Please check your network or server response.");
                }
            }

            // Make it available globally for your buttons
            window.fetchFarmerData = fetchFarmerData;
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
    <!-- reload-page -->
    <script>
        const navEntries = performance.getEntriesByType("navigation");
        if (navEntries.length > 0 && navEntries[0].type === "reload") {
            sessionStorage.removeItem('selectedIds');
            sessionStorage.removeItem('allSelectedAcrossPages');
        }

        document.addEventListener('DOMContentLoaded', () => {
            let selectedIds = new Set(JSON.parse(sessionStorage.getItem('selectedIds') || "[]"));
            let allSelectedAcrossPages = sessionStorage.getItem('allSelectedAcrossPages') === "true";

            // ... (rest of your code stays the same)
        });
        document.addEventListener('DOMContentLoaded', () => {
            let selectedIds = new Set(JSON.parse(sessionStorage.getItem('selectedIds') || "[]"));
            let allSelectedAcrossPages = sessionStorage.getItem('allSelectedAcrossPages') === "true";

            const selectAllCheckbox = document.getElementById('selectAll');
            const tableBody = document.querySelector('#yourTableId tbody');

            const getRowId = (cb) => cb.dataset.id ?? cb.value;

            // Restore checkbox states
            const restoreCheckboxStates = () => {
                document.querySelectorAll('.rowCheckbox').forEach(cb => {
                    cb.checked = selectedIds.has(String(getRowId(cb)));
                });
                const allRows = document.querySelectorAll('.rowCheckbox');
                selectAllCheckbox.checked = allRows.length > 0 && Array.from(allRows).every(cb => cb.checked);
            };

            const saveSelection = () => {
                sessionStorage.setItem('selectedIds', JSON.stringify(Array.from(selectedIds)));
                sessionStorage.setItem('allSelectedAcrossPages', allSelectedAcrossPages ? "true" : "false");
            };

            // Select All
            selectAllCheckbox.addEventListener('change', async function () {
                const visible = Array.from(document.querySelectorAll('.rowCheckbox'));
                if (this.checked) {
                    try {
                        const res = await fetch('archive.php?fetch_all=1', { credentials: 'same-origin' });
                        const json = await res.json();
                        if (json.success && Array.isArray(json.ids)) {
                            selectedIds = new Set(json.ids.map(id => String(id)));
                            visible.forEach(cb => cb.checked = true);
                            allSelectedAcrossPages = true;
                            saveSelection();
                        } else {
                            alert('Failed to fetch all IDs.');
                            this.checked = false;
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Error fetching all IDs.');
                        this.checked = false;
                    }
                } else {
                    selectedIds.clear();
                    allSelectedAcrossPages = false;
                    visible.forEach(cb => cb.checked = false);
                    saveSelection();
                }
            });

            // Individual row
            document.addEventListener('change', (e) => {
                if (!e.target.classList.contains('rowCheckbox')) return;
                const id = String(getRowId(e.target));
                if (e.target.checked) selectedIds.add(id);
                else {
                    selectedIds.delete(id);
                    allSelectedAcrossPages = false;
                    selectAllCheckbox.checked = false;
                }
                saveSelection();
            });

            // Archive action (same as your existing)
            document.addEventListener('click', async (e) => {
                const target = e.target.closest('.archiveAction');
                if (!target) return;
                e.preventDefault();

                const idsToArchive = selectedIds.size > 0
                    ? Array.from(selectedIds)
                    : target.dataset.id ? [String(target.dataset.id)] : [];

                if (idsToArchive.length === 0) {
                    alert("No records selected.");
                    return;
                }

                if (!confirm(`Archive ${idsToArchive.length} record(s)?`)) return;

                try {
                    const res = await fetch('archive.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: idsToArchive })
                    });
                    const json = await res.json();

                    if (json.success) {
                        (json.ids || []).forEach(id => {
                            const row = document.querySelector(`tr[data-id='${id}']`);
                            if (row) row.remove();
                            selectedIds.delete(String(id));
                        });

                        allSelectedAcrossPages = false;
                        selectAllCheckbox.checked = false;
                        saveSelection();

                        if (tableBody) {
                            tableBody.querySelectorAll('tr').forEach((tr, index) => {
                                const numberCell = tr.querySelector('.rowNumber');
                                if (numberCell) numberCell.textContent = index + 1;
                            });
                        }

                        if ((json.ids || []).length > 0) {
                            const msg = document.createElement('div');
                            const names = (json.names || []).join(", ");
                            msg.textContent = `Archived ${json.ids.length} record(s): ${names}`;
                            msg.className = 'bg-green-100 text-green-700 p-2 rounded mb-2';
                            tableBody.parentElement.prepend(msg);
                            setTimeout(() => msg.remove(), 4000);
                        }

                        if (!tableBody.querySelector('tr')) {
                            const noRow = document.createElement('tr');
                            noRow.innerHTML = `<td colspan="100%" class="text-center py-4">No records found.</td>`;
                            tableBody.appendChild(noRow);
                        }

                    } else {
                        alert('Archive failed: ' + (json.error || 'Unknown error'));
                    }
                } catch (err) {
                    console.error(err);
                    alert('Request failed.');
                }
            });

            restoreCheckboxStates();
        });

    </script>
    <!-- liveSearch -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('simple-search');
            const clearBtn = document.getElementById('clearBtn');
            const resultsTbody = document.getElementById('results');

            // Save original table rows once DOM is ready
            let originalRows = resultsTbody.innerHTML;

            // Event listener for typing
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim();
                clearBtn.classList.toggle('hidden', query === '');

                if (query === '') {
                    // ✅ Restore original rows from memory
                    resultsTbody.innerHTML = originalRows;
                    history.replaceState(null, '', window.location.pathname);
                } else {
                    debounceLiveSearch(query);
                }
            });

            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            });

            // Debounce setup
            let debounceTimeout;
            function debounceLiveSearch(query) {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(() => {
                    liveSearch(query);
                }, 300);
            }

            function liveSearch(query) {
                fetch(`search.php?search=${encodeURIComponent(query)}`)
                    .then(res => res.text())
                    .then(data => {
                        resultsTbody.innerHTML = data;
                        if (query !== '') {
                            history.replaceState(null, '', `?search=${encodeURIComponent(query)}`);
                        } else {
                            history.replaceState(null, '', window.location.pathname);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        resultsTbody.innerHTML = '<tr><td colspan="12" class="p-2 text-red-500">Error loading results.</td></tr>';
                    });
            }
        });
    </script>
    <!-- update status auto reload -->
    <script>
        document.addEventListener("click", (e) => {
            const toggle = e.target.closest(".dropdown-toggle");
            const menu = e.target.closest(".dropdown-menu");
            const statusOption = e.target.closest(".statusOption");

            // ✅ If clicking a dropdown toggle
            if (toggle) {
                // Close all other dropdowns first
                document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.add("hidden"));

                // Toggle the clicked one
                const dropdown = toggle.nextElementSibling;
                if (dropdown && dropdown.classList.contains("dropdown-menu")) {
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
                    updateStatus(newStatus); // your function call
                }

                // ✅ Find its related dropdown to close it
                const dropdownMenu = statusOption.closest(".dropdown-menu");
                const statusButton = document.getElementById("statusDropdownButton");

                if (dropdownMenu) {
                    // Try Flowbite hide method
                    if (typeof Dropdown !== "undefined" && statusButton) {
                        const dropdownInstance = new Dropdown(dropdownMenu, statusButton);
                        dropdownInstance.hide();
                    }

                    // Force hide
                    dropdownMenu.classList.add("hidden");
                    dropdownMenu.classList.remove("block");
                    dropdownMenu.style.display = "none";
                }

                if (statusButton) {
                    statusButton.setAttribute("aria-expanded", "false");
                    statusButton.blur();
                }

                // ✅ Optional: clear all selected checkboxes
                document.querySelectorAll(".rowCheckbox").forEach(cb => cb.checked = false);
                const selectAll = document.getElementById("selectAll");
                if (selectAll) selectAll.checked = false;
                if (statusButton) statusButton.disabled = true;

                return;
            }

            // ✅ Click outside any dropdown → close all
            if (!menu) {
                document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.add("hidden"));
            }
        });
    </script>
    <!-- update status -->
    <script>
        // Selectors
        const statusButton = document.getElementById("statusDropdownButton");
        const checkboxes = document.querySelectorAll(".rowCheckbox");
        const selectAll = document.getElementById("selectAll");
        const statusInfo = document.getElementById("statusUpdateInfo");
        const dropdownMenu = document.getElementById("statusDropdown");

        // Initialize Flowbite dropdown instance (if available)
        let dropdownInstance = null;
        if (typeof Dropdown !== "undefined" && statusButton && dropdownMenu) {
            dropdownInstance = new Dropdown(dropdownMenu, statusButton);
        }

        // Enable/disable Update button based on selection
        function toggleStatusButton() {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            statusButton.disabled = !anyChecked;
        }

        // Checkbox listeners
        checkboxes.forEach(cb => cb.addEventListener("change", toggleStatusButton));
        if (selectAll) {
            selectAll.addEventListener("change", function () {
                checkboxes.forEach(cb => cb.checked = this.checked);
                toggleStatusButton();
            });
        }

        // --- Update Status Function ---
        let isUpdating = false;

        function updateStatus(status) {
            if (isUpdating) return; // ✅ Prevent multiple submissions
            isUpdating = true;

            const selectedIds = Array.from(checkboxes)
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
                body: JSON.stringify({ ids: selectedIds, status: status })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // ✅ Update table instantly
                        data.ids.forEach(id => {
                            const row = document.querySelector(`tr[data-id='${id}']`);
                            if (row) {
                                const statusCell = row.querySelector(".statusCell");
                                if (statusCell) {
                                    statusCell.textContent = data.status;
                                    statusCell.classList.remove(
                                        "text-yellow-500",
                                        "text-blue-500",
                                        "text-green-500"
                                    );
                                    if (data.status === "Pending") statusCell.classList.add("text-yellow-500");
                                    if (data.status === "Process") statusCell.classList.add("text-blue-500");
                                    if (data.status === "Registered") statusCell.classList.add("text-green-500");
                                }
                            }
                        });

                        // ✅ Show success message
                        const msg = document.createElement("div");
                        msg.textContent = "✅ Status updated successfully!";
                        msg.className = "text-green-600 font-semibold mt-2";
                        statusInfo.innerHTML = "";
                        statusInfo.appendChild(msg);

                        // ✅ Reset checkboxes
                        checkboxes.forEach(cb => cb.checked = false);
                        if (selectAll) selectAll.checked = false;
                        toggleStatusButton();

                        // ✅ Close dropdown (Flowbite or fallback)
                        if (dropdownInstance) {
                            dropdownInstance.hide();
                        }
                        dropdownMenu.classList.add("hidden");

                        // Auto-hide message
                        setTimeout(() => msg.remove(), 2500);
                    } else {
                        const errMsg = document.createElement("div");
                        errMsg.textContent = "❌ Failed to update status: " + data.error;
                        errMsg.className = "text-red-600 font-semibold mt-2";
                        statusInfo.innerHTML = "";
                        statusInfo.appendChild(errMsg);
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    const errMsg = document.createElement("div");
                    errMsg.textContent = "❌ Failed to update status. Please try again.";
                    errMsg.className = "text-red-600 font-semibold mt-2";
                    statusInfo.innerHTML = "";
                    statusInfo.appendChild(errMsg);
                })
                .finally(() => {
                    // ✅ Unlock after request completes
                    isUpdating = false;
                });
        }

        if (isUpdating) return; // ✅ Prevent multiple submissions
        isUpdating = true;

        const selectedIds = Array.from(checkboxes)
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
            body: JSON.stringify({ ids: selectedIds, status: status })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ✅ Update table instantly
                    data.ids.forEach(id => {
                        const row = document.querySelector(`tr[data-id='${id}']`);
                        if (row) {
                            const statusCell = row.querySelector(".statusCell");
                            if (statusCell) {
                                statusCell.textContent = data.status;
                                statusCell.classList.remove(
                                    "text-yellow-500",
                                    "text-blue-500",
                                    "text-green-500",
                                    "text-red-500"
                                );
                                if (data.status === "Pending") statusCell.classList.add("text-yellow-500");
                                if (data.status === "Process") statusCell.classList.add("text-blue-500");
                                if (data.status === "Registered") statusCell.classList.add("text-green-500");
                                if (data.status === "Archived") statusCell.classList.add("text-red-500");
                            }
                        }
                    });

                    // ✅ Show success message
                    const msg = document.createElement("div");
                    msg.textContent = "✅ Status updated successfully!";
                    msg.className = "text-green-600 font-semibold mt-2";
                    statusInfo.innerHTML = "";
                    statusInfo.appendChild(msg);

                    // ✅ Reset checkboxes
                    checkboxes.forEach(cb => cb.checked = false);
                    if (selectAll) selectAll.checked = false;
                    toggleStatusButton();

                    // ✅ Close dropdown (Flowbite or fallback)
                    if (dropdownInstance) {
                        dropdownInstance.hide();
                    }
                    dropdownMenu.classList.add("hidden");

                    // Auto-hide message
                    setTimeout(() => msg.remove(), 2500);
                } else {
                    const errMsg = document.createElement("div");
                    errMsg.textContent = "❌ Failed to update status: " + data.error;
                    errMsg.className = "text-red-600 font-semibold mt-2";
                    statusInfo.innerHTML = "";
                    statusInfo.appendChild(errMsg);
                }
            })
            .catch(err => {
                console.error("Error:", err);
                const errMsg = document.createElement("div");
                errMsg.textContent = "❌ Failed to update status. Please try again.";
                errMsg.className = "text-red-600 font-semibold mt-2";
                statusInfo.innerHTML = "";
                statusInfo.appendChild(errMsg);
            })
            .finally(() => {
                // ✅ Unlock after request completes
                isUpdating = false;
            });
        }

        // Initialize button
        toggleStatusButton();

        // 🔹 When clicking a status option (Pending / Registered / Process)
        document.querySelectorAll(".statusOption").forEach(option => {
            option.addEventListener("click", () => {
                const newStatus = option.dataset.status || option.textContent.trim();

                // Update immediately
                updateStatus(newStatus);

                // ✅ Immediately close dropdown visually
                if (dropdownInstance) dropdownInstance.hide();
                dropdownMenu.classList.add("hidden"); // fallback
            });
        });
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
                        <small>${data.time}</small>
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

    <script src="../js/tailwind.config.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>