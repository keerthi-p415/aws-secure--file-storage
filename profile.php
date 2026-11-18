<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Fetch current user info
$stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle update email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_email'])) {
    $new_email = trim($_POST['email']);
    if (!empty($new_email)) {
        $upd = $conn->prepare("UPDATE users SET email=? WHERE id=?");
        $upd->bind_param("si", $new_email, $user_id);
        $upd->execute();
        $upd->close();
        $message = "Email updated successfully.";
        $user['email'] = $new_email;
    } else {
        $error = "Email cannot be empty.";
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current_password, $row['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $new_hash, $user_id);
        $upd->execute();
        $upd->close();
        $message = "Password changed successfully.";
    }
}

// Fetch activity logs
$logs_stmt = $conn->prepare("SELECT activity, activity_time FROM activity_logs WHERE user_id=? ORDER BY activity_time DESC LIMIT 20");
$logs_stmt->bind_param("i", $user_id);
$logs_stmt->execute();
$logs = $logs_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile - Secure File Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body { display:flex; min-height:100vh; background:#f1f4f8; }

        .sidebar { width:250px; background:#1e2a3a; color:#fff; padding:20px 0; flex-shrink:0; }
        .sidebar h2 { padding:0 25px 25px; font-size:22px; }
        .sidebar a { display:flex; align-items:center; gap:12px; color:#c7d0da; text-decoration:none; padding:14px 25px; font-size:15px; }
        .sidebar a:hover, .sidebar a.active { background:#2a3b52; color:#fff; }

        .main { flex:1; display:flex; flex-direction:column; }
        .topbar { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e6ea; }
        .topbar .user { display:flex; align-items:center; gap:8px; font-weight:600; color:#333; }

        .content { padding:30px; display:flex; gap:25px; flex-wrap:wrap; }

        .panel { background:#fff; border-radius:10px; padding:25px; box-shadow:0 1px 4px rgba(0,0,0,0.07); flex:1; min-width:320px; }
        .panel h3 { margin-bottom:18px; color:#1e2a3a; }

        .field { margin-bottom:16px; }
        .field label { display:block; margin-bottom:6px; font-size:13px; color:#555; font-weight:600; }
        .field input { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }

        .btn { background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600; }
        .btn:hover { background:#1d4ed8; }

        .message { background:#eef6ff; color:#2563eb; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:15px; font-weight:600; }
        .error { background:#fef2f2; color:#e11d48; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:15px; font-weight:600; }

        .log-item { padding:10px 0; border-bottom:1px solid #eee; font-size:13px; color:#555; }
        .log-item span { display:block; color:#999; font-size:11px; margin-top:3px; }

        .info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
        .info-row strong { color:#1e2a3a; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> FileStore</h2>
        <a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="profile.php" class="active"><i class="fa-solid fa-user-gear"></i> Profile</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h3>My Profile</h3>
            <div class="user"><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>

        <div class="content">

            <div class="panel">
                <h3>Account Info</h3>

                <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="info-row"><span>Username</span><strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
                <div class="info-row"><span>Member Since</span><strong><?php echo $user['created_at']; ?></strong></div>

                <form method="POST" action="profile.php" style="margin-top:20px;">
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" name="update_email" class="btn">Update Email</button>
                </form>

                <hr style="margin:25px 0; border:none; border-top:1px solid #eee;">

                <h3>Change Password</h3>
                <form method="POST" action="profile.php" style="margin-top:15px;">
                    <div class="field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="field">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="field">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
            </div>

            <div class="panel">
                <h3>Recent Activity</h3>
                <?php if ($logs->num_rows == 0): ?>
                    <p style="color:#999; font-size:13px;">No activity yet.</p>
                <?php endif; ?>
                <?php while ($log = $logs->fetch_assoc()): ?>
                    <div class="log-item">
                        <?php echo htmlspecialchars($log['activity']); ?>
                        <span><?php echo $log['activity_time']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>

        </div>
    </div>
</body>
</html>
