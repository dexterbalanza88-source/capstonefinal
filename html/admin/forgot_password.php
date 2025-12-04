<?php
session_start();
require_once "../../db/conn.php";
require_once "mailer.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $_SESSION["error"] = "Please enter your email.";
        header("Location: forgot_password.php");
        exit;
    }

    // check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u) {
        $_SESSION["error"] = "No account found with that email.";
        header("Location: forgot_password.php");
        exit;
    }

    // generate OTP
    $otp = random_int(100000, 999999);
    $expires = date("Y-m-d H:i:s", time() + 300);

    $upd = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
    $otp_str = (string) $otp;
    $upd->bind_param("ssi", $otp_str, $expires, $u["id"]);
    $upd->execute();

    sendOTPEmail($email, $otp_str);

    $_SESSION["pending_reset_user_id"] = $u["id"];
    $_SESSION["pending_reset_email"] = $email;

    header("Location: forgot_otp_verify.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Forgot Password | MAO Abra</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-[#166534]">
        <h2 class="text-2xl font-bold text-[#166534] text-center mb-3">Forgot Password</h2>
        <p class="text-center text-gray-700 mb-4">Enter your email to receive a verification code.</p>

        <?php if (!empty($_SESSION["error"])): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
                <?= $_SESSION["error"];
                unset($_SESSION["error"]); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-3">
            <label class="block text-sm font-medium">Email Address</label>
            <input type="email" name="email" required
                class="w-full border border-[#166534] rounded p-2 focus:ring-[#E6B800]" />

            <button class="w-full py-2 bg-[#166534] text-white rounded-lg hover:bg-[#14532d]">
                Send Code
            </button>
        </form>

        <p class="text-center mt-3">
            <a href="adminlogin.php" class="text-[#166534] hover:text-[#E6B800] text-sm">
                &larr; Back to Login
            </a>
        </p>
    </div>
</body>

</html>