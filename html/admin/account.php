<?php
include "../../db/db.php";
$message = "";
$showVerify = false;
$registeredEmail = "";
$registeredCode = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // REGISTER
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $verification_code = rand(100000, 999999);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, verification_code, is_verified) 
                                VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $username, $email, $passwordHash, $verification_code);

        if ($stmt->execute()) {
            $message = "✅ Account created! Your verification code is: <b>$verification_code</b>";
            $showVerify = true;
            $registeredEmail = $email;
        } else {
            $message = "❌ Error: " . $stmt->error;
        }
    }

    // VERIFY
    if (isset($_POST['verify'])) {
        $email = trim($_POST['email']);
        $code = trim($_POST['code']);

        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND verification_code=? LIMIT 1");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $update = $conn->prepare("UPDATE users SET is_verified=1 WHERE email=?");
            $update->bind_param("s", $email);
            $update->execute();

            echo "<script>
                alert('✅ Account verified successfully! You can now login.');
                window.location.href = 'adminlogin.php';
              </script>";
            exit;
        } else {
            echo "<script>
                alert('❌ Invalid verification code.');
                window.location.href = 'account.php';
              </script>";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    body {
        background-image: url('../../img/mao.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        font-family: 'Inter', sans-serif;
    }

    .overlay {
        background-color: rgba(243, 244, 237, 0.9);
        backdrop-filter: blur(6px);
        animation: fadeIn 0.8s ease-in-out;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .overlay:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    nav {
        background-color: #166534;
        border-bottom: 4px solid #E6B800;
        transition: background-color 0.3s ease;
    }

    nav:hover {
        background-color: #14532d;
    }

    input {
        transition: all 0.3s ease;
    }

    input:focus {
        transform: translateY(-1px);
        box-shadow: 0 0 0 3px rgba(230, 184, 0, 0.3);
    }

    .green-btn {
        background-color: #166534;
        color: white;
        transition: all 0.3s ease;
    }

    .green-btn:hover {
        background-color: #14532d;
        transform: translateY(-1px);
    }
</style>

<body class="bg-[#f4f6f3] font-[Inter]">
    <!-- Navbar -->
    <nav class="bg-[#166534] text-white shadow-lg fixed w-full z-40 top-0 left-0 border-b-4 border-[#E6B800]">
        <div class="flex items-center justify-between max-w-7xl mx-auto px-6 py-3">
            <div class="flex items-center space-x-3">
                <img src="../../img/logo.png" class="w-12 h-12 rounded-full border-2 border-[#E6B800] bg-white" alt="Logo">
                <span class="text-lg font-semibold tracking-wide">Municipal Agriculture Office - Abra de Ilog</span>
            </div>
        </div>
    </nav>

    <!-- Create Account Section -->
    <section class="flex-grow flex items-center justify-center relative mt-20 mb-4">
        <div class="overlay bg-gray rounded-2xl shadow-2xl w-full max-w-md p-8 border border-[#166534]">
            <div class="flex flex-col items-center mb-6">
                <img src="../../img/logo.png" class="w-20 h-20 rounded-full shadow-md shadow-black" alt="D.A Logo">
                <h1 class="text-2xl font-bold text-[#166534] mt-3">CREATE ACCOUNT</h1>
                <p class="text-sm text-gray-600">Municipal Agriculture Office - Abra de Ilog</p>
            </div>

            <!-- Message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 bg-blue-100 text-blue-700 rounded text-sm">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" id="registerForm" class="space-y-4 <?= $showVerify ? 'hidden' : '' ?>">
                <div>
                    <label for="username" class="block mb-2 text-sm font-medium text-gray-800">Username</label>
                    <input type="text" name="username" id="username"
                        class="w-full p-2.5 border border-[#166534] rounded-lg focus:ring-[#E6B800] focus:border-[#E6B800]"
                        placeholder="Enter username" required>
                </div>

                <div>
                    <label for="password" class="block mb-2 text-sm font-medium text-gray-800">Password</label>
                    <input type="password" name="password" id="password"
                        class="w-full p-2.5 border border-[#166534] rounded-lg focus:ring-[#E6B800] focus:border-[#E6B800]"
                        placeholder="••••••••" required>
                </div>

                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-800">Email</label>
                    <input type="email" name="email" id="email"
                        class="w-full p-2.5 border border-[#166534] rounded-lg focus:ring-[#E6B800] focus:border-[#E6B800]"
                        placeholder="Enter email" required>
                </div>

                <button type="submit" name="register"
                    class="w-full bg-[#166534] hover:bg-[#14532d] text-white font-semibold py-2.5 rounded-lg mt-4 shadow-md transition-all duration-300">
                    Send Code
                </button>

                <p class="text-sm text-gray-700 text-center mt-3">
                    Already have an account?
                    <a href="adminlogin.php" class="font-medium text-[#166534] hover:text-[#E6B800]">Login</a>
                </p>
            </form>
            <!-- Verification Form -->
            <form method="POST" id="verifyForm" class="space-y-4 <?= $showVerify ? '' : 'hidden' ?>">
                <h2 class="text-lg font-semibold text-[#166534] mb-3 text-center">Verify Your Account</h2>

                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-800">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($registeredEmail) ?>"
                        class="w-full p-2.5 border border-[#166534] rounded-lg focus:ring-[#E6B800] focus:border-[#E6B800]"
                        required>
                </div>

                <div>
                    <label for="code" class="block mb-2 text-sm font-medium text-gray-800">Verification Code</label>
                    <input type="text" name="code" placeholder="Enter code"
                        class="w-full p-2.5 border border-[#166534] rounded-lg focus:ring-[#E6B800] focus:border-[#E6B800]"
                        required>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="window.location='account.php'"
                        class="w-1/2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 rounded-lg shadow transition-all">
                        Back
                    </button>
                    <button type="submit" name="verify"
                        class="w-1/2 bg-[#166534] hover:bg-[#14532d] text-white font-medium py-2.5 rounded-lg shadow transition-all">
                        Verify
                    </button>
                </div>
            </form>
        </div>
    </section>
</body>

</html>