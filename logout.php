<?php
session_start();
require 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
    $activity_text = "Logged out";
    $log->bind_param("is", $_SESSION['user_id'], $activity_text);
    $log->execute();
    $log->close();
}

session_destroy();
header("Location: login.php");
exit();
