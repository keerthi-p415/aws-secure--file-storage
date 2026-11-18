<?php
session_start();
require 'db_connect.php';
require 'encryption_config.php';

if (!isset($_SESSION['user_id'])) {
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

$user_id = $_SESSION['user_id'];
$message = "";

// Handle upload
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
        $check->bind_param("is", $user_id, $file_name);
        $check->execute();
        $dup = $check->get_result()->num_rows;
        $check->close();

        if ($dup > 0) {
            $message = "You already uploaded a file with this name.";
        } else {
            $destination = "uploads/" . uniqid() . "_" . $file_name;
            encryptFile($file['tmp_name'], $destination);

            $stmt = $conn->prepare("INSERT INTO files (user_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $file_name, $destination, $file_ext, $file_size);
            $stmt->execute();
            $stmt->close();

            $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
            $activity_text = "Uploaded file: " . $file_name;
            $log->bind_param("is", $user_id, $activity_text);
            $log->execute();
            $log->close();

            $message = "File uploaded successfully!";
        }
    }
}

// Handle delete (owner only)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $find = $conn->prepare("SELECT file_path, file_name FROM files WHERE id=? AND user_id=?");
    $find->bind_param("ii", $delete_id, $user_id);
    $find->execute();
    $file_row = $find->get_result()->fetch_assoc();
    $find->close();

    if ($file_row) {
        if (file_exists($file_row['file_path'])) unlink($file_row['file_path']);
        $del = $conn->prepare("DELETE FROM files WHERE id=? AND user_id=?");
        $del->bind_param("ii", $delete_id, $user_id);
        $del->execute();
        $del->close();

        $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
        $activity_text = "Deleted file: " . $file_row['file_name'];
        $log->bind_param("is", $user_id, $activity_text);
        $log->execute();
        $log->close();

        $message = "File deleted successfully.";
    }
}

// All files (shared) with search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $search_term = "%" . $search . "%";
    $all_stmt = $conn->prepare("SELECT files.id, files.file_name, files.file_type, files.file_size, files.uploaded_at, files.download_count, files.user_id, users.username FROM files JOIN users ON files.user_id = users.id WHERE files.file_name LIKE ? ORDER BY files.uploaded_at DESC");
    $all_stmt->bind_param("s", $search_term);
} else {
    $all_stmt = $conn->prepare("SELECT files.id, files.file_name, files.file_type, files.file_size, files.uploaded_at, files.download_count, files.user_id, users.username FROM files JOIN users ON files.user_id = users.id ORDER BY files.uploaded_at DESC");
}
$all_stmt->execute();
$all_files = $all_stmt->get_result();

// My stats
$stats_stmt = $conn->prepare("SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size),0) AS total_size, COALESCE(SUM(download_count),0) AS total_downloads FROM files WHERE user_id=?");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stat_row = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// My uploads (used for Home "recent" and Upload page list)
$mine_stmt = $conn->prepare("SELECT id, file_name, file_type, file_size, uploaded_at FROM files WHERE user_id=? ORDER BY uploaded_at DESC");
$mine_stmt->bind_param("i", $user_id);
$mine_stmt->execute();
$my_files = $mine_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$mine_stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Secure File Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body { display:flex; min-height:100vh; background:#f1f4f8; }
        .sidebar { width:250px; background:#1e2a3a; color:#fff; padding:20px 0; flex-shrink:0; }
        .sidebar h2 { padding:0 25px 25px; font-size:22px; }
        .sidebar a { display:flex; align-items:center; gap:12px; color:#c7d0da; text-decoration:none; padding:14px 25px; font-size:15px; cursor:pointer; }
        .sidebar a:hover, .sidebar a.active { background:#2a3b52; color:#fff; }
        .sidebar a i { width:18px; text-align:center; }
        .main { flex:1; display:flex; flex-direction:column; min-width:0; }
        .topbar { background:#fff; padding:15px 30px; display:flex; justify-content:flex-end; align-items:center; border-bottom:1px solid #e2e6ea; }
        .topbar .user { display:flex; align-items:center; gap:8px; font-weight:600; color:#333; }
        .content { padding:30px; }
        .section { display:none; }
        .section.active { display:block; }
        .cards { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:25px; }
        .card { background:#fff; padding:20px 25px; border-radius:10px; min-width:200px; flex:1; box-shadow:0 1px 4px rgba(0,0,0,0.07); }
        .card h4 { color:#6b7785; font-size:14px; font-weight:600; margin-bottom:10px; }
        .card .value { color:#2563eb; font-size:28px; font-weight:700; }
        .panel { background:#fff; border-radius:10px; padding:25px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:25px; overflow-x:auto; }
        .panel h3 { margin-bottom:15px; color:#1e2a3a; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#2563eb; color:#fff; padding:12px 15px; text-align:left; font-size:14px; }
        td { padding:12px 15px; border-bottom:1px solid #eee; font-size:14px; color:#333; }
        tr:hover td { background:#f8fafc; }
        .upload-box { border:2px dashed #c7d0da; border-radius:10px; padding:40px; text-align:center; }
        .upload-box input[type=file] { margin:15px 0; }
        .btn { background:#2563eb; color:#fff; border:none; padding:10px 22px; border-radius:6px; cursor:pointer; font-size:14px; }
        .btn:hover { background:#1d4ed8; }
        .message { padding:10px 15px; border-radius:6px; margin-bottom:15px; background:#eef6ff; color:#2563eb; font-weight:600; }
        .action-links a { color:#2563eb; text-decoration:none; margin-right:8px; font-size:13px; }
        .action-links a.delete { color:#e11d48; }
        .search-form { margin-bottom:15px; display:flex; gap:8px; }
        .search-form input { padding:8px 12px; border:1px solid #ddd; border-radius:6px; flex:1; max-width:300px; }
        a.fname { color:#1e2a3a; text-decoration:none; font-weight:500; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> FileStore</h2>
        <a href="#" class="nav-link active" data-section="homeSection"><i class="fa-solid fa-house"></i> Home</a>
        <a href="#" class="nav-link" data-section="filesSection"><i class="fa-solid fa-folder"></i> All Files</a>
        <a href="#" class="nav-link" data-section="uploadSection"><i class="fa-solid fa-upload"></i> Upload</a>
        <a href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="user"><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>

        <div class="content">

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- HOME -->
            <div class="section" id="homeSection">
                <div class="cards">
                    <div class="card"><h4>My Files</h4><div class="value"><?php echo $stat_row['total_files']; ?></div></div>
                    <div class="card"><h4>My Downloads Received</h4><div class="value"><?php echo $stat_row['total_downloads']; ?></div></div>
                    <div class="card"><h4>My Storage Used</h4><div class="value"><?php echo round($stat_row['total_size']/1024/1024, 2); ?> MB</div></div>
                </div>
                <div class="panel">
                    <h3>My Recent Uploads</h3>
                    <table>
                        <tr><th>File Name</th><th>Uploaded On</th></tr>
                        <?php if (count($my_files) == 0): ?>
                            <tr><td colspan="2">No files uploaded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($my_files, 0, 5) as $r): ?>
                            <tr>
                                <td><?php echo getFileIcon($r['file_type']); ?> <?php echo htmlspecialchars($r['file_name']); ?></td>
                                <td><?php echo $r['uploaded_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- ALL FILES -->
            <div class="section" id="filesSection">
                <div class="panel">
                    <h3>All Files</h3>
                    <form method="GET" action="dashboard.php" class="search-form">
                        <input type="text" name="search" placeholder="Search by file name..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($search !== ''): ?><a href="dashboard.php" class="btn" style="background:#6b7785; text-decoration:none; line-height:2; display:inline-block;">Clear</a><?php endif; ?>
                    </form>
                    <table>
                        <tr>
                            <th>File Name</th><th>Uploaded By</th><th>Type</th><th>Size (KB)</th><th>Uploaded On</th><th>Downloads</th><th>Actions</th>
                        </tr>
                        <?php if ($all_files->num_rows == 0): ?>
                            <tr><td colspan="7">No files found.</td></tr>
                        <?php else: ?>
                            <?php while ($row = $all_files->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo getFileIcon($row['file_type']); ?> <a class="fname" href="file_action.php?type=preview&id=<?php echo $row['id']; ?>" target="_blank"><?php echo htmlspecialchars($row['file_name']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo strtoupper($row['file_type']); ?></td>
                                <td><?php echo round($row['file_size']/1024, 1); ?></td>
                                <td><?php echo $row['uploaded_at']; ?></td>
                                <td><?php echo $row['download_count']; ?></td>
                                <td class="action-links">
                                    <a href="file_action.php?type=download&id=<?php echo $row['id']; ?>"><i class="fa-solid fa-download"></i> Download</a>
                                    <?php if ($row['user_id'] == $user_id): ?>
                                        <a href="dashboard.php?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Delete this file?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- UPLOAD -->
            <div class="section" id="uploadSection">
                <div class="panel">
                    <h3>Upload a File</h3>
                    <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                        <div class="upload-box">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:40px; color:#2563eb;"></i>
                            <p style="margin:10px 0; color:#6b7785;">PDF, DOCX, PPT, JPG, PNG &mdash; Max 10 MB</p>
                            <input type="file" name="file" required>
                            <br>
                            <button type="submit" class="btn">Upload File</button>
                        </div>
                    </form>
                </div>

                <div class="panel">
                    <h3>Files I've Uploaded So Far</h3>
                    <table>
                        <tr><th>File Name</th><th>Type</th><th>Size (KB)</th><th>Uploaded On</th></tr>
                        <?php if (count($my_files) == 0): ?>
                            <tr><td colspan="4">You haven't uploaded any files yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($my_files as $m): ?>
                            <tr>
                                <td><?php echo getFileIcon($m['file_type']); ?> <a class="fname" href="file_action.php?type=preview&id=<?php echo $m['id']; ?>" target="_blank"><?php echo htmlspecialchars($m['file_name']); ?></a></td>
                                <td><?php echo strtoupper($m['file_type']); ?></td>
                                <td><?php echo round($m['file_size']/1024, 1); ?></td>
                                <td><?php echo $m['uploaded_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        const navLinks = document.querySelectorAll('.nav-link');
        const sections = document.querySelectorAll('.section');

        function showSection(id) {
            sections.forEach(function(s) { s.classList.remove('active'); });
            document.getElementById(id).classList.add('active');
            navLinks.forEach(function(l) { l.classList.remove('active'); });
            document.querySelector('[data-section="' + id + '"]').classList.add('active');
        }

        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                showSection(this.getAttribute('data-section'));
            });
        });

        <?php if (isset($_GET['delete_id']) || isset($_GET['search'])): ?>
            showSection('filesSection');
        <?php elseif ($message): ?>
            showSection('uploadSection');
        <?php else: ?>
            showSection('homeSection');
        <?php endif; ?>
    </script>
</body>
</html>
