<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action == 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='Approved' WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status='Rejected' WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_dashboard.php");
    exit();
}

$result = $conn->query("SELECT id, username, email, status, role, created_at FROM users WHERE id != " . intval($_SESSION['user_id']) . " ORDER BY created_at DESC");

// Stats
$total_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE id != " . intval($_SESSION['user_id']))->fetch_assoc()['c'];
$pending_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='Pending'")->fetch_assoc()['c'];
$total_files = $conn->query("SELECT COUNT(*) as c FROM files")->fetch_assoc()['c'];
$total_downloads = $conn->query("SELECT COALESCE(SUM(download_count),0) as c FROM files")->fetch_assoc()['c'];
// Analytics: file type distribution (count + total size)
$type_stats = $conn->query("SELECT file_type, COUNT(*) as file_count, COALESCE(SUM(file_size),0) as total_size FROM files GROUP BY file_type ORDER BY file_count DESC");
$chart_labels = [];
$chart_counts = [];
$chart_sizes_mb = [];
while ($t = $type_stats->fetch_assoc()) {
    $chart_labels[] = strtoupper($t['file_type']);
    $chart_counts[] = intval($t['file_count']);
    $chart_sizes_mb[] = round($t['total_size'] / 1024 / 1024, 2);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script> 
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

        .cards { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:25px; }
        .card { background:#fff; padding:20px 25px; border-radius:10px; min-width:200px; flex:1; box-shadow:0 1px 4px rgba(0,0,0,0.07); }
        .card h4 { color:#6b7785; font-size:14px; font-weight:600; margin-bottom:10px; }
        .card .value { color:#2563eb; font-size:28px; font-weight:700; }

        .panel { background:#fff; border-radius:10px; padding:25px; box-shadow:0 1px 4px rgba(0,0,0,0.07); }
        .panel h3 { margin-bottom:15px; color:#1e2a3a; }

        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#2563eb; color:#fff; padding:12px 15px; text-align:left; font-size:14px; }
        td { padding:12px 15px; border-bottom:1px solid #eee; font-size:14px; color:#333; }
        tr:hover td { background:#f8fafc; }

        .badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; }
        .badge.pending { background:#fff7e6; color:#d97706; }
        .badge.approved { background:#e7f7ec; color:#16a34a; }
        .badge.rejected { background:#fef2f2; color:#e11d48; }

        .action-links a { text-decoration:none; padding:5px 10px; border-radius:5px; font-size:12px; font-weight:600; margin-right:5px; }
        .approve-btn { background:#16a34a; color:#fff; }
        .reject-btn { background:#e11d48; color:#fff; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> Admin Panel</h2>
        <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="admin_files.php"><i class="fa-solid fa-folder-open"></i> All Files</a>
<a href="admin_records.php"><i class="fa-solid fa-clipboard-list"></i> Records</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h3>Admin Dashboard</h3>
            <div class="user"><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>

        <div class="content">
            <div class="cards">
                <div class="card"><h4>Total Users</h4><div class="value"><?php echo $total_users; ?></div></div>
                <div class="card"><h4>Pending Approvals</h4><div class="value"><?php echo $pending_users; ?></div></div>
                <div class="card"><h4>Total Files</h4><div class="value"><?php echo $total_files; ?></div></div>
                <div class="card"><h4>Total Downloads</h4><div class="value"><?php echo $total_downloads; ?></div></div>
            </div>
            <div class="cards">
                <div class="card" style="flex:1; min-width:400px;">
                    <h4>File Type Distribution</h4>
                    <canvas id="typeChart" style="max-height:280px; margin-top:10px;"></canvas>
                </div>
                <div class="card" style="flex:1; min-width:400px;">
                    <h4>Storage Used by File Type (MB)</h4>
                    <canvas id="sizeChart" style="max-height:280px; margin-top:10px;"></canvas>
                </div>
            </div>

            <div class="panel">
                <h3>All Users</h3>
                <table>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Status</th><th>Role</th><th>Registered On</th><th>Actions</th>
                    </tr>
                    <?php if ($result->num_rows == 0): ?>
                        <tr><td colspan="7">No users found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><span class="badge <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                        <td><?php echo $row['role']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td class="action-links">
                            <?php if ($row['status'] != 'Approved'): ?>
                                <a class="approve-btn" href="admin_dashboard.php?action=approve&id=<?php echo $row['id']; ?>">Approve</a>
                            <?php endif; ?>
                            <?php if ($row['status'] != 'Rejected'): ?>
                                <a class="reject-btn" href="admin_dashboard.php?action=reject&id=<?php echo $row['id']; ?>">Reject</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>
<script>
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartCounts = <?php echo json_encode($chart_counts); ?>;
        const chartSizes = <?php echo json_encode($chart_sizes_mb); ?>;

        const bgColors = ['#2563eb', '#e11d48', '#16a34a', '#ea580c', '#9333ea', '#0891b2', '#ca8a04'];

        new Chart(document.getElementById('typeChart'), {
            type: 'pie',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartCounts,
                    backgroundColor: bgColors
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        new Chart(document.getElementById('sizeChart'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Storage (MB)',
                    data: chartSizes,
                    backgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>
