<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$per_page = 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

$where = [];
$params = [];
$types = "";

if ($user_filter !== '') {
    $where[] = "u.username LIKE ?";
    $params[] = "%" . $user_filter . "%";
    $types .= "s";
}

switch ($type_filter) {
    case 'login':    $where[] = "a.activity = 'Logged in'"; break;
    case 'logout':   $where[] = "a.activity = 'Logged out'"; break;
    case 'upload':   $where[] = "a.activity LIKE '%uploaded%'"; break;
    case 'download': $where[] = "a.activity LIKE 'Downloaded%'"; break;
    case 'delete':   $where[] = "a.activity LIKE '%deleted%'"; break;
    default:         $type_filter = 'all';
}

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id $where_sql");
if ($types !== "") { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $conn->prepare("SELECT a.activity, a.activity_time, u.username FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id $where_sql ORDER BY a.activity_time DESC, a.id DESC LIMIT ? OFFSET ?");
$bind_types = $types . "ii";
$bind_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$records = $stmt->get_result();

$last_logins = $conn->query("SELECT u.username, u.status, MAX(a.activity_time) AS last_login FROM users u LEFT JOIN activity_logs a ON a.user_id = u.id AND a.activity = 'Logged in' GROUP BY u.id, u.username, u.status ORDER BY last_login DESC");

function activityBadge($text) {
    if ($text === 'Logged in')  return ['Login', 'b-login'];
    if ($text === 'Logged out') return ['Logout', 'b-logout'];
    if (stripos($text, 'deleted') !== false)  return ['Delete', 'b-delete'];
    if (stripos($text, 'uploaded') !== false) return ['Upload', 'b-upload'];
    if (stripos($text, 'Downloaded') === 0)   return ['Download', 'b-download'];
    return ['Other', 'b-other'];
}

function pageLink($p, $user_filter, $type_filter) {
    return 'admin_records.php?' . http_build_query(['user' => $user_filter, 'type' => $type_filter, 'page' => $p]);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Records - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body { display:flex; min-height:100vh; background:#f1f4f8; }
        .sidebar { width:250px; background:#1e2a3a; color:#fff; padding:20px 0; flex-shrink:0; }
        .sidebar h2 { padding:0 25px 25px; font-size:22px; }
        .sidebar a { display:flex; align-items:center; gap:12px; color:#c7d0da; text-decoration:none; padding:14px 25px; font-size:15px; }
        .sidebar a:hover, .sidebar a.active { background:#2a3b52; color:#fff; }
        .main { flex:1; display:flex; flex-direction:column; min-width:0; }
        .topbar { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e6ea; }
        .topbar .user { display:flex; align-items:center; gap:8px; font-weight:600; color:#333; }
        .content { padding:30px; }
        .panel { background:#fff; border-radius:10px; padding:25px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:25px; }
        .panel h3 { margin-bottom:15px; color:#1e2a3a; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#2563eb; color:#fff; padding:12px 15px; text-align:left; font-size:14px; }
        td { padding:11px 15px; border-bottom:1px solid #eee; font-size:14px; color:#333; }
        tr:hover td { background:#f8fafc; }
        .filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:15px; }
        .filters input, .filters select { padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .btn { background:#2563eb; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:14px; text-decoration:none; }
        .btn:hover { background:#1d4ed8; }
        .badge { padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
        .b-login { background:#e7f7ec; color:#16a34a; }
        .b-logout { background:#fff7e6; color:#d97706; }
        .b-upload { background:#eef6ff; color:#2563eb; }
        .b-download { background:#f3e8ff; color:#9333ea; }
        .b-delete { background:#fef2f2; color:#e11d48; }
        .b-other { background:#f1f5f9; color:#64748b; }
        .pagination { display:flex; gap:6px; align-items:center; margin-top:18px; font-size:14px; color:#555; }
        .pagination a { padding:6px 12px; border:1px solid #ddd; border-radius:6px; text-decoration:none; color:#2563eb; }
        .pagination a:hover { background:#eef6ff; }
        .muted { color:#999; }
        .scroll { overflow-x:auto; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> Admin Panel</h2>
        <a href="admin_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="admin_files.php"><i class="fa-solid fa-folder-open"></i> All Files</a>
        <a href="admin_records.php" class="active"><i class="fa-solid fa-clipboard-list"></i> Records</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h3>User Activity Records</h3>
            <div class="user"><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>

        <div class="content">

            <div class="panel">
                <h3>Last Login of Each User</h3>
                <div class="scroll">
                <table>
                    <tr><th>Username</th><th>Account Status</th><th>Last Login</th></tr>
                    <?php while ($l = $last_logins->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($l['username']); ?></td>
                        <td><?php echo htmlspecialchars($l['status']); ?></td>
                        <td><?php echo $l['last_login'] ? $l['last_login'] : '<span class="muted">Never</span>'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
                </div>
            </div>

            <div class="panel">
                <h3>Activity Log</h3>
                <form method="GET" action="admin_records.php" class="filters">
                    <input type="text" name="user" placeholder="Filter by username..." value="<?php echo htmlspecialchars($user_filter); ?>">
                    <select name="type">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All activities</option>
                        <option value="login" <?php echo $type_filter == 'login' ? 'selected' : ''; ?>>Logins</option>
                        <option value="logout" <?php echo $type_filter == 'logout' ? 'selected' : ''; ?>>Logouts</option>
                        <option value="upload" <?php echo $type_filter == 'upload' ? 'selected' : ''; ?>>Uploads</option>
                        <option value="download" <?php echo $type_filter == 'download' ? 'selected' : ''; ?>>Downloads</option>
                        <option value="delete" <?php echo $type_filter == 'delete' ? 'selected' : ''; ?>>Deletions</option>
                    </select>
                    <button type="submit" class="btn">Filter</button>
                    <?php if ($user_filter !== '' || $type_filter !== 'all'): ?>
                        <a href="admin_records.php" class="btn" style="background:#6b7785;">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="scroll">
                <table>
                    <tr><th>Date and Time</th><th>User</th><th>Type</th><th>Activity</th></tr>
                    <?php if ($records->num_rows == 0): ?>
                        <tr><td colspan="4">No records found.</td></tr>
                    <?php else: ?>
                        <?php while ($r = $records->fetch_assoc()): ?>
                        <?php list($label, $cls) = activityBadge($r['activity']); ?>
                        <tr>
                            <td><?php echo $r['activity_time']; ?></td>
                            <td><?php echo $r['username'] ? htmlspecialchars($r['username']) : '<span class="muted">(deleted user)</span>'; ?></td>
                            <td><span class="badge <?php echo $cls; ?>"><?php echo $label; ?></span></td>
                            <td><?php echo htmlspecialchars($r['activity']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </table>
                </div>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo pageLink($page - 1, $user_filter, $type_filter); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total; ?> records)</span>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo pageLink($page + 1, $user_filter, $type_filter); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
