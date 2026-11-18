<?php
session_start();
header('Content-Type: application/json');

$entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP request found. Please send OTP again.']);
    exit();
}

if ($_SESSION['otp_email'] !== $email) {
    echo json_encode(['success' => false, 'message' => 'Email mismatch. Please send OTP again.']);
    exit();
}

if (time() - $_SESSION['otp_time'] > 600) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit();
}

if ($entered_otp === $_SESSION['otp_code']) {
    $_SESSION['otp_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Email verified successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
}
