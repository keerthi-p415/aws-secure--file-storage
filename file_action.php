<?php
session_start();
require 'db_connect.php';
require 'encryption_config.php';

function decryptFile($file_path) {
    $raw = file_get_contents($file_path);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($raw, 0, $iv_length);
    $encrypted = substr($raw, $iv_length);
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    // Older files (uploaded before encryption was added) are plain, so serve them as-is
    return ($decrypted === false) ? $raw : $decrypted;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

// No user_id restriction here -- any logged-in user can view/download any file
$stmt = $conn->prepare("SELECT file_name, file_path, file_type FROM files WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file || !file_exists($file['file_path'])) {
    die("File not found.");
}

$mime_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];
$mime = isset($mime_types[$file['file_type']]) ? $mime_types[$file['file_type']] : 'application/octet-stream';

if ($type === 'download') {
    $upd = $conn->prepare("UPDATE files SET download_count = download_count + 1 WHERE id=?");
    $upd->bind_param("i", $id);
    $upd->execute();
    $upd->close();

    $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
    $activity_text = "Downloaded file: " . $file['file_name'];
    $log->bind_param("is", $_SESSION['user_id'], $activity_text);
    $log->execute();
    $log->close();
    $decrypted_data = decryptFile($file['file_path']);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
    header('Content-Length: ' . strlen($decrypted_data));
    echo $decrypted_data;
    exit();
     } elseif ($type === 'preview') {
    $decrypted_data = decryptFile($file['file_path']);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $file['file_name'] . '"');
    header('Content-Length: ' . strlen($decrypted_data));
    echo $decrypted_data;
    exit();
    } else {
    die("Invalid action.");
}
