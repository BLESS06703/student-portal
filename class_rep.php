<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $rep = $stmt->fetch();
    
    // Only class rep or admin can access
    if (!in_array($rep['role'], ['class_rep', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courseId = $rep['course_id'];
    $message = '';
    
    // Post announcement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if ($title && $msg) {
            $stmt = $pdo->prepare('INSERT INTO announcements (course_id, title, message, posted_by) VALUES (:cid, :t, :m, :uid)');
            $stmt->execute(['cid' => $courseId, 't' => $title, 'm' => $msg, 'uid' => $_SESSION['user_id']]);
            header('Location: class_rep.php?posted=1'); exit;
        }
    }
    
    // Delete announcement
    if (isset($_GET['delete_announcement'])) {
        $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id AND course_id = :cid');
        $stmt->execute(['id' => intval($_GET['delete_announcement']), 'cid' => $courseId]);
        header('Location: class_rep.php?deleted=1'); exit;
    }
    
    // Get announcements
    $stmt = $pdo->prepare('SELECT a.*, s.full_name FROM announcements a JOIN students s ON a.posted_by = s.id WHERE a.course_id = :cid ORDER BY a.created_at DESC LIMIT 20');
    $stmt->execute(['cid' => $courseId]);
    $announcements = $stmt->fetchAll();
    
    // Get students in course
    $stmt = $pdo->prepare('SELECT * FROM students WHERE course_id = :cid AND status = "approved" ORDER BY full_name');
    $stmt->execute(['cid' => $courseId]);
    $students = $stmt->fetchAll();
    
    // Stats
    $studentCount = count($students);
    $announcementCount = count($announcements);
    $notesCount = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE course_id = :cid');
    $notesCount->execute(['cid' => $courseId]);
    $totalNotes = $notesCount->fetchColumn();
    
    if (isset($_GET['posted'])) $message = 'Announcement posted successfully.';
    if (isset($_GET['deleted'])) $message = 'Announcement deleted.';
    
} catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Rep Panel - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1.5rem; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 200; }
        .sidebar-logo { font-size: 1.35rem; font-weight: 800; margin-bottom: 2rem; }
        .sidebar-nav { list-style: none; flex: 1; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 12px; color: #4b5563; text-decoration: none; font-size: 0.85rem; font-weight: 500; border-left: 3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { border-left-color: #1a1a1a; font-weight: 600; }
        .sidebar-nav i { width: 18px; text-align: center; opacity: 0.5; font-size: 0.8rem; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .sidebar-footer a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
        .page-subtitle { color: #6b7280; margin-bottom: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.25rem; border-radius: 14px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-value { font-size: 1.75rem; font-weight: 800; }
        .stat-label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.25rem; font-weight: 600; }
        .card { background: #fff; border-radius: 14px; border: none; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.85rem; margin-bottom: 1rem; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .form-input, .form-textarea { width: 100%; padding: 0.75rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 14px; font-size: 0.9rem; font-family: inherit; margin-bottom: 0.75rem; }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .btn { padding: 0.65rem 1.25rem; background: #1a1a1a; color: white; border: none; border-radius: 14px; font-size: 0.85rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #333; }
        .btn-danger { background: #ef4444; } .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 0.35rem 0.65rem; font-size: 0.72rem; border-radius: 10px; }
        .announcement-item { padding: 1rem 0; border-bottom: 1px solid #f3f4f6; }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-title { font-size: 0.95rem; font-weight: 600; margin-bottom: 0.35rem; }
        .announcement-message { font-size: 0.85rem; color: #4b5563; margin-bottom: 0.5rem; }
        .announcement-meta { font-size: 0.72rem; color: #9ca3af; display: flex; gap: 0.75rem; align-items: center; }
        .student-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.75rem; }
        .student-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f9fafb; border-radius: 14px; }
        .student-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; color: #6b7280; flex-shrink: 0; }
        .student-info { flex: 1; }
        .student-name { font-size: 0.85rem; font-weight: 600; }
        .student-id { font-size: 0.72rem; color: #6b7280; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-rep { background: #ede9fe; color: #7c3aed; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .student-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header style="display:none;background:#fff;padding:0.75rem 1rem;border-bottom:1px solid #e5e7eb;align-items:center;gap:0.75rem;" id="mobHeader">
    <button onclick="history.back()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;">&larr;</button>
    <span style="font-weight:700;">Class Rep Panel</span>
</header>
<style>@media(max-width:1024px){#mobHeader{display:flex!important;}}</style>
    <aside class="sidebar">
        <div class="sidebar-logo">Class Rep Panel</div>
        <ul class="sidebar-nav">
            <li><a href="class_rep.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Student View</a></li>
            <li><a href="admin.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1 class="page-title">Class Rep Panel</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($rep['course_name'] . ' (' . $rep['course_code'] . ')'); ?> &middot; <?php echo htmlspecialchars($rep['full_name']); ?></p>
        
        <?php if ($message): ?><div class="alert alert-success" id="alertMsg"><?php echo $message; ?></div>
        <script>setTimeout(function(){ var a=document.getElementById('alertMsg'); if(a)a.style.display='none'; }, 4000);</script><?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $studentCount; ?></div><div class="stat-label">Students in Course</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalNotes; ?></div><div class="stat-label">Course Notes</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $announcementCount; ?></div><div class="stat-label">Announcements</div></div>
        </div>
        
        <!-- Post Announcement -->
        <div class="card">
            <div class="card-title">Post Announcement</div>
            <form method="POST">
                <input type="text" name="title" class="form-input" placeholder="Announcement title..." required>
                <textarea name="message" class="form-textarea" placeholder="Write your announcement here..." required></textarea>
                <button type="submit" name="post_announcement" class="btn"><i class="fas fa-paper-plane"></i> Post Announcement</button>
            </form>
        </div>
        
        <!-- Announcements List -->
        <div class="card">
            <div class="card-title">Recent Announcements (<?php echo $announcementCount; ?>)</div>
            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $a): ?>
                    <div class="announcement-item">
                        <div class="announcement-title"><?php echo htmlspecialchars($a['title']); ?></div>
                        <div class="announcement-message"><?php echo nl2br(htmlspecialchars($a['message'])); ?></div>
                        <div class="announcement-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($a['full_name']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($a['created_at'])); ?></span>
                            <a href="class_rep.php?delete_announcement=<?php echo $a['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#6b7280;text-align:center;padding:1rem;">No announcements yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Students List -->
        <div class="card">
            <div class="card-title">Students in <?php echo htmlspecialchars($rep['course_name']); ?> (<?php echo $studentCount; ?>)</div>
            <div class="student-list">
                <?php foreach ($students as $s): ?>
                    <div class="student-item">
                        <div class="student-avatar"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                        <div class="student-info">
                            <div class="student-name">
                                <?php echo htmlspecialchars($s['full_name']); ?>
                                <?php if ($s['role'] === 'class_rep'): ?><span class="badge badge-rep">Rep</span><?php endif; ?>
                            </div>
                            <div class="student-id"><?php echo htmlspecialchars($s['student_id']); ?> &middot; Year <?php echo $s['year_level']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
<?php
// Attendance section added at end
?>
