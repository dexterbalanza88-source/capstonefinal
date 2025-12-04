<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendVerificationEmail($toEmail, $token) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'yourgmail@gmail.com';     // your Gmail
        $mail->Password   = 'your-app-password';       // Gmail App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('yourgmail@gmail.com', 'Your App Name');
        $mail->addAddress($toEmail);

        $verificationLink = "http://yourdomain.com/account.php?token=$token";

        $mail->isHTML(true);
        $mail->Subject = 'Verify your account';
        $mail->Body    = "
            <h2>Welcome to Our App</h2>
            <p>Click the link below to verify your email:</p>
            <a href='$verificationLink'>$verificationLink</a>
            <br><br>
            <p>If you did not request this, please ignore.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}
