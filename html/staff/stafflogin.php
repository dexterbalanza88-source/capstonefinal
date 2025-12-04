<?php
session_start(); // Add session start
require_once "../../db/conn.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $sql = "SELECT id, fullname, email, password, role, status 
                FROM user_accounts 
                WHERE LOWER(email)=LOWER(?) AND LOWER(role)='staff' 
                LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows < 1) {
                $error = "No staff account found with that email.";
            } else {
                $stmt->bind_result($id, $fullname, $db_email, $db_password, $db_role, $db_status);
                $stmt->fetch();

                if (strtolower($db_status) !== 'active') {
                    $error = "Your account is inactive. Contact admin.";
                } elseif (!password_verify($password, $db_password)) {
                    $error = "Invalid email or password.";
                } else {
                    // ✅ Successful login → generate unique token
                    $token = bin2hex(random_bytes(64));
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

                    $stmt_insert = $conn->prepare(
                        "INSERT INTO staff_sessions (staff_id, session_token, user_agent, ip_address)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt_insert->bind_param("isss", $id, $token, $user_agent, $ip_address);
                    $stmt_insert->execute();
                    $stmt_insert->close();

                    // Per-staff cookie
                    $cookie_name = "staff_token_" . $id;
                    setcookie(
                        $cookie_name,
                        $token,
                        time() + 3600, // 1 hour
                        "/",
                        "",
                        isset($_SERVER['HTTPS']),
                        true
                    );

                    // Display success message and redirect with alert
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Login Successful</title>
                        <script>
                            alert("Login successful! Welcome back, ' . htmlspecialchars($fullname) . '");
                            window.location.href = "adddata.php?staff_id=' . $id . '";
                        </script>
                    </head>
                    <body>
                        <p style="text-align:center; margin-top:50px;">
                            Login successful. Redirecting...
                            <br>
                            <a href="adddata.php?staff_id=' . $id . '">Click here if not redirected</a>
                        </p>
                    </body>
                    </html>';
                    exit;
                }
            }
            $stmt->close();
        }
    }
}

// If we reach here, either it's a GET request or login failed
// Set CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!-- Below is your unchanged login HTML / UI -->
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Staff Login | MAO Abra de Ilog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
</head>

<body class="min-h-screen flex flex-col">

    <!-- Background -->
    <div class="fixed inset-0 bg-cover bg-center"
        style="background-image: url('../../img/mun.png'); filter: brightness(.8) blur(1px); z-index:-1;"></div>

    <!-- Navbar -->
    <nav class="bg-[#166534] border-b-4 border-[#E6B800] text-white fixed w-full top-0 z-10">
        <div class="max-w-6xl mx-auto px-6 py-3 flex items-center space-x-3">
            <img src="../../img/logo.png" class="h-12 w-12 rounded-full border-2 border-white">
            <h1 class="text-lg font-semibold">Municipal Agriculture Office – Abra De Ilog</h1>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center mt-24">
        <div class="bg-white/90 backdrop-blur-lg shadow-2xl rounded-2xl border border-[#166534] p-8 max-w-md w-full">
            <div class="flex justify-center mb-4">
                <img src="../../img/logo.png" class="w-20 h-20">
            </div>

            <h2 class="text-2xl font-bold text-[#166534] text-center mb-2">STAFF LOGIN PORTAL</h2>

            <!-- Logout Success Message -->
            <?php if (!empty($logout_message)): ?>
                <div class="bg-green-100 text-green-700 border border-green-400 px-4 py-2 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($logout_message) ?>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="bg-red-100 text-red-700 border border-red-400 px-4 py-2 rounded mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="stafflogin.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div>
                    <label class="text-sm font-medium">Email Address</label>
                    <input type="email" name="email" required
                        class="w-full border border-[#166534] rounded-lg p-2.5 text-sm"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div>
                    <label class="text-sm font-medium">Password</label>
                    <input type="password" name="password" required
                        class="w-full border border-[#166534] rounded-lg p-2.5 text-sm">
                </div>

                <button type="submit"
                    class="w-full bg-[#166534] text-white py-2.5 rounded-lg shadow-md font-semibold hover:bg-[#14532d] transition">
                    Sign In
                </button>
            </form>

            <div class="mt-4 text-sm text-center">
                <a href="../login.html" class="text-[#166534] hover:text-[#E6B800]">&larr; Back</a>
            </div>
        </div>
    </main>

    <!-- Add Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>

</html>