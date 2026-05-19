<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if (!in_array($admin['role'], ['admin', 'class_rep'])) { header('Location: dashboard.php'); exit; }
    $isSuperAdmin = ($admin['role'] === 'admin');
    
    function logActivity($pdo, $userId, $action) {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action) VALUES (:uid, :a)');
        $stmt->execute(['uid' => $userId, 'a' => $action]);
    }
    
    if (isset($_GET['clear_logs'])) { $pdo->exec('DELETE FROM activity_logs'); header('Location: admin.php?msg=logs_cleared'); exit; }
    if ($isSuperAdmin && isset($_GET['delete_course'])) {
        $cid = intval($_GET['delete_course']);
        $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute(['id' => $cid]);
        logActivity($pdo, $_SESSION['user_id'], 'Deleted course ID ' . $cid);
        header('Location: admin.php?msg=course_deleted'); exit;
    }
    if (isset($_GET['approve'])) {
        $pdo->prepare('UPDATE students SET status = "approved" WHERE id = :id')->execute(['id' => intval($_GET['approve'])]);
        logActivity($pdo, $_SESSION['user_id'], 'Approved student ID ' . intval($_GET['approve']));
        header('Location: admin.php?msg=approved'); exit;
    }
    if (isset($_GET['reject'])) {
        $pdo->prepare('UPDATE students SET status = "rejected" WHERE id = :id')->execute(['id' => intval($_GET['reject'])]);
        logActivity($pdo, $_SESSION['user_id'], 'Rejected student ID ' . intval($_GET['reject']));
        header('Location: admin.php?msg=rejected'); exit;
    }
    if ($isSuperAdmin && isset($_GET['make_rep'])) {
        $pdo->prepare('UPDATE students SET role = "class_rep" WHERE id = :id')->execute(['id' => intval($_GET['make_rep'])]);
        logActivity($pdo, $_SESSION['user_id'], 'Made student ID ' . intval($_GET['make_rep']) . ' a class rep');
        header('Location: admin.php?msg=rep'); exit;
    }
    if ($isSuperAdmin && isset($_GET['remove_rep'])) {
        $pdo->prepare('UPDATE students SET role = "student" WHERE id = :id')->execute(['id' => intval($_GET['remove_rep'])]);
        logActivity($pdo, $_SESSION['user_id'], 'Removed class rep from student ID ' . intval($_GET['remove_rep']));
        header('Location: admin.php?msg=unrep'); exit;
    }
    if ($isSuperAdmin && isset($_GET['delete_user'])) {
        $pdo->prepare('DELETE FROM students WHERE id = :id AND role != "admin"')->execute(['id' => intval($_GET['delete_user'])]);
        logActivity($pdo, $_SESSION['user_id'], 'Deleted student ID ' . intval($_GET['delete_user']));
        header('Location: admin.php?msg=deleted'); exit;
    }
    if ($isSuperAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
        $name = trim($_POST['course_name'] ?? ''); $code = trim($_POST['course_code'] ?? ''); $desc = trim($_POST['course_desc'] ?? '');
        if ($name && $code) {
            $pdo->prepare('INSERT INTO courses (name, code, description) VALUES (:n, :c, :d)')->execute(['n' => $name, 'c' => $code, 'd' => $desc]);
            logActivity($pdo, $_SESSION['user_id'], 'Added course: ' . $name);
            header('Location: admin.php?msg=course_added'); exit;
        }
    }
    
    $search = trim($_GET['search'] ?? '');
    $filterCourse = $_GET['filter_course'] ?? '';
    $filterRole = $_GET['filter_role'] ?? '';
    $where = "WHERE s.status = 'approved'";
    $params = [];
    if ($search) { $where .= " AND (s.full_name LIKE :s OR s.student_id LIKE :s2)"; $params['s'] = "%$search%"; $params['s2'] = "%$search%"; }
    if ($filterCourse) { $where .= " AND s.course_id = :fc"; $params['fc'] = $filterCourse; }
    if ($filterRole) { $where .= " AND s.role = :fr"; $params['fr'] = $filterRole; }
    
    $stmt = $pdo->prepare("SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id $where ORDER BY c.name, s.full_name");
    $stmt->execute($params);
    $allStudents = $stmt->fetchAll();
    
    $groupedStudents = [];
    foreach ($allStudents as $s) { $courseKey = $s['course_name'] ?? 'Unassigned'; $groupedStudents[$courseKey][] = $s; }
    
    $pendingStudents = $pdo->query('SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.status = "pending" ORDER BY s.created_at DESC')->fetchAll();
    
    $totalStudents = $pdo->query('SELECT COUNT(*) FROM students WHERE status = "approved"')->fetchColumn();
    $pendingCount = $pdo->query('SELECT COUNT(*) FROM students WHERE status = "pending"')->fetchColumn();
    $totalNotes = $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
    $totalCourses = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $newThisWeek = $pdo->query('SELECT COUNT(*) FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
    
    $courses = $pdo->query('SELECT id, name, code, description FROM courses ORDER BY name')->fetchAll();
    $logs = $pdo->query('SELECT al.*, s.full_name FROM activity_logs al JOIN students s ON al.user_id = s.id ORDER BY al.created_at DESC LIMIT 8')->fetchAll();
    
    $msgs = ['approved' => 'Student approved.', 'rejected' => 'Student rejected.', 'rep' => 'Class rep assigned.', 'unrep' => 'Class rep removed.', 'deleted' => 'Student deleted.', 'course_added' => 'Course added.', 'course_deleted' => 'Course deleted.', 'logs_cleared' => 'Activity logs cleared.'];
    $message = $msgs[$_GET['msg'] ?? ''] ?? '';
    
} catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; }
        .mobile-header { display: none; background: #fff; padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .hamburger { background: none; border: none; cursor: pointer; padding: 0.5rem; display: flex; flex-direction: column; gap: 5px; }
        .hamburger span { display: block; width: 24px; height: 2px; background: #1a1a1a; border-radius: 2px; }
        .mobile-logo { font-weight: 700; font-size: 1.1rem; }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1.5rem; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 200; transition: transform 0.3s ease; }
        .sidebar-logo { font-size: 1.35rem; font-weight: 800; margin-bottom: 2rem; }
        .sidebar-nav { list-style: none; flex: 1; overflow-y: auto; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 8px; color: #4b5563; text-decoration: none; font-size: 0.85rem; font-weight: 500; border-left: 3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { border-left-color: #1a1a1a; font-weight: 600; }
        .sidebar-nav i { width: 18px; text-align: center; opacity: 0.5; font-size: 0.8rem; }
        .dropdown-menu { background: #f9fafb; border-radius: 8px; padding: 0.5rem !important; margin-top: 0.25rem; list-style: none; }
        .dropdown-menu li { padding: 0.3rem 0.5rem; border-radius: 6px; font-size: 0.78rem; display: flex; align-items: center; justify-content: space-between; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .sidebar-footer a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 150; }
        .sidebar-overlay.active { display: block; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
        .page-subtitle { color: #6b7280; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.25rem; border-radius: 14px; border: 1px solid #e5e7eb; }
        .stat-card.danger { border-left: 4px solid #ef4444; background: #fef2f2; }
        .stat-value { font-size: 1.75rem; font-weight: 800; }
        .stat-label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; font-weight: 600; }
        .stat-trend { font-size: 0.7rem; color: #16a34a; margin-top: 0.35rem; }
        .card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .card-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
        .filter-bar { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-input { padding: 0.55rem 0.8rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.85rem; background: #fff; flex: 1; min-width: 150px; }
        .filter-select { padding: 0.55rem 0.8rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.85rem; background: #fff; }
        .course-group { margin-bottom: 1rem; }
        .course-group-header { background: #f3f4f6; padding: 0.6rem 1rem; border-radius: 10px; font-size: 0.78rem; font-weight: 600; color: #4b5563; display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .student-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 0.4rem; }
        .student-summary { display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 1rem; cursor: pointer; }
        .student-summary-left { display: flex; align-items: center; gap: 0.75rem; }
        .student-avatar { width: 34px; height: 34px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; color: #6b7280; }
        .student-name { font-size: 0.88rem; font-weight: 600; }
        .student-meta { font-size: 0.72rem; color: #9ca3af; }
        .student-details { display: none; padding: 0.65rem 1rem; border-top: 1px solid #f3f4f6; font-size: 0.82rem; background: #fafafa; border-radius: 0 0 10px 10px; }
        .student-details.open { display: block; }
        .detail-row { display: flex; justify-content: space-between; padding: 0.25rem 0; }
        .detail-label { color: #9ca3af; font-size: 0.72rem; text-transform: uppercase; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.68rem; font-weight: 600; }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-rep { background: #ede9fe; color: #7c3aed; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .btn { padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem; font-family: inherit; }
        .btn-approve { background: #16a34a; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #4b5563; }
        .btn-primary { background: #1a1a1a; color: white; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1rem; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 100%; max-width: 450px; position: relative; }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .form-input { width: 100%; padding: 0.7rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; font-family: inherit; margin-bottom: 0.75rem; }
        .log-item { display: flex; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.8rem; align-items: center; }
        .log-item:last-child { border-bottom: none; }
        .log-time { color: #9ca3af; font-size: 0.72rem; white-space: nowrap; }
        @media (max-width: 768px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">Admin Panel</div>
        <div></div>
    </header>
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">Admin Panel</div>
        <ul class="sidebar-nav">
            <li><a href="admin.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="system_dashboard.php"><i class="fas fa-chart-pie"></i> System Overview</a></li>
            <li><a href="bulk_import.php"><i class="fas fa-file-csv"></i> Bulk Import</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup</a></li>
            <li><a href="audit_trail.php"><i class="fas fa-history"></i> Audit Trail</a></li>
            <?php if ($isSuperAdmin): ?>
            <li><a href="#" onclick="toggleDropdown(event)" style="justify-content:space-between;"><span><i class="fas fa-graduation-cap"></i> Courses</span><i class="fas fa-chevron-down" style="font-size:0.6rem;opacity:0.5;"></i></a>
                <ul class="dropdown-menu" id="courseDropdown" style="display:none;">
                    <?php foreach ($courses as $c): ?>
                    <li><span><?php echo htmlspecialchars($c['code']); ?></span><a href="admin.php?delete_course=<?php echo $c['id']; ?>" onclick="return confirm('Delete?')" style="color:#ef4444;font-size:0.7rem;"><i class="fas fa-trash"></i></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="#" onclick="openCourseModal()"><i class="fas fa-plus-circle"></i> Add Course</a></li>
            <?php endif; ?>
            <li><a href="teacher.php"><i class="fas fa-clipboard-check"></i> Teacher Panel</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Student View</a></li>
        </ul>
        <div class="sidebar-footer"><a href="logout.php">Sign out</a></div>
    </aside>
    
    <?php if ($isSuperAdmin): ?>
    <div class="modal-overlay" id="courseModal">
        <div class="modal">
            <button class="modal-close" onclick="closeCourseModal()">&times;</button>
            <h2 style="font-weight:700;margin-bottom:1rem;">Add New Course</h2>
            <form method="POST">
                <input type="text" name="course_name" class="form-input" placeholder="Course Name" required>
                <input type="text" name="course_code" class="form-input" placeholder="Course Code" required>
                <input type="text" name="course_desc" class="form-input" placeholder="Description">
                <button type="submit" name="add_course" class="btn btn-primary" style="width:100%;padding:0.7rem;">Add Course</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($admin['full_name']); ?> &middot; <?php echo $isSuperAdmin ? 'Super Admin' : 'Class Rep'; ?></p>
        
        <?php if ($message): ?><div class="alert alert-success" id="alertMsg"><?php echo $message; ?></div><script>setTimeout(function(){ var a=document.getElementById('alertMsg'); if(a)a.style.display='none'; }, 4000);</script><?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div class="stat-label">Total Students</div><?php if ($newThisWeek > 0): ?><div class="stat-trend"><i class="fas fa-arrow-up"></i> +<?php echo $newThisWeek; ?> this week</div><?php endif; ?></div>
            <div class="stat-card <?php echo $pendingCount > 0 ? 'danger' : ''; ?>"><div class="stat-value"><?php echo $pendingCount; ?></div><div class="stat-label">Pending</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalNotes; ?></div><div class="stat-label">Notes</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalCourses; ?></div><div class="stat-label">Courses</div></div>
        </div>
        
        <?php if ($pendingCount > 0): ?>
        <div class="card">
            <div class="card-title">Pending Approvals (<?php echo $pendingCount; ?>)</div>
            <?php foreach ($pendingStudents as $s): ?>
                <div class="student-card">
                    <div class="student-summary">
                        <div class="student-summary-left">
                            <div class="student-avatar"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                            <div><div class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></div><div class="student-meta"><?php echo htmlspecialchars($s['student_id']); ?></div></div>
                        </div>
                        <div style="display:flex;gap:0.25rem;">
                            <a href="admin.php?approve=<?php echo $s['id']; ?>" class="btn btn-approve">Approve</a>
                            <a href="admin.php?reject=<?php echo $s['id']; ?>" class="btn btn-reject" onclick="return confirm('Reject?')"><i class="fas fa-times"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-title">All Students (<?php echo $totalStudents; ?>)</div>
            <form class="filter-bar" method="GET">
                <input type="text" name="search" class="filter-input" placeholder="Search name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="filter_course" class="filter-select">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $filterCourse == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                </select>
                <select name="filter_role" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $filterRole == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="class_rep" <?php echo $filterRole == 'class_rep' ? 'selected' : ''; ?>>Class Rep</option>
                    <option value="student" <?php echo $filterRole == 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search || $filterCourse || $filterRole): ?><a href="admin.php" class="btn btn-outline">Clear</a><?php endif; ?>
            </form>
            
            <?php foreach ($groupedStudents as $courseName => $students): ?>
            <div class="course-group">
                <div class="course-group-header"><span><?php echo htmlspecialchars($courseName); ?></span><span><?php echo count($students); ?> student(s)</span></div>
                <?php foreach ($students as $s): 
                    $lastActive = $s['last_login'] ? floor((time() - strtotime($s['last_login'])) / 86400) : 999;
                    $activeColor = $lastActive <= 7 ? '#16a34a' : ($lastActive <= 30 ? '#f59e0b' : '#9ca3af');
                ?>
                    <div class="student-card">
                        <div class="student-summary" onclick="toggleDetails(<?php echo $s['id']; ?>)">
                            <div class="student-summary-left">
                                <div class="student-avatar"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                                <div>
                                    <div class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                    <div class="student-meta">
                                        <?php if ($s['role'] === 'admin'): ?><span class="badge badge-admin">Admin</span><?php elseif ($s['role'] === 'class_rep'): ?><span class="badge badge-rep">Rep</span><?php endif; ?>
                                        <span style="color:<?php echo $activeColor; ?>;"><i class="fas fa-circle" style="font-size:0.4rem;"></i></span>
                                    </div>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down" id="chevron<?php echo $s['id']; ?>" style="font-size:0.7rem;color:#9ca3af;"></i>
                        </div>
                        <div class="student-details" id="details<?php echo $s['id']; ?>">
                            <div class="detail-row"><span class="detail-label">Student ID</span><span><?php echo htmlspecialchars($s['student_id']); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Email</span><span><?php echo htmlspecialchars($s['email']); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Course</span><span><?php echo htmlspecialchars($s['course_name'] ?? 'N/A'); ?></span></div>
                            <?php if ($isSuperAdmin && $s['id'] != $_SESSION['user_id']): ?>
                            <div style="margin-top:0.5rem;display:flex;gap:0.35rem;">
                                <?php if ($s['role'] === 'student'): ?><a href="admin.php?make_rep=<?php echo $s['id']; ?>" class="btn btn-outline">Make Rep</a>
                                <?php elseif ($s['role'] === 'class_rep'): ?><a href="admin.php?remove_rep=<?php echo $s['id']; ?>" class="btn btn-outline">Remove Rep</a><?php endif; ?>
                                <a href="admin.php?delete_user=<?php echo $s['id']; ?>" class="btn btn-reject" onclick="return confirm('Delete?')">Delete</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <div class="card-title">Recent Activity</div>
            <?php foreach ($logs as $log): ?>
                <div class="log-item"><i class="fas fa-circle" style="font-size:0.35rem;color:#3b82f6;"></i><span style="flex:1;"><strong><?php echo htmlspecialchars($log['full_name']); ?></strong> <?php echo htmlspecialchars($log['action']); ?></span><span class="log-time"><?php echo date('H:i', strtotime($log['created_at'])); ?></span></div>
            <?php endforeach; ?>
            <a href="admin.php?clear_logs=1" class="btn btn-outline" style="margin-top:0.5rem;" onclick="return confirm('Clear all?')">Clear All</a>
        </div>
    </main>
    
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('active'); }
        function toggleDropdown(e) { e.preventDefault(); var m = document.getElementById('courseDropdown'); m.style.display = m.style.display === 'none' ? 'block' : 'none'; }
        function openCourseModal() { document.getElementById('courseModal').classList.add('active'); }
        function closeCourseModal() { document.getElementById('courseModal').classList.remove('active'); }
        function toggleDetails(id) {
            var d = document.getElementById('details' + id);
            var c = document.getElementById('chevron' + id);
            d.classList.toggle('open');
            c.style.transform = d.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    </script>
</body>
</html>
