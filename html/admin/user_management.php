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
// ---------------------------
// CSRF
// ---------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ---------------------------
// Helpers
// ---------------------------
function json_response($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

/* ===========================================================
   📌 FETCH USERS (Staff or Enumerator)
   GET: ?fetch=users&role=staff|enumerator
   =========================================================== */
if (isset($_GET['fetch']) && $_GET['fetch'] === 'users') {

    $role = strtolower($_GET['role'] ?? 'staff');
    if (!in_array($role, ['staff', 'enumerator'])) {
        $role = 'staff';
    }

    $stmt = $conn->prepare("
        SELECT id, fullname, username, email, assigned_area, status, role, created_at
        FROM user_accounts
        WHERE role = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $role);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response(['success' => true, 'data' => $rows]);
}

/* ===========================================================
   📌 ADD USER - FIXED VERSION
   =========================================================== */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['ajax'], $_POST['action']) &&
    $_POST['ajax'] == '1' &&
    $_POST['action'] === 'add_user'
) {

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.']);
    }

    $role = strtolower(post('role'));
    if (!in_array($role, ['staff', 'enumerator'])) {
        $role = 'staff';
    }

    $fullname = post('fullname');
    $username = post('username');
    $email = post('email');
    $password = post('password');
    $assigned_area = post('assigned_area');

    if (!$fullname || !$username || !$email || !$password) {
        json_response(['success' => false, 'message' => 'Please fill all required fields.']);
    }

    // Duplicate check
    $dup = $conn->prepare("SELECT id FROM user_accounts WHERE email = ? OR username = ?");
    $dup->bind_param("ss", $email, $username);
    $dup->execute();
    $dup->store_result();

    if ($dup->num_rows > 0) {
        json_response(['success' => false, 'message' => 'Email or username already exists.']);
    }
    $dup->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);

    if ($role === 'enumerator') {
        $stmt = $conn->prepare("
            INSERT INTO staff_account(fullname, email, username, password, role, assigned_area, status, created_at) 
            VALUES (?, ?, ?, ?, 'enumerator', ?, 'active', NOW())
        ");
        $stmt->bind_param("sssss", $fullname, $email, $username, $hash, $assigned_area);
    } else {
        // FIXED: Added email field to staff insert
        $stmt = $conn->prepare("
            INSERT INTO user_accounts(fullname, email, username, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, 'staff', 'active', NOW())
        ");
        $stmt->bind_param("ssss", $fullname, $email, $username, $hash);
    }

    if ($stmt->execute()) {
        $stmt->close();
        json_response(['success' => true, 'message' => 'User created successfully.']);
    } else {
        $error = $conn->error;
        $stmt->close();
        json_response(['success' => false, 'message' => "DB Error: " . $error]);
    }
}
/* ===========================================================
   📌 DELETE USER
   =========================================================== */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['ajax'], $_POST['action']) &&
    $_POST['ajax'] == '1' &&
    $_POST['action'] === 'delete_user'
) {

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.']);
    }

    $id = (int) $_POST['id'];

    if (!$id) {
        json_response(['success' => false, 'message' => 'Invalid ID.']);
    }

    if ($_SESSION['user_id'] == $id) {
        json_response(['success' => false, 'message' => 'Cannot delete your own account.']);
    }

    $stmt = $conn->prepare("DELETE FROM user_accounts WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'User deleted successfully.']);
    }

    json_response(['success' => false, 'message' => "DB Error: " . $conn->error]);
}

/* ===========================================================
   📌 UPDATE USER
   =========================================================== */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['ajax'], $_POST['action']) &&
    $_POST['ajax'] == '1' &&
    $_POST['action'] === 'update_user'
) {

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.']);
    }

    $id = (int) $_POST['id'];
    if (!$id) {
        json_response(['success' => false, 'message' => 'Invalid ID.']);
    }

    $fullname = post('fullname');
    $username = post('username');
    $status = post('status') ?: 'active';
    $assigned_area = post('assigned_area');

    if (!$fullname || !$username) {
        json_response(['success' => false, 'message' => 'Please fill required fields.']);
    }

    // Duplicate username check
    $dup = $conn->prepare("SELECT id FROM user_accounts WHERE username = ? AND id != ?");
    $dup->bind_param("si", $username, $id);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        json_response(['success' => false, 'message' => 'Username already taken.']);
    }

    // What is the user role?
    $role_stmt = $conn->prepare("SELECT role FROM user_accounts WHERE id = ?");
    $role_stmt->bind_param("i", $id);
    $role_stmt->execute();
    $role = ($role_stmt->get_result()->fetch_assoc()['role']) ?? 'staff';

    if ($role === 'enumerator') {
        $stmt = $conn->prepare("
            UPDATE user_accounts 
            SET fullname = ?, username = ?, status = ?, assigned_area = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $fullname, $username, $status, $assigned_area, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE user_accounts 
            SET fullname = ?, username = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $fullname, $username, $status, $id);
    }

    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'User updated successfully.']);
    }

    json_response(['success' => false, 'message' => "DB Error: " . $conn->error]);
}


// ---------------------------
// Otherwise load HTML page
// ---------------------------
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>User Management</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen text-sm">
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

                <li>
                    <a href="datalist.php"
                        class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg hover:bg-green-100 dark:hover:bg-gray-700 group">
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
        <div class="max-w-7xl mx-auto p-6">
            <div class="flex items-center justify-between mb-6">

                <div class="flex items-center gap-3">
                    <!-- Back Button -->
                    <a href="index.php"
                        class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-md text-gray-700 text-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </a>

                    <h1 class="text-2xl font-bold">User Management</h1>
                </div>

                <div class="text-gray-600">Admin</div>
            </div>


            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex gap-3 border-b pb-3 items-center">
                    <button id="tab-staff" class="tab-btn px-4 py-2 rounded-md bg-green-100 font-medium">Staff
                        Users</button>
                    <button id="tab-enum" class="tab-btn px-4 py-2 rounded-md hover:bg-gray-100">Enumerator
                        Users</button>
                    <div class="ml-auto flex gap-2">
                        <button id="add-staff-btn"
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">Add Staff</button>
                        <button id="add-enum-btn"
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">Add
                            Enumerator</button>
                    </div>
                </div>

                <div id="content" class="mt-4">
                    <div id="table-container" class="overflow-x-auto bg-white rounded-md shadow-sm">
                        <div id="loading" class="p-6 text-center text-gray-500">Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Staff Modal -->
        <div id="modal-add-staff" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
            <div class="bg-white rounded-lg w-full max-w-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Create Staff Account</h2>
                    <button onclick="closeModal('modal-add-staff')" class="text-gray-500">✕</button>
                </div>

                <form id="form-add-staff" class="space-y-3">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="role" value="staff">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div>
                        <label class="block text-xs text-gray-600">Full name</label>
                        <input name="fullname" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Username</label>
                        <input name="username" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Email</label>
                        <input name="email" type="email" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Password</label>
                        <input name="password" type="password" required class="w-full p-2 border rounded mt-1" />
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="closeModal('modal-add-staff')"
                            class="px-4 py-2 rounded border">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded bg-green-500 text-white">Create Staff</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Enumerator Modal -->
        <div id="modal-add-enum" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
            <div class="bg-white rounded-lg w-full max-w-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Create Enumerator Account</h2>
                    <button onclick="closeModal('modal-add-enum')" class="text-gray-500">✕</button>
                </div>

                <form id="form-add-enum" class="space-y-3">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="role" value="enumerator">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div>
                        <label class="block text-xs text-gray-600">Full name</label>
                        <input name="fullname" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Username</label>
                        <input name="username" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Email</label>
                        <input name="email" type="email" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Password</label>
                        <input name="password" type="password" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Assigned Barangay / Area</label>
                        <input name="assigned_area" class="w-full p-2 border rounded mt-1" placeholder="e.g., Brgy 1" />
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="closeModal('modal-add-enum')"
                            class="px-4 py-2 rounded border">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded bg-green-500 text-white">Create
                            Enumerator</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal (reused for both roles) -->
        <div id="modal-edit" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
            <div class="bg-white rounded-lg w-full max-w-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="edit-title" class="text-lg font-semibold">Edit User</h2>
                    <button onclick="closeModal('modal-edit')" class="text-gray-500">✕</button>
                </div>

                <form id="form-edit" class="space-y-3">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div>
                        <label class="block text-xs text-gray-600">Full name</label>
                        <input name="fullname" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Username</label>
                        <input name="username" required class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div id="assigned-area-wrapper" style="display:none;">
                        <label class="block text-xs text-gray-600">Assigned Area</label>
                        <input name="assigned_area" class="w-full p-2 border rounded mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Status</label>
                        <select name="status" class="w-full p-2 border rounded mt-1">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="closeModal('modal-edit')"
                            class="px-4 py-2 rounded border">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Confirm Delete Modal -->
        <div id="modal-confirm-delete" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
            <div class="bg-white rounded-lg w-full max-w-md p-6 text-center">
                <h3 class="text-lg font-semibold mb-4">Confirm delete?</h3>
                <p id="delete-info" class="text-sm text-gray-600 mb-4"></p>
                <div class="flex justify-center gap-3">
                    <button onclick="closeModal('modal-confirm-delete')"
                        class="px-4 py-2 rounded border">Cancel</button>
                    <button id="confirm-delete-btn" class="px-4 py-2 rounded bg-red-600 text-white">Delete</button>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div id="toast" class="fixed right-6 bottom-6 hidden p-3 rounded shadow text-white"></div>>


        <script>
            /* Utilities */
            function showToast(msg, type = 'success') {
                const t = document.getElementById('toast');
                t.textContent = msg;
                t.className = 'fixed right-6 bottom-6 p-3 rounded shadow text-white';
                t.style.backgroundColor = (type === 'success') ? 'rgb(16 185 129)' : (type === 'error' ? 'rgb(239 68 68)' : 'rgb(59 130 246)');
                t.classList.remove('hidden');
                setTimeout(() => t.classList.add('hidden'), 3500);
            }
            function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
            function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

            /* Tabs & initial load */
            let currentRole = 'staff';
            document.getElementById('tab-staff').addEventListener('click', () => switchTab('staff'));
            document.getElementById('tab-enum').addEventListener('click', () => switchTab('enumerator'));
            document.getElementById('add-staff-btn').addEventListener('click', () => openModal('modal-add-staff'));
            document.getElementById('add-enum-btn').addEventListener('click', () => openModal('modal-add-enum'));

            function switchTab(role) {
                currentRole = role;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('bg-green-100', 'font-medium'));
                if (role === 'staff') document.getElementById('tab-staff').classList.add('bg-green-100', 'font-medium');
                else document.getElementById('tab-enum').classList.add('bg-green-100', 'font-medium');
                loadTable();
            }

            /* Load table via AJAX */
            async function loadTable() {
                const container = document.getElementById('table-container');
                container.innerHTML = '<div class="p-6 text-center text-gray-500">Loading...</div>';
                try {
                    const res = await fetch('?fetch=users&role=' + encodeURIComponent(currentRole));
                    const json = await res.json();
                    if (!json.success) { container.innerHTML = `<div class="p-6 text-red-500">${json.message}</div>`; return; }
                    const rows = json.data;
                    if (!rows.length) { container.innerHTML = `<div class="p-6 text-center text-gray-600">No ${currentRole} users found.</div>`; return; }

                    let html = `<table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Full Name</th>
          <th class="px-4 py-3 text-left">Email</th>
          <th class="px-4 py-3 text-left">Username</th>
          ${currentRole === 'enumerator' ? '<th class="px-4 py-3 text-left">Assigned Area</th>' : ''}
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>`;
                    rows.forEach((r, i) => {
                        html += `<tr class="${i % 2 ? 'bg-white' : 'bg-gray-50'}">
        <td class="px-4 py-3">${i + 1}</td>
        <td class="px-4 py-3">${escapeHtml(r.fullname ?? r.username)}</td>
        <td class="px-4 py-3">${escapeHtml(r.email)}</td>
        <td class="px-4 py-3">${escapeHtml(r.username)}</td>
        ${currentRole === 'enumerator' ? `<td class="px-4 py-3">${escapeHtml(r.assigned_area ?? '')}</td>` : ''}
        <td class="px-4 py-3">${escapeHtml(r.status ?? 'active')}</td>
        <td class="px-4 py-3 flex gap-2">
          <button class="px-2 py-1 rounded border" onclick="openEdit(${r.id})">Edit</button>
          <button class="px-2 py-1 rounded bg-red-600 text-white" onclick="confirmDelete(${r.id},'${escapeAttr(r.fullname ?? r.username)}')">Delete</button>
        </td>
      </tr>`;
                    });
                    html += `</tbody></table>`;
                    container.innerHTML = html;
                } catch (err) {
                    console.error(err);
                    container.innerHTML = `<div class="p-6 text-center text-red-500">Network error</div>`;
                }
            }

            /* Submit add forms (AJAX) */
            document.getElementById('form-add-staff').addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target;
                const data = new FormData(form);
                await postAjax(data, (json) => {
                    if (json.success) { closeModal('modal-add-staff'); showToast(json.message, 'success'); loadTable(); form.reset(); }
                    else showToast(json.message, 'error');
                });
            });
            document.getElementById('form-add-enum').addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target;
                const data = new FormData(form);
                await postAjax(data, (json) => {
                    if (json.success) { closeModal('modal-add-enum'); showToast(json.message, 'success'); loadTable(); form.reset(); }
                    else showToast(json.message, 'error');
                });
            });

            /* Edit flow */
            async function openEdit(id) {
                try {
                    const res = await fetch('?fetch=users&role=' + currentRole + '&id=' + id);
                    const json = await res.json();
                    if (!json.success || !json.data.length) { showToast('Could not fetch user', 'error'); return; }
                    const u = json.data[0];
                    const form = document.getElementById('form-edit');
                    form.id.value = u.id;
                    form.fullname.value = u.fullname || u.username || '';
                    form.username.value = u.username || '';
                    form.status.value = u.status || 'active';
                    if (currentRole === 'enumerator') {
                        document.getElementById('assigned-area-wrapper').style.display = 'block';
                        form.assigned_area.value = u.assigned_area || '';
                        document.getElementById('edit-title').textContent = 'Edit Enumerator';
                    } else {
                        document.getElementById('assigned-area-wrapper').style.display = 'none';
                        form.assigned_area.value = '';
                        document.getElementById('edit-title').textContent = 'Edit Staff';
                    }
                    openModal('modal-edit');
                } catch (err) {
                    showToast('Network error', 'error');
                }
            }

            /* Save edit */
            document.getElementById('form-edit').addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = new FormData(e.target);
                // attach role is optional — server determines per id
                await postAjax(data, (json) => {
                    if (json.success) { closeModal('modal-edit'); showToast(json.message, 'success'); loadTable(); }
                    else showToast(json.message, 'error');
                });
            });

            /* Delete */
            let deleteTargetId = null;
            function confirmDelete(id, name) {
                deleteTargetId = id;
                document.getElementById('delete-info').textContent = `Delete "${name}"? This action cannot be undone.`;
                openModal('modal-confirm-delete');
            }
            document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
                if (!deleteTargetId) return;
                const data = new FormData();
                data.append('ajax', '1');
                data.append('action', 'delete_user');
                data.append('id', deleteTargetId);
                data.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
                await postAjax(data, (json) => {
                    if (json.success) { closeModal('modal-confirm-delete'); showToast(json.message, 'success'); loadTable(); }
                    else showToast(json.message, 'error');
                });
            });

            /* Generic helper to POST AJAX to this page */
            async function postAjax(formData, cb) {
                try {
                    const res = await fetch('', { method: 'POST', body: formData });
                    const json = await res.json();
                    cb(json);
                } catch (err) {
                    console.error(err);
                    showToast('Network error', 'error');
                }
            }

            /* Helpers */
            function escapeHtml(s) { if (!s && s !== 0) return ''; return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
            function escapeAttr(s) { return (s || '').replace(/'/g, "\\'").replace(/"/g, '\\"'); }

            /* Init */
            switchTab('staff');
        </script>

</body>

</html>