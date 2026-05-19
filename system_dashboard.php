<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if ($admin['role'] !== 'admin') { header('Location: dashboard.php'); exit; }
    
    // Overall stats
    $totalStudents = $pdo->query('SELECT COUNT(*) FROM students WHERE status = "approved"')->fetchColumn();
    $pendingStudents = $pdo->query('SELECT COUNT(*) FROM students WHERE status = "pending"')->fetchColumn();
    $totalTeachers = $pdo->query("SELECT COUNT(*) FROM students WHERE role = 'teacher'")->fetchColumn();
    $totalCourses = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $totalNotes = $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
    $totalAssignments = $pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
    $totalSubmissions = $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    $gradedSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'graded'")->fetchColumn();
    
    // Storage
    $uploadDir = '/data/data/com.termux/files/home/web_uploads/';
    $totalSize = 0;
    $fileCount = 0;
    if (is_dir($uploadDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) { $totalSize += $file->getSize(); $fileCount++; }
        }
    }
    
    // Recent activity (last 24h)
    $recentActivity = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    
    // Active today
    $activeToday = $pdo->query("SELECT COUNT(*) FROM students WHERE last_login >= DATE(NOW())")->fetchColumn();
    
    // Course distribution
    $courseStats = $pdo->query('SELECT c.name, COUNT(s.id) as count FROM courses c LEFT JOIN students s ON c.id = s.course_id AND s.status = "approved" GROUP BY c.id ORDER BY count DESC')->fetchAll();
    
    // Latest registrations
    $latestStudents = $pdo->query('SELECT full_name, student_id, role, created_at FROM students ORDER BY created_at DESC LIMIT 8')->fetchAll();
    
    // Latest activity
    $logs = $pdo->query('SELECT al.*, s.full_name FROM activity_logs al JOIN students s ON al.user_id = s.id ORDER BY al.created_at DESC LIMIT 10')->fetchAll();
    
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
    <title>System Dashboard - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.5rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1.5rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; padding: 1rem; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; }
        .stat-card .value { font-size: 1.75rem; font-weight: 800; }
        .stat-card .label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.25rem; }
        .stat-card.warn { border-left: 4px solid #f59e0b; }
        .stat-card.info { border-left: 4px solid #3b82f6; }
        .stat-card.success { border-left: 4px solid #16a34a; }
        
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; }
        
        .bar-container { margin: 0.4rem 0; }
        .bar-label { display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.15rem; }
        .bar-track { height: 20px; background: #f3f4f6; border-radius: 6px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 6px; background: #3b82f6; transition: width 0.5s; }
        
        .log-item { display: flex; gap: 0.5rem; padding: 0.4rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.78rem; align-items: center; }
        .log-item:last-child { border-bottom: none; }
        .log-time { color: #9ca3af; font-size: 0.7rem; white-space: nowrap; }
        
        .student-item { display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.8rem; }
        .student-item:last-child { border-bottom: none; }
        .badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 10px; font-size: 0.6rem; font-weight: 700; }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-teacher { background: #fce7f3; color: #be185d; }
        .badge-student { background: #f3f4f6; color: #6b7280; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card, body.dark .stat-card { background: #1e293b; }
        body.dark .bar-track { background: #334155; }
        body.dark .log-item, body.dark .student-item { border-color: #334155; }
        @media (max-width: 700px) { .row-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="admin.php"><i class="fas fa-arrow-left"></i> Admin Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>System Dashboard</h1>
    <p class="subtitle"><?php echo htmlspecialchars($admin['full_name']); ?> — Super Admin Overview</p>
    
    <div class="stats-grid">
        <div class="stat-card success"><div class="value"><?php echo $totalStudents; ?></div><div class="label">Total Students</div></div>
        <div class="stat-card warn"><div class="value"><?php echo $pendingStudents; ?></div><div class="label">Pending</div></div>
        <div class="stat-card"><div class="value"><?php echo $totalTeachers; ?></div><div class="label">Teachers</div></div>
        <div class="stat-card"><div class="value"><?php echo $totalCourses; ?></div><div class="label">Courses</div></div>
        <div class="stat-card"><div class="value"><?php echo $totalNotes; ?></div><div class="label">Notes</div></div>
        <div class="stat-card"><div class="value"><?php echo $totalAssignments; ?></div><div class="label">Assignments</div></div>
        <div class="stat-card"><div class="value"><?php echo $gradedSubmissions; ?>/<?php echo $totalSubmissions; ?></div><div class="label">Graded</div></div>
        <div class="stat-card info"><div class="value"><?php echo formatSize($totalSize); ?></div><div class="label">Storage (<?php echo $fileCount; ?> files)</div></div>
        <div class="stat-card"><div class="value"><?php echo $activeToday; ?></div><div class="label">Active Today</div></div>
        <div class="stat-card"><div class="value"><?php echo $recentActivity; ?></div><div class="label">Actions (24h)</div></div>
    </div>
    
    <div class="row-grid">
        <div class="card">
            <h2>Students Per Course</h2>
            <?php $maxCount = max(array_column($courseStats, 'count')) ?: 1; ?>
            <?php foreach ($courseStats as $cs): ?>
                <div class="bar-container">
                    <div class="bar-label"><span><?php echo htmlspecialchars($cs['name']); ?></span><span><?php echo $cs['count']; ?></span></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?php echo round(($cs['count'] / $maxCount) * 100); ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <h2>Latest Registrations</h2>
            <?php foreach ($latestStudents as $s): 
                $bcls = $s['role'] === 'admin' ? 'badge-admin' : ($s['role'] === 'teacher' ? 'badge-teacher' : 'badge-student');
            ?>
                <div class="student-item">
                    <span><strong><?php echo htmlspecialchars($s['full_name']); ?></strong> <span class="badge <?php echo $bcls; ?>"><?php echo $s['role']; ?></span></span>
                    <span style="color:#9ca3af;font-size:0.75rem;"><?php echo date('M d', strtotime($s['created_at'])); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="card">
        <h2>Recent Activity</h2>
        <?php foreach ($logs as $log): ?>
            <div class="log-item">
                <i class="fas fa-circle" style="font-size:0.3rem;color:#3b82f6;"></i>
                <span style="flex:1;"><strong><?php echo htmlspecialchars($log['full_name']); ?></strong> <?php echo htmlspecialchars($log['action']); ?></span>
                <span class="log-time"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
            </div>
        <?php endforeach; ?>
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
