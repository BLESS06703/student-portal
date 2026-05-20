<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';

try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if ($admin['role'] !== 'admin') { header('Location: dashboard.php'); exit; }
    
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $searchUser = trim($_GET['user'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 20;
    
    $where = "WHERE 1=1";
    $params = [];
    if ($dateFrom) { $where .= " AND DATE(al.created_at) >= :df"; $params['df'] = $dateFrom; }
    if ($dateTo) { $where .= " AND DATE(al.created_at) <= :dt"; $params['dt'] = $dateTo; }
    if ($searchUser) { $where .= " AND s.full_name LIKE :su"; $params['su'] = "%$searchUser%"; }
    
    if (isset($_POST['clear_logs'])) {
        $pdo->exec('DELETE FROM activity_logs');
        $message = 'All logs cleared.';
    }
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al JOIN students s ON al.user_id = s.id $where");
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $perPage);
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare("SELECT al.*, s.full_name, s.role FROM activity_logs al JOIN students s ON al.user_id = s.id $where ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    $todayCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $weekCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $totalCount = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.4rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .msg { padding: 0.6rem; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.82rem; }
        .msg-ok { background: #f0fdf4; color: #16a34a; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
        .stat-box { background: #fff; padding: 1rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-box .value { font-size: 1.5rem; font-weight: 800; }
        .stat-box .label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; }
        
        /* Filter Grid */
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem; }
        .filter-grid .full-width { grid-column: 1 / -1; }
        .filter-grid label { font-size: 0.7rem; color: #6b7280; display: block; margin-bottom: 0.15rem; }
        input, select { padding: 0.5rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.82rem; font-family: inherit; background: #fff; width: 100%; }
        .btn-row { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .btn { padding: 0.45rem 0.8rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: #1a1a1a; color: white; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #4b5563; }
        
        /* Table */
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 0.75rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 600px; }
        th { text-align: left; padding: 0.5rem; font-size: 0.68rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #e5e7eb; background: #fff; white-space: nowrap; }
        td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; }
        td.action-cell { max-width: 300px; word-wrap: break-word; white-space: normal; }
        tr:hover td { background: #fafafa; }
        .badge { display: inline-block; padding: 0.12rem 0.4rem; border-radius: 10px; font-size: 0.6rem; font-weight: 700; white-space: nowrap; }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-teacher { background: #fce7f3; color: #be185d; }
        .badge-student { background: #f3f4f6; color: #6b7280; }
        
        .pagination { display: flex; justify-content: center; gap: 0.3rem; margin-top: 1rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.78rem; text-decoration: none; border: 1px solid #e5e7eb; color: #4b5563; }
        .pagination a:hover { background: #f3f4f6; }
        .pagination .active { background: #1a1a1a; color: white; }
        
        /* Danger Zone */
        .danger-zone { margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #fef2f2; text-align: right; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 300; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 100%; max-width: 400px; text-align: center; }
        .modal h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .modal p { font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem; }
        .modal-btns { display: flex; gap: 0.5rem; justify-content: center; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card, body.dark .stat-box, body.dark .modal { background: #1e293b; }
        body.dark table, body.dark th, body.dark td { border-color: #334155; }
        body.dark th { background: #1e293b; }
        body.dark tr:hover td { background: #1e293b; }
        body.dark input, body.dark select { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .danger-zone { border-color: #450a0a; }
        @media (max-width: 500px) { .filter-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="admin.php"><i class="fas fa-arrow-left"></i> Admin Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Audit Trail</h1>
    <p class="subtitle">Track all system activity — <?php echo $totalLogs; ?> records total</p>
    
    <?php if ($message): ?><div class="msg msg-ok"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="stats-row">
        <div class="stat-box"><div class="value"><?php echo $todayCount; ?></div><div class="label">Today</div></div>
        <div class="stat-box"><div class="value"><?php echo $weekCount; ?></div><div class="label">This Week</div></div>
        <div class="stat-box"><div class="value"><?php echo $totalCount; ?></div><div class="label">Total</div></div>
    </div>
    
    <div class="card">
        <form method="GET">
            <div class="filter-grid">
                <div><label>From Date</label><input type="date" name="date_from" value="<?php echo $dateFrom; ?>"></div>
                <div><label>To Date</label><input type="date" name="date_to" value="<?php echo $dateTo; ?>"></div>
                <div class="full-width"><label>Search User</label><input type="text" name="user" placeholder="Type a name..." value="<?php echo htmlspecialchars($searchUser); ?>"></div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
                <a href="audit_trail.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
        
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): 
                            $roleBadge = $log['role'] === 'admin' ? 'badge-admin' : ($log['role'] === 'teacher' ? 'badge-teacher' : 'badge-student');
                        ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                                <td style="white-space:nowrap;"><strong><?php echo htmlspecialchars($log['full_name']); ?></strong></td>
                                <td><span class="badge <?php echo $roleBadge; ?>"><?php echo $log['role']; ?></span></td>
                                <td class="action-cell"><?php echo htmlspecialchars($log['action']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:2rem;">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php $qp = http_build_query(array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'user' => $searchUser])); ?>
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&<?php echo $qp; ?>"><i class="fas fa-chevron-left"></i> Prev</a><?php endif; ?>
            <span class="active">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&<?php echo $qp; ?>">Next <i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="danger-zone">
        <button class="btn btn-danger" onclick="openClearModal()"><i class="fas fa-exclamation-triangle"></i> Clear All Logs</button>
    </div>
    
    <div class="modal-overlay" id="clearModal">
        <div class="modal">
            <h3><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Delete All Logs?</h3>
            <p>This action cannot be undone. All activity history will be permanently deleted.</p>
            <div class="modal-btns">
                <button class="btn btn-outline" onclick="closeClearModal()">Cancel</button>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="clear_logs" class="btn btn-danger">Yes, Delete All</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleTheme() {
        document.body.classList.toggle("dark");
        var icon = document.querySelector("#themeBtn i");
        if (document.body.classList.contains("dark")) { if(icon) icon.className = "fas fa-sun"; localStorage.setItem("theme", "dark"); }
        else { if(icon) icon.className = "fas fa-moon"; localStorage.setItem("theme", "light"); }
    }
    if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); var i = document.querySelector("#themeBtn i"); if(i) i.className = "fas fa-sun"; }
    function openClearModal() { document.getElementById('clearModal').classList.add('active'); }
    function closeClearModal() { document.getElementById('clearModal').classList.remove('active'); }
    </script>
</body>
</html>
