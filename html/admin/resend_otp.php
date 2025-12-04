<?php
session_start();
require '../../db/conn.php';
require 'mailer.php';

if (!isset($_SESSION['admin_email'])) {
    header("Location: adminlogin.php");
    exit;
}

$email = $_SESSION['admin_email'];
$newOtp = rand(100000, 999999);

// ✅ update new OTP in DB
$update = $conn->prepare("UPDATE admin SET otp_code = ?, otp_expiry = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE email = ?");
$update->bind_param("ss", $newOtp, $email);
$update->execute();

// ✅ send email
sendOTPEmail($email, $newOtp);

$_SESSION['message'] = "A new OTP has been sent.";
header("Location: otp_verify.php");
exit;

?>


