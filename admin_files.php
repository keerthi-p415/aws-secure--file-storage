<?php
session_start();
require 'db_connect.php';
require 'encryption_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

function getFileIcon($ext) {
    $icons = [
        'pdf'  => '<i class="fa-solid fa-file-pdf" style="color:#e11d48;"></i>',
        'docx' => '<i class="fa-solid fa-file-word" style="color:#2563eb;"></i>',
        'ppt'  => '<i class="fa-solid fa-file-powerpoint" style="color:#ea580c;"></i>',
        'pptx' => '<i class="fa-solid fa-file-powerpoint" style="color:#ea580c;"></i>',
        'jpg'  => '<i class="fa-solid fa-file-image" style="color:#16a34a;"></i>',
        'jpeg' => '<i class="fa-solid fa-file-image" style="color:#16a34a;"></i>',
        'png'  => '<i class="fa-solid fa-file-image" style="color:#16a34a;"></i>',
    ];
    return isset($icons[$ext]) ? $icons[$ext] : '<i class="fa-solid fa-file" style="color:#6b7785;"></i>';
}

function encryptFile($source_path, $dest_path) {
    $data = file_get_contents($source_path);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    file_put_contents($dest_path, $iv . $encrypted);
}

$admin_id = $_SESSION['user_id'];
$message = "";

// Handle admin upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowed_ext = ['pdf', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
    $max_size = 10 * 1024 * 1024;

    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $file_size = $file['size'];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $message = "File too large. Max size is 10 MB.";
    } elseif ($file['error'] !== 0) {
        $message = "Upload error occurred.";
    } elseif (!in_array($file_ext, $allowed_ext)) {
        $message = "File type not allowed. Allowed: " . implode(', ', $allowed_ext);
    } elseif ($file_size > $max_size) {
        $message = "File too large. Max size is 10 MB.";
    } else {
        $check = $conn->prepare("SELECT id FROM files WHERE user_id=? AND file_name=?");
        $check->bind_param("is", $admin_id, $file_name);
        $check->execute();
        $dup = $check->get_result()->num_rows;
        $check->close();

        if ($dup > 0) {
            $message = "You already uploaded a file with this name.";
        } else {
            $destination = "uploads/" . uniqid() . "_" . $file_name;
            encryptFile($file['tmp_name'], $destination);

            $stmt = $conn->prepare("INSERT INTO files (user_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $admin_id, $file_name, $destination, $file_ext, $file_size);
            $stmt->execute();
            $stmt->close();

            $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
            $activity_text = "Admin uploaded file: " . $file_name;
            $log->bind_param("is", $admin_id, $activity_text);
            $log->execute();
            $log->close();

            $message = "File uploaded successfully!";
        }
    }
}

// Handle admin delete (any file)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $find = $conn->prepare("SELECT file_path, file_name FROM files WHERE id=?");
    $find->bind_param("i", $delete_id);
    $find->execute();
    $file_row = $find->get_result()->fetch_assoc();
    $find->close();

    if ($file_row) {
        if (file_exists($file_row['file_path'])) unlink($file_row['file_path']);
        $del = $conn->prepare("DELETE FROM files WHERE id=?");
        $del->bind_param("i", $delete_id);
        $del->execute();
        $del->close();

        $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
        $activity_text = "Admin deleted file: " . $file_row['file_name'];
        $log->bind_param("is", $admin_id, $activity_text);
        $log->execute();
        $log->close();

        $message = "File deleted successfully.";
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $search_term = "%" . $search . "%";
    $files = $conn->prepare("SELECT files.id, files.file_name, files.file_type, files.file_size, files.uploaded_at, users.username FROM files JOIN users ON files.user_id = users.id WHERE files.file_name LIKE ? ORDER BY files.uploaded_at DESC");
    $files->bind_param("s", $search_term);
} else {
    $files = $conn->prepare("SELECT files.id, files.file_name, files.file_type, files.file_size, files.uploaded_at, users.username FROM files JOIN users ON files.user_id = users.id ORDER BY files.uploaded_at DESC");
}
$files->execute();
$all_files = $files->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Files - Admin</title>
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
        .content { padding:30px; }
        .panel { background:#fff; border-radius:10px; padding:25px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:25px; }
        .panel h3 { margin-bottom:15px; color:#1e2a3a; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#2563eb; color:#fff; padding:12px 15px; text-align:left; font-size:14px; }
        td { padding:12px 15px; border-bottom:1px solid #eee; font-size:14px; color:#333; }
        tr:hover td { background:#f8fafc; }
        .search-form { margin-bottom:15px; display:flex; gap:8px; }
        .search-form input { padding:8px 12px; border:1px solid #ddd; border-radius:6px; flex:1; max-width:300px; }
        .btn { background:#2563eb; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:14px; text-decoration:none; }
        .btn:hover { background:#1d4ed8; }
        .delete-link { color:#e11d48; text-decoration:none; font-weight:600; font-size:13px; }
        .message { background:#eef6ff; color:#2563eb; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:15px; font-weight:600; }
        .upload-box { border:2px dashed #c7d0da; border-radius:10px; padding:30px; text-align:center; }
        .upload-box input[type=file] { margin:12px 0; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> Admin Panel</h2>
        <a href="admin_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="admin_files.php" class="active"><i class="fa-solid fa-folder-open"></i> All Files</a>
<a href="admin_records.php"><i class="fa-solid fa-clipboard-list"></i> Records</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h3>All Uploaded Files</h3>
            <div class="user"><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>

        <div class="content">
            <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

            <div class="panel">
                <h3>Upload a File</h3>
                <form method="POST" action="admin_files.php" enctype="multipart/form-data">
                    <div class="upload-box">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size:36px; color:#2563eb;"></i>
                        <p style="margin:8px 0; color:#6b7785;">PDF, DOCX, PPT, JPG, PNG &mdash; Max 10 MB</p>
                        <input type="file" name="file" required>
                        <br>
                        <button type="submit" class="btn">Upload File</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h3>All Files</h3>
                <form method="GET" action="admin_files.php" class="search-form">
                    <input type="text" name="search" placeholder="Search by file name..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn">Search</button>
                    <?php if ($search !== ''): ?><a href="admin_files.php" class="btn" style="background:#6b7785;">Clear</a><?php endif; ?>
                </form>

                <table>
                    <tr><th>File Name</th><th>Uploaded By</th><th>Type</th><th>Size (KB)</th><th>Uploaded On</th><th>Actions</th></tr>
                    <?php if ($all_files->num_rows == 0): ?>
                        <tr><td colspan="6">No files found.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $all_files->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo getFileIcon($row['file_type']); ?> <a href="file_action.php?type=preview&id=<?php echo $row['id']; ?>" target="_blank" style="color:#1e2a3a; text-decoration:none; font-weight:500;"><?php echo htmlspecialchars($row['file_name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo strtoupper($row['file_type']); ?></td>
                            <td><?php echo round($row['file_size']/1024, 1); ?></td>
                            <td><?php echo $row['uploaded_at']; ?></td>
                            <td>
                                <a href="file_action.php?type=download&id=<?php echo $row['id']; ?>" style="margin-right:8px; color:#2563eb; text-decoration:none;"><i class="fa-solid fa-download"></i> Download</a>
                                <a class="delete-link" href="admin_files.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this file?');"><i class="fa-solid fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
