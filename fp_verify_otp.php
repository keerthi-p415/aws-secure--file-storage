<?php
session_start();
header('Content-Type: application/json');

$entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

if (!isset($_SESSION['otp_code']) || !isset($_SESSION['fp_email'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
    exit();
}

if (time() - $_SESSION['otp_time'] > 600) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit();
}

if ($entered_otp === $_SESSION['otp_code']) {
    $_SESSION['fp_otp_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'OTP verified!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
}
