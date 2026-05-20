<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if ($admin['role'] !== 'admin') { header('Location: dashboard.php'); exit; }
    
    $backupDir = '/data/data/com.termux/files/home/backups/';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    
    // Create backup
    if (isset($_POST['create_backup'])) {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        $command = "mariadb-dump -u appuser -pAppP@ssw0rd! secure_app > " . escapeshellarg($filepath) . " 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            $size = filesize($filepath);
            $message = "Backup created: $filename (" . round($size / 1024, 1) . " KB)";
            
            // Log activity
            $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action) VALUES (:uid, :a)');
            $stmt->execute(['uid' => $_SESSION['user_id'], 'a' => 'Created backup: ' . $filename]);
        } else {
            $error = 'Backup failed. Check database connection.';
        }
    }
    
    // Delete backup
    if (isset($_GET['delete'])) {
        $file = basename($_GET['delete']);
        $filepath = $backupDir . $file;
        if (file_exists($filepath)) {
            unlink($filepath);
            $message = 'Backup deleted.';
        }
    }
    
    // Restore backup
    if (isset($_POST['restore_backup']) && isset($_FILES['restore_file'])) {
        $file = $_FILES['restore_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $command = "mariadb -u appuser -pAppP@ssw0rd! secure_app < " . escapeshellarg($file['tmp_name']) . " 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $message = 'Database restored successfully!';
                $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action) VALUES (:uid, :a)');
                $stmt->execute(['uid' => $_SESSION['user_id'], 'a' => 'Restored database from file']);
            } else {
                $error = 'Restore failed. Check the SQL file.';
            }
        }
    }
    
    // List backups
    $backups = [];
    if (is_dir($backupDir)) {
        $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $f) {
            if (strpos($f, '.sql') !== false) {
                $fp = $backupDir . $f;
                $backups[] = [
                    'name' => $f,
                    'size' => filesize($fp),
                    'date' => date('Y-m-d H:i:s', filemtime($fp))
                ];
            }
        }
    }
    
    // Database stats
    $dbSize = 0;
    $tables = $pdo->query('SHOW TABLE STATUS')->fetchAll();
    foreach ($tables as $t) { $dbSize += ($t['Data_length'] ?? 0) + ($t['Index_length'] ?? 0); }
    $totalRows = $pdo->query('SELECT SUM(TABLE_ROWS) FROM information_schema.tables WHERE table_schema = "secure_app"')->fetchColumn();
    
} catch (PDOException $e) { $error = $e->getMessage(); }

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 800px; margin: 0 auto; }
        h1 { font-size: 1.4rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .msg { padding: 0.6rem 0.8rem; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.82rem; }
        .msg-ok { background: #f0fdf4; color: #16a34a; }
        .msg-err { background: #fef2f2; color: #dc2626; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; }
        .btn { padding: 0.55rem 1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.3rem; font-family: inherit; }
        .btn-primary { background: #f15a24; color: white; }
        .btn-primary:hover { background: #d44a1a; }
        .btn-outline { background: transparent; border: 1.5px solid #d1d5db; color: #4b5563; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.7rem; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .stat-box { background: #f9fafb; padding: 1rem; border-radius: 12px; text-align: center; }
        .stat-box .value { font-size: 1.4rem; font-weight: 800; }
        .stat-box .label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        
        .backup-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; gap: 0.5rem; }
        .backup-item:last-child { border-bottom: none; }
        .backup-name { font-weight: 500; font-size: 0.85rem; }
        .backup-meta { font-size: 0.72rem; color: #9ca3af; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark .stat-box { background: #0f172a; }
        body.dark .backup-item { border-color: #334155; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="admin.php"><i class="fas fa-arrow-left"></i> Admin Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Database Backup</h1>
    <p class="subtitle">Create and manage database backups</p>
    
    <?php if ($message): ?><div class="msg msg-ok"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="stats-row">
        <div class="stat-box"><div class="value"><?php echo formatSize($dbSize); ?></div><div class="label">Database Size</div></div>
        <div class="stat-box"><div class="value"><?php echo $totalRows; ?></div><div class="label">Total Records</div></div>
        <div class="stat-box"><div class="value"><?php echo count($tables); ?></div><div class="label">Tables</div></div>
        <div class="stat-box"><div class="value"><?php echo count($backups); ?></div><div class="label">Backups</div></div>
    </div>
    
    <div class="card">
        <h2>Create Backup</h2>
        <form method="POST">
            <button type="submit" name="create_backup" class="btn btn-primary"><i class="fas fa-download"></i> Create New Backup</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Restore Backup</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="restore_file" accept=".sql" required style="margin-bottom:0.5rem;">
            <button type="submit" name="restore_backup" class="btn btn-danger" onclick="return confirm('Restoring will overwrite the current database. Continue?')"><i class="fas fa-upload"></i> Restore Database</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Backup History (<?php echo count($backups); ?>)</h2>
        <?php if (count($backups) > 0): ?>
            <?php foreach ($backups as $b): ?>
                <div class="backup-item">
                    <div>
                        <div class="backup-name"><i class="fas fa-file"></i> <?php echo htmlspecialchars($b['name']); ?></div>
                        <div class="backup-meta"><?php echo formatSize($b['size']); ?> &middot; <?php echo $b['date']; ?></div>
                    </div>
                    <div style="display:flex;gap:0.3rem;">
                        <a href="/data/data/com.termux/files/home/backups/<?php echo urlencode($b['name']); ?>" download class="btn btn-outline btn-sm"><i class="fas fa-download"></i></a>
                        <a href="?delete=<?php echo urlencode($b['name']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this backup?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6b7280;text-align:center;">No backups yet.</p>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleTheme() {
        document.body.classList.toggle("dark");
        var icon = document.querySelector("#themeBtn i");
        if (document.body.classList.contains("dark")) { if(icon) icon.className = "fas fa-sun"; localStorage.setItem("theme", "dark"); }
        else { if(icon) icon.className = "fas fa-moon"; localStorage.setItem("theme", "light"); }
    }
    if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); var i = document.querySelector("#themeBtn i"); if(i) i.className = "fas fa-sun"; }
    </script>
</body>
</html>
