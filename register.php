<?php
session_start();
require 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['final_register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $message = "All fields are required.";
    } elseif (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || $_SESSION['otp_email'] !== $email) {
        $message = "Please verify your email with OTP before registering.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Username or Email already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, status, role, email_verified) VALUES (?, ?, ?, 'Pending', 'user', 1)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);

            if ($stmt->execute()) {
                unset($_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_time'], $_SESSION['otp_verified']);
                $message = "SUCCESS: Registration successful! Please wait for admin approval before logging in.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - Secure File Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg, #1e2a3a, #2563eb);
        }
        .card {
            background:#fff; width:420px; padding:40px 35px; border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.25);
        }
        .card .logo { text-align:center; margin-bottom:10px; color:#2563eb; font-size:32px; }
        .card h2 { text-align:center; color:#1e2a3a; margin-bottom:25px; }
        .field { margin-bottom:18px; }
        .field label { display:block; margin-bottom:6px; font-size:13px; color:#555; font-weight:600; }
        .input-wrap { position:relative; }
        .input-wrap input { width:100%; padding:11px 40px 11px 36px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .input-wrap i.left-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#999; }
        .toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#999; }

        .email-row { display:flex; gap:8px; }
        .email-row .input-wrap { flex:1; }
        .otp-btn {
            background:#2563eb; color:#fff; border:none; padding:0 14px; border-radius:6px;
            font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;
        }
        .otp-btn:disabled { background:#9ca3af; cursor:not-allowed; }

        .otp-section { display:none; margin-top:12px; }
        .otp-section.show { display:block; }
        .otp-row { display:flex; gap:8px; }
        .otp-row input { flex:1; padding:11px 12px; border:1px solid #ddd; border-radius:6px; font-size:16px; letter-spacing:4px; text-align:center; }

        .verified-badge { display:none; align-items:center; gap:6px; color:#16a34a; font-size:13px; font-weight:600; margin-top:8px; }
        .verified-badge.show { display:flex; }

        .btn {
            width:100%; padding:12px; background:#2563eb; color:#fff; border:none; border-radius:6px;
            font-size:15px; font-weight:600; cursor:pointer; margin-top:5px;
        }
        .btn:hover { background:#1d4ed8; }
        .btn:disabled { background:#9ca3af; cursor:not-allowed; }

        .message { padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:18px; text-align:center; font-weight:600; }
        .message.info { background:#eef6ff; color:#2563eb; }
        .message.success { background:#e7f7ec; color:#16a34a; }
        .message.error { background:#fef2f2; color:#e11d48; }

        .footer-link { text-align:center; margin-top:20px; font-size:13px; color:#555; }
        .footer-link a { color:#2563eb; text-decoration:none; font-weight:600; }

        .small-status { font-size:12px; margin-top:6px; min-height:16px; }
        .small-status.error { color:#e11d48; }
        .small-status.success { color:#16a34a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"><i class="fa-solid fa-shield-halved"></i></div>
        <h2>Create Account</h2>

        <?php if ($message): ?>
            <?php
                $cls = 'info';
                if (strpos($message, 'SUCCESS') === 0) { $cls = 'success'; $message = str_replace('SUCCESS: ', '', $message); }
                elseif (stripos($message, 'error') !== false || stripos($message, 'exists') !== false || stripos($message, 'verify') !== false) { $cls = 'error'; }
            ?>
            <div class="message <?php echo $cls; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm">
            <div class="field">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user left-icon"></i>
                    <input type="text" name="username" required>
                </div>
            </div>

            <div class="field">
                <label>Email</label>
                <div class="email-row">
                    <div class="input-wrap">
                        <i class="fa-solid fa-envelope left-icon"></i>
                        <input type="email" name="email" id="emailInput" required>
                    </div>
                    <button type="button" class="otp-btn" id="sendOtpBtn" onclick="sendOtp()">Send OTP</button>
                </div>
                <div class="small-status" id="sendOtpStatus"></div>

                <div class="otp-section" id="otpSection">
                    <div class="otp-row">
                        <input type="text" id="otpInput" maxlength="6" placeholder="6-digit OTP">
                        <button type="button" class="otp-btn" id="verifyOtpBtn" onclick="verifyOtp()">Verify OTP</button>
                    </div>
                    <div class="small-status" id="verifyOtpStatus"></div>
                </div>

                <div class="verified-badge" id="verifiedBadge">
                    <i class="fa-solid fa-circle-check"></i> Email Verified
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

            <input type="hidden" name="final_register" value="1">
            <button type="submit" class="btn" id="registerBtn" disabled>Register</button>
        </form>

        <div class="footer-link">Already have an account? <a href="login.php">Login here</a></div>
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

        async function sendOtp() {
            const email = document.getElementById('emailInput').value.trim();
            const statusEl = document.getElementById('sendOtpStatus');
            const btn = document.getElementById('sendOtpBtn');

            if (!email) {
                statusEl.textContent = 'Please enter your email first.';
                statusEl.className = 'small-status error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Sending...';
            statusEl.textContent = '';

            try {
                const res = await fetch('send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email)
                });
                const data = await res.json();

                if (data.success) {
                    statusEl.textContent = data.message;
                    statusEl.className = 'small-status success';
                    document.getElementById('otpSection').classList.add('show');
                } else {
                    statusEl.textContent = data.message;
                    statusEl.className = 'small-status error';
                }
            } catch (e) {
                statusEl.textContent = 'Something went wrong. Try again.';
                statusEl.className = 'small-status error';
            }

            btn.disabled = false;
            btn.textContent = 'Resend OTP';
        }

        async function verifyOtp() {
            const email = document.getElementById('emailInput').value.trim();
            const otp = document.getElementById('otpInput').value.trim();
            const statusEl = document.getElementById('verifyOtpStatus');
            const btn = document.getElementById('verifyOtpBtn');

            if (!otp) {
                statusEl.textContent = 'Please enter the OTP.';
                statusEl.className = 'small-status error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Verifying...';

            try {
                const res = await fetch('verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email) + '&otp=' + encodeURIComponent(otp)
                });
                const data = await res.json();

                if (data.success) {
                    statusEl.textContent = '';
                    document.getElementById('otpSection').classList.remove('show');
                    document.getElementById('verifiedBadge').classList.add('show');
                    document.getElementById('emailInput').readOnly = true;
                    document.getElementById('sendOtpBtn').style.display = 'none';
                    document.getElementById('registerBtn').disabled = false;
                } else {
                    statusEl.textContent = data.message;
                    statusEl.className = 'small-status error';
                }
            } catch (e) {
                statusEl.textContent = 'Something went wrong. Try again.';
                statusEl.className = 'small-status error';
            }

            btn.disabled = false;
            btn.textContent = 'Verify OTP';
        }
    </script>
</body>
</html>
