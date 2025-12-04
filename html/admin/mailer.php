<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

function sendOTPEmail($email, $otp)
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'depotayawa@gmail.com';
    $mail->Password = 'bhputratwahuioyq';  // <-- NOT your Gmail password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('depotayawa@gmail.com', 'MAO Abra de Ilog');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your Login Verification Code";
    $mail->Body = "
        <h2>Your Login Verification Code</h2>
        <p>Your code is: <b>$otp</b></p>
        <p>This code expires in 5 minutes.</p>
    ";

    return $mail->send();
}
