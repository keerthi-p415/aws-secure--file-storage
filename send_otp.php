<?php
session_start();
require 'db_connect.php';
require 'mail_config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
    exit();
}
$check->close();

$otp = strval(random_int(100000, 999999));
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_time'] = time();
$_SESSION['otp_verified'] = false;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = 'tls';
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your OTP - Secure File Storage";
    $mail->Body = "Hi,<br><br>Your One-Time Password (OTP) for email verification is:<br><br><h2 style='letter-spacing:5px;'>$otp</h2><br>This OTP is valid for 10 minutes.<br><br>If you did not request this, please ignore this email.";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent! Please check your email.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Could not send OTP. Try again later.']);
}
