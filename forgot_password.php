<?php
session_start();
require 'db_connect.php';

$message = "";
$step = "email"; // email -> otp -> reset -> done

// Determine current step from session state
if (isset($_SESSION['fp_otp_verified']) && $_SESSION['fp_otp_verified'] === true) {
    $step = "reset";
} elseif (isset($_SESSION['fp_email'])) {
    $step = "otp";
}

// Handle: Step 1 - Send OTP (AJAX handled separately in fp_send_otp.php)
// Handle: Step 2 - Verify OTP (AJAX handled separately in fp_verify_otp.php)

// Handle: Step 3 - Reset password (normal form POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    if (!isset($_SESSION['fp_otp_verified']) || $_SESSION['fp_otp_verified'] !== true || !isset($_SESSION['fp_email'])) {
        $message = "Session expired. Please start again.";
        $step = "email";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 6) {
            $message = "Password must be at least 6 characters.";
            $step = "reset";
        } elseif ($new_password !== $confirm_password) {
            $message = "Passwords do not match.";
            $step = "reset";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $email = $_SESSION['fp_email'];

            $upd = $conn->prepare("UPDATE users SET password=? WHERE email=?");
            $upd->bind_param("ss", $hash, $email);
            $upd->execute();
            $upd->close();

            unset($_SESSION['fp_email'], $_SESSION['fp_otp_verified'], $_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_time'], $_SESSION['otp_verified']);

            $message = "SUCCESS: Password reset successful! You can now login with your new password.";
            $step = "done";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - Secure File Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg, #1e2a3a, #2563eb);
        }
        .card {
            background:#fff; width:400px; padding:40px 35px; border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.25);
        }
        .card .logo { text-align:center; margin-bottom:10px; color:#2563eb; font-size:32px; }
        .card h2 { text-align:center; color:#1e2a3a; margin-bottom:8px; }
        .card p.sub { text-align:center; color:#6b7785; font-size:13px; margin-bottom:25px; }
        .field { margin-bottom:18px; }
        .field label { display:block; margin-bottom:6px; font-size:13px; color:#555; font-weight:600; }
        .input-wrap { position:relative; }
        .input-wrap input { width:100%; padding:11px 12px 11px 36px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .input-wrap i.left-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#999; }
        .toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#999; }

        .otp-input { text-align:center; letter-spacing:6px; font-size:18px; }

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

        .small-status { font-size:12px; margin-top:6px; min-height:16px; text-align:center; }
        .small-status.error { color:#e11d48; }
        .small-status.success { color:#16a34a; }

        .icon-center { text-align:center; font-size:50px; color:#16a34a; margin-bottom:15px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"><i class="fa-solid fa-shield-halved"></i></div>

        <?php if ($step == "email"): ?>
            <h2>Forgot Password</h2>
            <p class="sub">Enter your registered email to receive an OTP</p>

            <?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

            <div class="field">
                <label>Email</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope left-icon"></i>
                    <input type="email" id="emailInput" required>
                </div>
                <div class="small-status" id="sendStatus"></div>
            </div>
            <button type="button" class="btn" id="sendBtn" onclick="sendOtp()">Send OTP</button>

        <?php elseif ($step == "otp"): ?>
            <h2>Enter OTP</h2>
            <p class="sub">OTP sent to <strong><?php echo htmlspecialchars($_SESSION['fp_email']); ?></strong></p>

            <div class="field">
                <input type="text" id="otpInput" class="otp-input" maxlength="6" placeholder="------">
                <div class="small-status" id="verifyStatus"></div>
            </div>
            <button type="button" class="btn" id="verifyBtn" onclick="verifyOtp()">Verify OTP</button>

        <?php elseif ($step == "reset"): ?>
            <h2>Reset Password</h2>
            <p class="sub">Enter your new password</p>

            <?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="field">
                    <label>New Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock left-icon"></i>
                        <input type="password" name="new_password" id="newPassword" required>
                        <i class="fa-solid fa-eye toggle-eye" onclick="toggleField('newPassword', this)"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock left-icon"></i>
                        <input type="password" name="confirm_password" id="confirmPassword" required>
                        <i class="fa-solid fa-eye toggle-eye" onclick="toggleField('confirmPassword', this)"></i>
                    </div>
                </div>
                <button type="submit" name="reset_password" class="btn">Reset Password</button>
            </form>

        <?php elseif ($step == "done"): ?>
            <div class="icon-center"><i class="fa-solid fa-circle-check"></i></div>
            <h2 style="text-align:center;">Password Reset!</h2>
            <p class="sub"><?php echo htmlspecialchars(str_replace('SUCCESS: ', '', $message)); ?></p>
            <a href="login.php" class="btn" style="display:block; text-align:center; text-decoration:none;">Go to Login</a>
        <?php endif; ?>

        <?php if ($step != "done"): ?>
        <div class="footer-link">Remember your password? <a href="login.php">Login here</a></div>
        <?php endif; ?>
    </div>

    <script>
        function toggleField(id, icon) {
            const input = document.getElementById(id);
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
            const statusEl = document.getElementById('sendStatus');
            const btn = document.getElementById('sendBtn');

            if (!email) {
                statusEl.textContent = 'Please enter your email.';
                statusEl.className = 'small-status error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Sending...';

            try {
                const res = await fetch('fp_send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email)
                });
                const data = await res.json();

                if (data.success) {
                    location.reload();
                } else {
                    statusEl.textContent = data.message;
                    statusEl.className = 'small-status error';
                    btn.disabled = false;
                    btn.textContent = 'Send OTP';
                }
            } catch (e) {
                statusEl.textContent = 'Something went wrong. Try again.';
                statusEl.className = 'small-status error';
                btn.disabled = false;
                btn.textContent = 'Send OTP';
            }
        }

        async function verifyOtp() {
            const otp = document.getElementById('otpInput').value.trim();
            const statusEl = document.getElementById('verifyStatus');
            const btn = document.getElementById('verifyBtn');

            if (!otp) {
                statusEl.textContent = 'Please enter the OTP.';
                statusEl.className = 'small-status error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Verifying...';

            try {
                const res = await fetch('fp_verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'otp=' + encodeURIComponent(otp)
                });
                const data = await res.json();

                if (data.success) {
                    location.reload();
                } else {
                    statusEl.textContent = data.message;
                    statusEl.className = 'small-status error';
                    btn.disabled = false;
                    btn.textContent = 'Verify OTP';
                }
            } catch (e) {
                statusEl.textContent = 'Something went wrong. Try again.';
                statusEl.className = 'small-status error';
                btn.disabled = false;
                btn.textContent = 'Verify OTP';
            }
        }
    </script>
</body>
</html>
