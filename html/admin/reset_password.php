<?php
session_start();
require_once "../../db/conn.php";

if (empty($_SESSION["allow_reset"]) || empty($_SESSION["pending_reset_user_id"])) {
    header("Location: forgot_password.php");
    exit;
}

$userId = $_SESSION["pending_reset_user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $p1 = $_POST["password"] ?? "";
    $p2 = $_POST["confirm"] ?? "";

    if ($p1 === "" || $p2 === "") {
        $_SESSION["error"] = "Please fill all fields.";
        header("Location: reset_password.php");
        exit;
    }

    if ($p1 !== $p2) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location: reset_password.php");
        exit;
    }

    $hash = password_hash($p1, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hash=?, otp_code=NULL, otp_expires=NULL WHERE id=?");
    $stmt->bind_param("si", $hash, $userId);
    $stmt->execute();

    unset($_SESSION["allow_reset"]);
    unset($_SESSION["pending_reset_user_id"]);

    $_SESSION["notice"] = "Password reset successfully. Please log in.";
    header("Location: adminlogin.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
<div class="bg-white p-8 rounded-xl shadow-lg border border-[#166534] max-w-md w-full">
    <h2 class="text-2xl font-bold text-[#166534] text-center mb-3">Create New Password</h2>

    <?php if (!empty($_SESSION["error"])): ?>
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
            <?= $_SESSION["error"]; unset($_SESSION["error"]); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-3">
        <label class="block text-sm font-medium">New Password</label>
        <input type="password" name="password" required class="w-full border border-[#166534] rounded p-2" />

        <label class="block text-sm font-medium">Confirm Password</label>
        <input type="password" name="confirm" required class="w-full border border-[#166534] rounded p-2" />

        <button class="w-full py-2 bg-[#166534] text-white rounded-lg hover:bg-[#14532d]">
            Reset Password
        </button>
    </form>
</div>
</body>
</html>
