<?php
session_start();
require 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, username, password, status, role, email_verified FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

         if (password_verify($password, $user['password'])) {
            if ($user['email_verified'] != 1) {
                $message = "Please verify your email before logging in. Check your inbox.";
                } 
              elseif ($user['status'] == 'Approved') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
 
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
                $activity_text = "Logged in";
                $log->bind_param("is", $user['id'], $activity_text);
                $log->execute();
                $log->close();
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } elseif ($user['status'] == 'Pending') {
                $message = "Your account is pending admin approval.";
            } else {
                $message = "Your account has been rejected.";
            }
        } else {
            $message = "Invalid username or password.";
        }
    } else {
        $message = "Invalid username or password.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Secure File Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg, #1e2a3a, #2563eb);
        }
        .login-card {
            background:#fff; width:380px; padding:40px 35px; border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.25);
        }
        .login-card .logo { text-align:center; margin-bottom:10px; color:#2563eb; font-size:32px; }
        .login-card h2 { text-align:center; color:#1e2a3a; margin-bottom:25px; }
        .field { margin-bottom:18px; }
        .field label { display:block; margin-bottom:6px; font-size:13px; color:#555; font-weight:600; }
        .input-wrap { position:relative; }
        .input-wrap input {
            width:100%; padding:11px 40px 11px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px;
        }
        .input-wrap i.left-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#999; }
        .input-wrap input { padding-left:36px; }
        .toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#999; }
        .btn {
            width:100%; padding:12px; background:#2563eb; color:#fff; border:none; border-radius:6px;
            font-size:15px; font-weight:600; cursor:pointer; margin-top:5px;
        }
        .btn:hover { background:#1d4ed8; }
        .message { background:#fef2f2; color:#e11d48; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:18px; text-align:center; }
        .footer-link { text-align:center; margin-top:20px; font-size:13px; color:#555; }
        .footer-link a { color:#2563eb; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo"><i class="fa-solid fa-shield-halved"></i></div>
        <h2>Welcome Back</h2>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="field">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user left-icon"></i>
                    <input type="text" name="username" required>
                </div>
            </div>
            <div class="field">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock left-icon"></i>
                    <input type="password" name="password" id="password" required>
                    <i class="fa-solid fa-eye toggle-eye" id="eyeIcon" onclick="togglePassword()"></i>
                </div>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>

        <div class="footer-link"><a href="forgot_password.php">Forgot Password?</a></div>
        <div class="footer-link">Don't have an account? <a href="register.php">Register here</a></div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
