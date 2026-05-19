<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    // Stats
    $totalNotes = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE course_id = :cid');
    $totalNotes->execute(['cid' => $student['course_id']]);
    $notesCount = $totalNotes->fetchColumn();
    
    $myUploads = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE uploaded_by = :uid');
    $myUploads->execute(['uid' => $_SESSION['user_id']]);
    $myUploadsCount = $myUploads->fetchColumn();
    
    $totalDownloads = $pdo->prepare('SELECT COALESCE(SUM(downloads), 0) FROM notes WHERE course_id = :cid');
    $totalDownloads->execute(['cid' => $student['course_id']]);
    $downloadsCount = $totalDownloads->fetchColumn();
    
    // Recent notes
    $stmt = $pdo->prepare('SELECT n.*, s.full_name AS uploader_name FROM notes n JOIN students s ON n.uploaded_by = s.id WHERE n.course_id = :cid ORDER BY n.created_at DESC LIMIT 6');
    $stmt->execute(['cid' => $student['course_id']]);
    $recentNotes = $stmt->fetchAll();
    
    // Course modules
    $courseModules = [
        ['name' => 'Course Materials', 'icon' => 'fa-book', 'desc' => 'Access lecture notes and slides', 'link' => 'notes.php'],
        ['name' => 'Assignments', 'icon' => 'fa-clipboard-list', 'desc' => 'View and submit assignments', 'link' => 'assignments.php'],
        ['name' => 'Discussions', 'icon' => 'fa-comments', 'desc' => 'Join course discussions', 'link' => 'discussions.php'],
    ];
    
    // Assignments
    $stmt = $pdo->prepare('SELECT a.*, CASE WHEN s.id IS NOT NULL THEN s.status ELSE "pending" END AS submission_status FROM assignments a LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = :sid WHERE a.course_id = :cid ORDER BY a.due_date ASC');
    $stmt->execute(['sid' => $_SESSION['user_id'], 'cid' => $student['course_id']]);
    $assignments = $stmt->fetchAll();
    
    // Exam results
    $stmt = $pdo->prepare('SELECT * FROM exam_results WHERE student_id = :sid AND course_id = :cid ORDER BY exam_date DESC');
    $stmt->execute(['sid' => $_SESSION['user_id'], 'cid' => $student['course_id']]);
    $examResults = $stmt->fetchAll();
    
    // GPA
    $totalPoints = 0; $totalCourses = 0;
    $gradeMap = ['A' => 4.0, 'B+' => 3.5, 'B' => 3.0, 'C+' => 2.5, 'C' => 2.0, 'D' => 1.0, 'F' => 0];
    foreach ($examResults as $r) { $totalPoints += $gradeMap[$r['grade']] ?? 0; $totalCourses++; }
    $gpa = $totalCourses > 0 ? round($totalPoints / $totalCourses, 2) : 0;
    
    // Performance message
    $perfMessage = ''; $perfClass = '';
    if ($gpa >= 3.5) { $perfMessage = 'Excellent performance! Keep up the great work.'; $perfClass = 'perf-excellent'; }
    elseif ($gpa >= 3.0) { $perfMessage = 'Good progress. You\'re on the right track.'; $perfClass = 'perf-good'; }
    elseif ($gpa >= 2.0) { $perfMessage = 'Room for improvement. Consider reviewing course materials.'; $perfClass = 'perf-average'; }
    elseif ($totalCourses > 0) { $perfMessage = 'Your performance needs attention. Seek academic support.'; $perfClass = 'perf-low'; }
    
    // Announcements
    $stmt = $pdo->prepare('SELECT a.*, s.full_name FROM announcements a JOIN students s ON a.posted_by = s.id WHERE a.course_id = :cid ORDER BY a.created_at DESC LIMIT 3');
    $stmt->execute(['cid' => $student['course_id']]);
    $announcements = $stmt->fetchAll();
    
    $semesterLabel = 'Semester ' . ($student['semester'] ?? '1');
    $yearLabel = 'Year ' . $student['year_level'];
    
} catch (PDOException $e) { $error = 'Error loading dashboard.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        .mobile-header { display: none; background: #fff; padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .hamburger { background: none; border: none; cursor: pointer; padding: 0.5rem; display: flex; flex-direction: column; gap: 5px; }
        .hamburger span { display: block; width: 24px; height: 2px; background: #1a1a1a; border-radius: 2px; }
        .mobile-logo { font-weight: 700; font-size: 1.1rem; }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1.5rem; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 200; transition: transform 0.3s ease; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; margin-bottom: 2rem; letter-spacing: -0.5px; }
        .sidebar-nav { list-style: none; flex: 1; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 12px; color: #4b5563; text-decoration: none; font-size: 0.875rem; font-weight: 500; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; font-weight: 600; border-left-color: #1a1a1a; }
        .sidebar-nav i { width: 18px; text-align: center; opacity: 0.5; font-size: 0.8rem; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-footer .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: #1a1a1a; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600; }
        .sidebar-footer .user-info { flex: 1; }
        .sidebar-footer .user-name { font-size: 0.85rem; font-weight: 600; }
        .sidebar-footer .user-role { font-size: 0.75rem; color: #6b7280; }
        .sidebar-footer a { color: #6b7280; text-decoration: none; font-size: 0.8rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 150; }
        .sidebar-overlay.active { display: block; }
        .main-content { margin-left: 260px; padding: 2rem 2.5rem; max-width: 1200px; }
        .page-header { margin-bottom: 2rem; }
        .greeting { font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem; }
        .page-title { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px; }
        .page-subtitle { color: #6b7280; margin-top: 0.25rem; font-size: 0.9rem; }
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .metric-card { background: #fff; border-radius: 16px; padding: 1.25rem 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .metric-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.08); transform: translateY(-2px); transition: all 0.2s; }
        .metric-value { font-size: 2rem; font-weight: 800; letter-spacing: -1px; line-height: 1; }
        .metric-label { font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .metric-trend { font-size: 0.7rem; margin-top: 0.35rem; font-weight: 500; }
        .metric-trend.up { color: #16a34a; }
        .metric-trend.neutral { color: #6b7280; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .section-title { font-size: 1.1rem; font-weight: 700; letter-spacing: -0.3px; }
        .section-link { font-size: 0.85rem; color: #6b7280; text-decoration: none; font-weight: 500; }
        .modules-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .module-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-decoration: none; color: inherit; transition: all 0.2s; display: block; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .module-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .module-icon { width: 44px; height: 44px; background: #f3f4f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 1rem; color: #4b5563; }
        .module-name { font-size: 1rem; font-weight: 600; margin-bottom: 0.35rem; }
        .module-desc { font-size: 0.82rem; color: #6b7280; }
        .recent-notes-scroll { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 2rem; }
        .recent-notes-scroll::-webkit-scrollbar { height: 4px; }
        .recent-notes-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .recent-note-card { min-width: 220px; max-width: 220px; flex-shrink: 0; background: #fff; border-radius: 16px; padding: 1rem; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.05); scroll-snap-align: start; }
        .recent-note-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.08); transform: translateY(-2px); transition: all 0.2s; }
        .note-new-badge { position: absolute; top: 0.5rem; right: 0.5rem; background: #1a1a1a; color: white; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.65rem; font-weight: 700; }
        .note-file-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #6b7280; background: #f3f4f6; margin-bottom: 0.75rem; }
        .note-card-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; line-height: 1.3; }
        .note-card-meta { font-size: 0.7rem; color: #9ca3af; margin-bottom: 0.5rem; }
        .note-card-footer { display: flex; gap: 0.75rem; font-size: 0.7rem; color: #9ca3af; }
        .card { background: #fff; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .card-title { font-size: 1rem; font-weight: 700; }
        .card-badge { font-size: 0.7rem; color: #6b7280; background: #f3f4f6; padding: 0.25rem 0.65rem; border-radius: 20px; }
        .task-card { background: #fff; border-radius: 14px; padding: 1rem 1.25rem; border-left: 4px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 0.5rem; }
        .task-card.overdue { border-left-color: #ef4444; }
        .task-card.due-soon { border-left-color: #f59e0b; }
        .task-info { flex: 1; min-width: 150px; }
        .task-title { font-size: 0.9rem; font-weight: 600; }
        .task-meta { font-size: 0.75rem; color: #9ca3af; }
        .task-actions { display: flex; gap: 0.5rem; align-items: center; }
        .btn { padding: 0.55rem 1rem; background: #1a1a1a; color: white; border: none; border-radius: 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #333; }
        .btn-outline { background: transparent; color: #1a1a1a; border: 1.5px solid #d1d5db; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 10px; }
        .badge { display: inline-block; padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-new { background: #dbeafe; color: #1d4ed8; }
        .empty-state { text-align: center; padding: 2rem; color: #9ca3af; }
        .perf-alert { border-left: 4px solid #e5e7eb; }
        .perf-excellent { border-left-color: #16a34a; background: #f0fdf4; }
        .perf-good { border-left-color: #3b82f6; background: #eff6ff; }
        .perf-average { border-left-color: #f59e0b; background: #fffbeb; }
        .perf-low { border-left-color: #ef4444; background: #fef2f2; }
        .progress-bar { width: 100%; height: 4px; background: #f3f4f6; border-radius: 2px; margin-top: 0.25rem; }
        .progress-fill { height: 100%; border-radius: 2px; }
        @media (max-width: 1024px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .metrics-grid { grid-template-columns: 1fr 1fr; }
            .modules-grid { grid-template-columns: 1fr; }
            .metric-value { font-size: 1.5rem; }
        }
        /* Dark Mode */
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .sidebar, body.dark .card, body.dark .metric-card, body.dark .module-card, body.dark .recent-note-card, body.dark .task-card, body.dark .mobile-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        body.dark .sidebar-nav a { color: #94a3b8; }
        body.dark .sidebar-nav a:hover, body.dark .sidebar-nav a.active { background: #334155; color: #fff; }
        body.dark .metric-label, body.dark .page-subtitle, body.dark .greeting, body.dark .module-desc, body.dark .note-card-meta, body.dark .task-meta, body.dark .section-link { color: #94a3b8; }
        body.dark .sidebar-logo, body.dark .page-title, body.dark .metric-value, body.dark .card-title, body.dark .module-name, body.dark .note-card-title, body.dark .task-title { color: #f1f5f9; }
        body.dark .user-avatar { background: #334155; }
        body.dark .module-icon, body.dark .note-file-icon { background: #334155; color: #94a3b8; }
        body.dark .sidebar { background: #1e293b; border-color: #334155; }
        body.dark .sidebar-footer { border-color: #334155; }
        body.dark .mobile-header { border-color: #334155; }
        body.dark .hamburger span { background: #e2e8f0; }
        body.dark .perf-excellent { background: #052e16; }
        body.dark .perf-good { background: #172554; }
        body.dark .perf-average { background: #451a03; }
        body.dark .perf-low { background: #450a0a; }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">Student Portal</div>
            <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
        <div></div>
    </header>
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">Student Portal</div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="notes.php"><i class="fas fa-file-alt"></i> Course Notes</a></li>
            <li><a href="assignments.php"><i class="fas fa-clipboard-list"></i> Assignments</a></li>
            <?php if (in_array($student["role"] ?? "", ["teacher", "admin"])): ?><li><a href="teacher.php"><i class="fas fa-clipboard-check"></i> Teacher Panel</a></li><?php endif; ?>
            <?php if (in_array($student["role"] ?? "", ["admin"])): ?><li><a href="admin.php"><i class="fas fa-cog"></i> Admin Panel</a></li><?php endif; ?>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-avatar"><?php echo strtoupper(substr($student['full_name'] ?? 'U', 0, 1)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars(explode(' ', $student['full_name'] ?? 'User')[0]); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($student['course_code'] ?? 'N/A'); ?></div>
            </div>
            <a href="logout.php">Sign out</a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <p class="greeting">Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>, <?php echo htmlspecialchars(explode(' ', $student['full_name'] ?? 'User')[0]); ?></p>
            <h1 class="page-title"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></h1>
            <p class="page-subtitle"><?php echo $yearLabel; ?> &middot; <?php echo $semesterLabel; ?> &middot; <?php echo htmlspecialchars($student['student_id'] ?? ''); ?></p>
        </div>
        
        <!-- Metric Cards -->
        <div class="metrics-grid">
            <div class="metric-card"><div class="metric-value"><?php echo $notesCount; ?></div><div class="metric-label">Course Notes</div><div class="metric-trend neutral">Available resources</div></div>
            <div class="metric-card"><div class="metric-value"><?php echo $myUploadsCount; ?></div><div class="metric-label">My Uploads</div><div class="metric-trend up">Contributions made</div></div>
            <div class="metric-card"><div class="metric-value"><?php echo $downloadsCount; ?></div><div class="metric-label">Total Downloads</div><div class="metric-trend neutral">Course-wide activity</div></div>
            <div class="metric-card"><div class="metric-value"><?php echo ($student['year_level'] ?? 1) . '.' . ($student['semester'] ?? 1); ?></div><div class="metric-label">Current Level</div><div class="metric-trend neutral"><?php echo $semesterLabel; ?></div></div>
        </div>
        
        <!-- Quick Access -->
        <div class="section-header"><h2 class="section-title">Quick Access</h2><a href="notes.php" class="section-link">View all</a></div>
        <div class="modules-grid">
            <?php foreach ($courseModules as $mod): ?>
                <a href="<?php echo $mod['link']; ?>" class="module-card">
                    <div class="module-icon"><i class="fas <?php echo $mod['icon']; ?>"></i></div>
                    <div class="module-name"><?php echo $mod['name']; ?></div>
                    <div class="module-desc"><?php echo $mod['desc']; ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Announcements -->
        <?php if (count($announcements) > 0): ?>
        <div class="section-header"><h2 class="section-title">Announcements</h2></div>
        <?php foreach ($announcements as $a): ?>
            <div class="card" style="margin-bottom:0.5rem;border-left:4px solid #7c3aed;">
                <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($a['title']); ?></div>
                <div style="font-size:0.78rem;color:#6b7280;"><?php echo htmlspecialchars($a['full_name']); ?> &middot; <?php echo date('M d', strtotime($a['created_at'])); ?></div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Recent Notes -->
        <div class="section-header"><h2 class="section-title">Recent Notes</h2><a href="notes.php" class="section-link">Browse all</a></div>
        <div class="recent-notes-scroll">
            <?php if (count($recentNotes) > 0): ?>
                <?php foreach ($recentNotes as $note): 
                    $daysAgo = floor((time() - strtotime($note['created_at'])) / 86400);
                    $isNew = $daysAgo <= 2;
                    $fileExt = strtolower(pathinfo($note['file_path'], PATHINFO_EXTENSION));
                    $borderColors = ['pdf' => '#ef4444', 'doc' => '#3b82f6', 'docx' => '#3b82f6', 'txt' => '#6b7280', 'png' => '#8b5cf6', 'jpg' => '#8b5cf6'];
                    $fileIcons = ['pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word', 'txt' => 'fa-file-alt', 'png' => 'fa-file-image', 'jpg' => 'fa-file-image'];
                    $borderColor = $borderColors[$fileExt] ?? '#e5e7eb';
                    $fileIcon = $fileIcons[$fileExt] ?? 'fa-file';
                ?>
                    <div class="recent-note-card" style="border-top: 4px solid <?php echo $borderColor; ?>;">
                        <?php if ($isNew): ?><span class="note-new-badge">New</span><?php endif; ?>
                        <div class="note-file-icon"><i class="fas <?php echo $fileIcon; ?>"></i></div>
                        <div class="note-card-title"><?php echo htmlspecialchars($note['title']); ?></div>
                        <div class="note-card-meta"><?php echo htmlspecialchars($note['uploader_name']); ?> &middot; <?php echo date('M d', strtotime($note['created_at'])); ?></div>
                        <div class="note-card-footer">
                            <span><i class="fas fa-download"></i> <?php echo $note['downloads']; ?></span>
                            <span><?php echo number_format($note['file_size'] / 1024, 0); ?> KB</span>
                        </div>
                        <a href="download.php?id=<?php echo $note['id']; ?>" class="btn btn-sm" style="width:100%;text-align:center;margin-top:0.5rem;">Download</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="width:100%;"><p>No notes yet. Be the first to upload!</p></div>
            <?php endif; ?>
        </div>
        
        <!-- Academic Performance -->
        <div class="section-header"><h2 class="section-title">Academic Performance</h2></div>
        
        <?php if ($perfMessage): ?>
        <div class="card perf-alert <?php echo $perfClass; ?>">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <span style="font-size:1.5rem;"><?php echo $gpa >= 3.5 ? '&#9733;' : ($gpa >= 2.0 ? '&#128218;' : '&#9888;'); ?></span>
                <div><strong>GPA: <?php echo number_format($gpa, 2); ?></strong><p style="margin:0.25rem 0 0 0;font-size:0.85rem;color:#6b7280;"><?php echo $perfMessage; ?></p></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Pending Assignments -->
            <div class="card">
                <div class="card-header"><span class="card-title">Pending Assignments</span><span class="card-badge"><?php echo count($assignments); ?> total</span></div>
                <?php if (count($assignments) > 0): ?>
                    <?php foreach ($assignments as $a): $dueDate = strtotime($a['due_date']); $daysLeft = ceil(($dueDate - time()) / 86400); $bc = $daysLeft <= 0 ? 'overdue' : ($daysLeft <= 3 ? 'due-soon' : ''); ?>
                        <div class="task-card <?php echo $bc; ?>">
                            <div class="task-info"><div class="task-title"><?php echo htmlspecialchars($a['title']); ?></div><div class="task-meta">Due: <?php echo date('M d, Y', $dueDate); ?> (<?php echo $daysLeft > 0 ? $daysLeft.'d left' : 'Overdue'; ?>)</div></div>
                            <div class="task-actions"><?php if (($a['submission_status'] ?? '') === 'pending'): ?><span class="badge badge-new">Action Needed</span><?php elseif (($a['submission_status'] ?? '') === 'graded'): ?><span class="badge" style="background:#f0fdf4;color:#16a34a;">Graded</span><?php else: ?><span class="badge" style="background:#fef3c7;color:#b45309;">Submitted</span><?php endif; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?><div class="empty-state" style="padding:1rem;"><p>No pending assignments.</p></div><?php endif; ?>
            </div>
            
            <!-- Exam Results -->
            <div class="card">
                <div class="card-header"><span class="card-title">Exam Results</span><span class="card-badge">GPA: <?php echo number_format($gpa, 2); ?></span></div>
                <?php if (count($examResults) > 0): ?>
                    <?php foreach ($examResults as $r): $gc = $r['grade'] === 'A' ? '#16a34a' : ($r['grade'] === 'B' ? '#3b82f6' : ($r['grade'] === 'C' ? '#f59e0b' : '#ef4444')); $pct = round(($r['score'] / $r['total_marks']) * 100); ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid #f3f4f6;gap:1rem;flex-wrap:wrap;">
                            <div style="flex:1;min-width:120px;"><div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($r['exam_title']); ?></div><div style="font-size:0.75rem;color:#9ca3af;"><?php echo date('M d, Y', strtotime($r['exam_date'])); ?> &middot; Semester <?php echo $r['semester']; ?></div></div>
                            <div style="text-align:right;"><div style="font-weight:700;font-size:1.1rem;color:<?php echo $gc; ?>;"><?php echo $r['grade']; ?></div><div style="font-size:0.75rem;color:#6b7280;"><?php echo $r['score']; ?>/<?php echo $r['total_marks']; ?> (<?php echo $pct; ?>%)</div></div>
                            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $gc; ?>;"></div></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?><div class="empty-state" style="padding:1rem;"><p>No exam results yet.</p></div><?php endif; ?>
            </div>
        </div>
        
        <!-- Analytics -->
        <div class="card">
            <div class="card-header"><span class="card-title">Performance Analytics</span><span class="card-badge">Semester <?php echo $student['semester'] ?? '1'; ?></span></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(100px, 1fr));gap:1rem;">
                <div style="text-align:center;padding:1rem;"><div style="font-size:2rem;font-weight:800;"><?php echo number_format($gpa, 2); ?></div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Current GPA</div></div>
                <div style="text-align:center;padding:1rem;"><div style="font-size:2rem;font-weight:800;"><?php echo count($examResults); ?></div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Exams Taken</div></div>
                <div style="text-align:center;padding:1rem;"><div style="font-size:2rem;font-weight:800;"><?php echo $myUploadsCount; ?></div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Notes Shared</div></div>
            </div>
        </div>
    </main>
    
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('active'); }
        function toggleTheme() {
            document.body.classList.toggle("dark");
            var btn = document.getElementById("themeBtn");
            var icon = btn.querySelector("i");
            if (document.body.classList.contains("dark")) {
                icon.className = "fas fa-sun";
                localStorage.setItem("theme", "dark");
            } else {
                icon.className = "fas fa-moon";
                localStorage.setItem("theme", "light");
            }
        }
        if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); document.getElementById("themeBtn").querySelector("i").className = "fas fa-sun"; }
    </script>
</body>
</html>
