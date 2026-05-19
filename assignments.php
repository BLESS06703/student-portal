<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['submission_file'])) {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $file = $_FILES['submission_file'];
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/png', 'image/jpeg'];
        $maxSize = 10 * 1024 * 1024;
        
        $stmt = $pdo->prepare('SELECT id, status FROM submissions WHERE assignment_id = :aid AND student_id = :sid');
        $stmt->execute(['aid' => $assignment_id, 'sid' => $_SESSION['user_id']]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['status'] === 'graded') { $error = 'Already graded.'; }
        elseif (!$assignment_id) { $error = 'Invalid assignment.'; }
        elseif ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload failed.'; }
        elseif ($file['size'] > $maxSize) { $error = 'File too large.'; }
        elseif (!in_array($file['type'], $allowed)) { $error = 'Invalid file type.'; }
        else {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/submissions/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'sub_' . $_SESSION['user_id'] . '_' . $assignment_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                if ($existing) {
                    $stmt = $pdo->prepare('UPDATE submissions SET file_path = :fp, status = "submitted", submitted_at = NOW() WHERE id = :id');
                    $stmt->execute(['fp' => $filename, 'id' => $existing['id']]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO submissions (assignment_id, student_id, file_path, status) VALUES (:aid, :sid, :fp, "submitted")');
                    $stmt->execute(['aid' => $assignment_id, 'sid' => $_SESSION['user_id'], 'fp' => $filename]);
                }
                header('Location: assignments.php?submitted=1'); exit;
            }
        }
    }
    
    $stmt = $pdo->prepare('SELECT a.*, sub.id AS sub_id, sub.status AS sub_status, sub.score, sub.feedback, sub.submitted_at FROM assignments a LEFT JOIN submissions sub ON a.id = sub.assignment_id AND sub.student_id = :sid WHERE a.course_id = :cid ORDER BY a.due_date ASC');
    $stmt->execute(['sid' => $_SESSION['user_id'], 'cid' => $student['course_id']]);
    $assignments = $stmt->fetchAll();
    
    if (isset($_GET['submitted'])) $message = 'Assignment submitted.';
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Student Portal</title>
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
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; margin-bottom: 2rem; }
        .sidebar-nav { list-style: none; flex: 1; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 12px; color: #4b5563; text-decoration: none; font-size: 0.85rem; font-weight: 500; border-left: 3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { border-left-color: #1a1a1a; font-weight: 600; }
        .sidebar-nav i { width: 18px; text-align: center; opacity: 0.5; font-size: 0.8rem; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .sidebar-footer a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 150; }
        .sidebar-overlay.active { display: block; }
        
        .main-content { margin-left: 260px; padding: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
        .page-subtitle { color: #6b7280; margin-bottom: 1.5rem; }
        
        .alert { padding: 0.75rem 1rem; border-radius: 14px; font-size: 0.85rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .alert-success { background: #f0fdf4; color: #16a34a; }
        .alert-error { background: #fef2f2; color: #dc2626; }
        
        .card { background: #fff; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .assignment-item { padding: 1.25rem 0; border-bottom: 1px solid #f3f4f6; }
        .assignment-item:last-child { border-bottom: none; }
        .assign-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .assign-title { font-size: 1rem; font-weight: 600; }
        .assign-file { font-size: 0.8rem; color: #6b7280; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem; }
        .assign-meta { display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: 0.78rem; color: #9ca3af; margin-bottom: 0.75rem; }
        .assign-meta i { width: 14px; }
        
        .status-badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fff7ed; color: #c2410c; }
        .status-submitted { background: #eff6ff; color: #1d4ed8; }
        .status-graded { background: #f0fdf4; color: #16a34a; }
        .status-overdue { background: #fef2f2; color: #dc2626; }
        
        .btn { padding: 0.5rem 1rem; background: #1a1a1a; color: white; border: none; border-radius: 14px; font-size: 0.8rem; font-weight: 600; cursor: pointer; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn:hover { background: #333; }
        .btn-outline { background: transparent; color: #1a1a1a; border: 1.5px solid #d1d5db; }
        .btn-sm { padding: 0.3rem 0.65rem; font-size: 0.72rem; border-radius: 10px; }
        .action-row { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem; align-items: center; }
        .upload-box { padding: 1.25rem; border: 2px dashed #d1d5db; border-radius: 14px; text-align: center; cursor: pointer; display: block; }
        .upload-box:hover { border-color: #1a1a1a; background: #fafafa; }
        .upload-box input[type="file"] { display: none; }
        .grade-box { background: #f0fdf4; padding: 1rem; border-radius: 12px; margin-top: 0.5rem; }
        .grade-score { font-size: 1.5rem; font-weight: 800; }
        
        @media (max-width: 1024px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .sidebar, body.dark .card, body.dark .mobile-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        body.dark .sidebar-nav a { color: #94a3b8; }
        body.dark .sidebar-nav a:hover, body.dark .sidebar-nav a.active { background: #334155; color: #fff; }
        body.dark .sidebar-logo, body.dark .page-title { color: #f1f5f9; }
        body.dark .sidebar { background: #1e293b; border-color: #334155; }
        body.dark .mobile-header { border-color: #334155; }
        body.dark .hamburger span { background: #e2e8f0; }
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
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="notes.php"><i class="fas fa-file-alt"></i> Course Notes</a></li>
            <li><a href="assignments.php" class="active"><i class="fas fa-clipboard-list"></i> Assignments</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1 class="page-title">Assignments</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></p>
        
        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?> <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;">&times;</button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?> <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;">&times;</button></div><?php endif; ?>
        
        <div class="card">
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $a): 
                    $dueDate = strtotime($a['due_date']);
                    $daysLeft = ceil(($dueDate - time()) / 86400);
                    $isOverdue = $daysLeft <= 0 && ($a['sub_status'] ?? '') === '';
                    $status = ($a['sub_status'] ?? '') === 'graded' ? 'graded' : (($a['sub_status'] ?? '') === 'submitted' ? 'submitted' : ($isOverdue ? 'overdue' : 'pending'));
                    $statusLabel = ['pending' => 'Pending', 'submitted' => 'Submitted', 'graded' => 'Graded', 'overdue' => 'Overdue'][$status];
                ?>
                    <div class="assignment-item">
                        <div class="assign-top">
                            <div>
                                <div class="assign-title"><?php echo htmlspecialchars($a['title']); ?></div>
                                <?php if ($a['file_path']): ?>
                                    <div class="assign-file"><i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($a['file_path']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge status-<?php echo $status; ?>"><?php echo $statusLabel; ?></span>
                        </div>
                        <div class="assign-meta">
                            <span><i class="fas fa-calendar-alt"></i> Due: <?php echo date('M d, Y', $dueDate); ?> &middot; <?php echo $daysLeft > 0 ? $daysLeft . ' days left' : 'Overdue'; ?></span>
                            <span><i class="fas fa-chart-bar"></i> Max: <?php echo $a['max_score']; ?> pts</span>
                            <?php if ($a['submitted_at']): ?>
                                <span><i class="fas fa-clock"></i> Submitted: <?php echo date('M d, g:i A', strtotime($a['submitted_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($status === 'graded'): ?>
                            <div class="grade-box">
                                <div class="grade-score"><?php echo $a['score']; ?>/<?php echo $a['max_score']; ?></div>
                                <?php 
                                $fb = $a['feedback'] ?? '';
                                if (strpos($fb, '[MARKED_FILE:') !== false) {
                                    preg_match('/\[MARKED_FILE:(.*?)\]/', $fb, $matches);
                                    $markedFile = $matches[1] ?? '';
                                    $fb = str_replace('[MARKED_FILE:' . $markedFile . ']', '', $fb);
                                    echo '<p style="margin-top:0.25rem;"><a href="download_marked.php?file=' . urlencode($markedFile) . '" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Marked Paper</a></p>';
                                }
                                if (trim($fb)) echo '<p style="margin-top:0.25rem;font-size:0.85rem;">' . nl2br(htmlspecialchars(trim($fb))) . '</p>';
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="action-row">
                                <?php if ($a['file_path']): ?>
                                    <a href="download_assignment.php?id=<?php echo $a['id']; ?>" class="btn btn-outline"><i class="fas fa-download"></i> Download</a>
                                <?php endif; ?>
                                <?php if ($status === 'submitted'): ?>
                                    <span style="color:#1d4ed8;display:flex;align-items:center;gap:0.35rem;"><i class="fas fa-check-circle"></i> Awaiting grading</span>
                                <?php else: ?>
                                    <form method="POST" enctype="multipart/form-data" style="flex:1;">
                                        <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                        <label class="upload-box" id="uploadBox<?php echo $a['id']; ?>">
                                            <div id="uploadText<?php echo $a['id']; ?>">
                                                <i class="fas fa-cloud-upload-alt" style="font-size:1.3rem;color:#9ca3af;"></i>
                                                <p style="margin-top:0.35rem;font-size:0.85rem;color:#6b7280;">Click to submit your work</p>
                                            </div>
                                            <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" required onchange="updateLabel(this, <?php echo $a['id']; ?>)">
                                        </label>
                                        <button type="submit" class="btn" style="margin-top:0.5rem;"><i class="fas fa-paper-plane"></i> Submit</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#6b7280;text-align:center;padding:2rem;">No assignments posted yet.</p>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('active'); }
        function updateLabel(input, id) {
            if (input.files && input.files[0]) {
                var f = input.files[0];
                document.getElementById('uploadText' + id).innerHTML = '<i class="fas fa-file" style="font-size:1.3rem;color:#1a1a1a;"></i><p style="margin-top:0.35rem;font-weight:600;">' + f.name + '</p>';
            }
        }
    </script>
</body>
</html>
<script>
function toggleTheme() {
    document.body.classList.toggle("dark");
    var icon = document.querySelector("#themeBtn i");
    if (document.body.classList.contains("dark")) {
        if(icon) icon.className = "fas fa-sun";
        localStorage.setItem("theme", "dark");
    } else {
        if(icon) icon.className = "fas fa-moon";
        localStorage.setItem("theme", "light");
    }
}
if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); }
</script>
