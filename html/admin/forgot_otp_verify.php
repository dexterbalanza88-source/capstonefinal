<?php
session_start();
require_once "../../db/conn.php";
require_once "mailer.php"; // keep your mailer file

if (empty($_SESSION["pending_reset_user_id"])) {
    header("Location: forgot_password.php");
    exit;
}

$userId = $_SESSION["pending_reset_user_id"];
$message = "";


// ====================================================
// 1️⃣ RESEND OTP
// ====================================================
if (isset($_GET["resend"]) && $_GET["resend"] == 1) {

    // generate OTP again
    $newOtp = random_int(100000, 999999);
    $expires = date("Y-m-d H:i:s", time() + 300); // 5 minutes

    // update DB
    $up = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
    $newOtpStr = (string) $newOtp;
    $up->bind_param("ssi", $newOtpStr, $expires, $userId);
    $up->execute();

    // send email
    sendOTPEmail($_SESSION["pending_reset_email"], $newOtpStr);

    $_SESSION["success"] = "A new OTP has been sent to your email.";
    header("Location: forgot_otp_verify.php");
    exit;
}


// ====================================================
// 2️⃣ VERIFY OTP
// ====================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $otp = trim($_POST["otp"] ?? "");

    if ($otp === "") {
        $_SESSION["error"] = "Enter the OTP.";
        header("Location: forgot_otp_verify.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT otp_code, otp_expires FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || $otp !== $u["otp_code"]) {
        $_SESSION["error"] = "Incorrect OTP.";
        header("Location: forgot_otp_verify.php");
        exit;
    }

    if (strtotime($u["otp_expires"]) < time()) {
        $_SESSION["error"] = "OTP expired.";
        header("Location: forgot_otp_verify.php");
        exit;
    }

    $_SESSION["allow_reset"] = true;
    header("Location: reset_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Verify OTP | Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-gray-100">

    <div class="bg-white p-8 rounded-xl shadow-lg border border-[#166534] max-w-md w-full">

        <h2 class="text-2xl font-bold text-[#166534] text-center mb-3">OTP Verification</h2>
        <p class="text-center text-gray-700 mb-4">Enter the 6-digit code sent to your email.</p>

        <!-- SUCCESS MESSAGE -->
        <?php if (!empty($_SESSION["success"])): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">
                <?= $_SESSION["success"];
                unset($_SESSION["success"]); ?>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if (!empty($_SESSION["error"])): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
                <?= $_SESSION["error"];
                unset($_SESSION["error"]); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-3">
            <label class="block text-sm font-medium">Enter OTP</label>
            <input type="text" name="otp" maxlength="6" class="w-full border border-[#166534] rounded p-2" required />

            <button class="w-full py-2 bg-[#166534] text-white rounded-lg hover:bg-[#14532d]">
                Verify Code
            </button>
        </form>

        <!-- RESEND OTP LINK -->
        <p class="text-center mt-4 text-sm">
            <a href="?resend=1" class="text-[#166534] hover:text-[#0f3a1f]">Resend OTP</a>
        </p>

        <!-- START OVER -->
        <p class="text-center mt-3 text-sm">
            <a href="forgot_password.php" class="text-[#166534] hover:text-[#E6B800]">Start Over</a>
        </p>

    </div>

</body>

</html>