<?php
include "../db/conn.php";
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
                        <button type="button"
                            class="flex items-center w-full p-2 text-base font-medium text-[#166534] rounded-lg bg-green-50 border-l-4 border-[#E6B800] group hover:bg-green-100 transition duration-150"
                            aria-controls="dropdown-datalist" data-collapse-toggle="dropdown-datalist">
                            <svg class="w-6 h-6 text-[#166534]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="flex-1 ml-3 text-left whitespace-nowrap">Data List</span>
                            <svg class="w-4 h-4 ml-auto transform group-data-[state=open]:rotate-180 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown -->
                        <ul id="dropdown-datalist" class="hidden py-2 space-y-1 ml-8">
                            <li id="allFarmersBtn" class="cursor-pointer" onclick="showTable('allFarmersTable')">
                                <div
                                    class="flex items-center p-2 text-sm font-medium text-blue-700 rounded-lg hover:bg-blue-100">
                                    <svg class="w-5 h-5 text-blue-700 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 3h14v2H3V3zm0 6h14v2H3V9zm0 6h14v2H3v-2z" />
                                    </svg>
                                    All Farmers
                                </div>
                            </li>
                            <li id="unregisteredBtn" class="cursor-pointer" onclick="showTable('unregisteredTable')">
                                <div
                                    class="flex items-center p-2 text-sm font-medium text-[#999b1c] rounded-lg hover:bg-yellow-100">
                                    <svg class="w-6 h-6 text-[#999b1c] mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <!-- Person / Farmer silhouette -->
                                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm-7 9v-2a5 5 0 0110 0v2H5z" />

                                        <!-- Clock / Pending indicator -->
                                        <path
                                            d="M17 12a5 5 0 100 10 5 5 0 000-10zm.5 5H17V14a.5.5 0 00-1 0v4h1.5a.5.5 0 000-1z" />
                                    </svg>

                                    Pending Farmers
                                </div>
                            </li>
                            <li id="registeredBtn" class="cursor-pointer" onclick="showTable('registeredTable')">
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
        <main class="md:ml-64 pt-20 flex flex-col">
            <div class="w-[100-px] flex items-center justify-center mt-20 ml-[10px] h-[400px] mb-18">
                <!-- Sidebar -->
                <div
                    class="p-2 w-[200px] h-full bg-white border-l border-t border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="py-3 px-4">
                        <div class="flex items-center justify-center">
                            <img src="../img/profile.png" class="h-10 w-10" alt="">
                        </div>
                        <span class="block text-sm ml-13 font-semibold text-gray-900 dark:text-white">Admin</span>
                        <span class="block text-sm ml-4 text-gray-900 truncate dark:text-white">admin@gmail.com</span>
                    </div>

                    <ul>
                        <!-- Add Staff Account -->
                        <li>
                            <a href="" id="btnAddUser"
                                class="nav-item flex items-center gap-3 p-3 text-base font-medium text-gray-900 rounded-lg 
            transition-all duration-200 hover:bg-green-100 hover:text-green-800 dark:hover:bg-gray-700 dark:text-white group active">
                                <svg aria-hidden="true"
                                    class="nav-icon w-6 h-6 text-green-600 transition duration-200 group-hover:text-green-800"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                    <path fill-rule="evenodd"
                                        d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span>Add Staff Account</span>
                            </a>
                        </li>

                        <!-- User List -->
                        <li>
                            <a href="#" id="btnUserList"
                                class="nav-item flex items-center gap-3 p-3 text-base font-medium text-gray-900 rounded-lg 
            transition-all duration-200 hover:bg-green-100 hover:text-green-800 dark:hover:bg-gray-700 dark:text-white group">
                                <svg aria-hidden="true"
                                    class="nav-icon w-6 h-6 text-gray-400 transition duration-200 group-hover:text-green-800"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z">
                                    </path>
                                </svg>
                                <span>User List</span>
                            </a>
                        </li>

                        <!-- Login History -->
                        <li>
                            <a href="#" id="btnLoginHistory"
                                class="nav-item flex items-center gap-3 p-3 text-base font-medium text-gray-900 rounded-lg 
            transition-all duration-200 hover:bg-green-100 hover:text-green-800 dark:hover:bg-gray-700 dark:text-white group">
                                <svg aria-hidden="true"
                                    class="nav-icon w-6 h-6 text-gray-400 transition duration-200 group-hover:text-green-800"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 8V6a1 1 0 10-2 0v5a1 1 0 00.293.707l3 3a1 1 0 101.414-1.414L11 10z">
                                    </path>
                                </svg>
                                <span>Login History</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- MAIN CONTENT -->

                <div
                    class="p-4 w-[490px] flex justify-center items-center h-[400px] bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow">

                    <!-- ADD STAFF -->
                    <div id="addAccountSection" class="w-72 flex flex-col items-center h-auto">
                        <div class="p-8 w-full">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 text-center">Create Account
                                for Staff</h2>
                            <form id="addUserForm" action="add_user.php" method="POST" class="space-y-4">
                                <div>
                                    <label for="fullname"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Full
                                        Name</label>
                                    <input type="text" id="fullname" name="fullname" required
                                        class="w-full h-8 border rounded-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="email"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                    <input type="email" id="email" name="email" required
                                        class="w-full h-8 border rounded-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="password"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                                    <input type="password" id="password" name="password" required
                                        class="w-full h-8 border rounded-lg text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button type="submit"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-1 rounded-lg">Add
                                    User</button>
                            </form>
                        </div>
                    </div>

                    <!-- USER LIST -->
                    <div id="userListSection" class="hidden w-full h-full overflow-y-auto">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">User List</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">#</th>
                                        <th class="px-4 py-3">Full Name</th>
                                        <th class="px-4 py-3">Email</th>
                                        <th class="px-4 py-3">Role</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $usersQuery = "SELECT * FROM users ORDER BY id DESC";
                                    $usersResult = $conn->query($usersQuery);
                                    if ($usersResult && $usersResult->num_rows > 0):
                                        $count = 1;
                                        while ($user = $usersResult->fetch_assoc()):
                                            $statusColor = ($user['status'] === 'Active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            ?>
                                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">
                                                    <?= $count++; ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= htmlspecialchars($user['fullname']); ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= htmlspecialchars($user['email']); ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= htmlspecialchars($user['role']); ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <span
                                                        class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColor; ?>">
                                                        <?= $user['status']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <button
                                                        class="text-blue-600 hover:text-blue-800 font-medium text-sm mr-2">Edit</button>
                                                    <button
                                                        class="text-red-600 hover:text-red-800 font-medium text-sm">Delete</button>
                                                </td>
                                            </tr>
                                            <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-gray-500 dark:text-gray-400">No
                                                users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- LOGIN HISTORY -->
                    <div id="loginHistorySection" class="hidden w-full h-full overflow-y-auto">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Login History</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">#</th>
                                        <th class="px-4 py-3">User</th>
                                        <th class="px-4 py-3">Email</th>
                                        <th class="px-4 py-3">Login Time</th>
                                        <th class="px-4 py-3">Logout Time</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $historyQuery = "
                                        SELECT lh.id, u.username, lh.email, lh.login_time, lh.logout_time, lh.status
                                        FROM login_history lh
                                        LEFT JOIN users u ON lh.user_id = u.id
                                        ORDER BY lh.login_time DESC
                                    ";
                                    $historyResult = $conn->query($historyQuery);
                                    if ($historyResult && $historyResult->num_rows > 0):
                                        $count = 1;
                                        while ($lh = $historyResult->fetch_assoc()):
                                            $statusColor = 'bg-gray-100 text-gray-800';
                                            if (stripos($lh['status'], 'Success') !== false)
                                                $statusColor = 'bg-green-100 text-green-800';
                                            elseif (stripos($lh['status'], 'Failed') !== false)
                                                $statusColor = 'bg-red-100 text-red-800';
                                            elseif (stripos($lh['status'], 'Logout') !== false)
                                                $statusColor = 'bg-yellow-100 text-yellow-800';
                                            ?>
                                            <tr class=" border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">
                                                    <?= $count++; ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= htmlspecialchars($lh['username'] ?? 'Unknown'); ?>
                                                </td>
                                                <td class="px-4 py-2"><?= htmlspecialchars($lh['email']); ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= $lh['login_time'] ? date('Y-m-d h:i A', strtotime($lh['login_time'])) : '-'; ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <?= $lh['logout_time'] ? date('Y-m-d h:i A', strtotime($lh['logout_time'])) : '-'; ?>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <span
                                                        class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColor; ?>">
                                                        <?= htmlspecialchars($lh['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-gray-500 dark:text-gray-400">No
                                                login history found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        // Sections
                        const addAccountSection = document.getElementById("addAccountSection");
                        const userListSection = document.getElementById("userListSection");
                        const loginHistorySection = document.getElementById("loginHistorySection");

                        // Menu links
                        const btnAddUser = document.getElementById("btnAddUser");
                        const btnUserList = document.getElementById("btnUserList");
                        const btnLoginHistory = document.getElementById("btnLoginHistory");

                        function hideAllSections() {
                            addAccountSection.classList.add("hidden");
                            userListSection.classList.add("hidden");
                            loginHistorySection.classList.add("hidden");
                        }

                        function resetActiveMenu() {
                            btnAddUser.classList.remove("active");
                            btnUserList.classList.remove("active");
                            btnLoginHistory.classList.remove("active");
                        }

                        function showSection(section, menuBtn) {
                            hideAllSections();
                            resetActiveMenu();
                            section.classList.remove("hidden");
                            menuBtn.classList.add("active");
                        }

                        btnAddUser.addEventListener("click", (e) => {
                            e.preventDefault();
                            showSection(addAccountSection, btnAddUser);
                        });

                        btnUserList.addEventListener("click", (e) => {
                            e.preventDefault();
                            showSection(userListSection, btnUserList);
                        });

                        btnLoginHistory.addEventListener("click", (e) => {
                            e.preventDefault();
                            showSection(loginHistorySection, btnLoginHistory);
                        });

                        showSection(addAccountSection, btnAddUser);
                    });
                </script>
            </div>
        </main>
    </div>
    <script src="../js/tailwind.config.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>