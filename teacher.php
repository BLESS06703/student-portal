<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $teacher = $stmt->fetch();
    
    if (!in_array($teacher['role'], ['teacher', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    $selectedCourse = $_GET['course'] ?? ($courses[0]['id'] ?? '');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
        $title = trim($_POST['assignment_title'] ?? '');
        $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $maxScore = intval($_POST['max_score'] ?? 100);
        $filePath = '';
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/assignments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'assignment_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['assignment_file']['name']);
            move_uploaded_file($_FILES['assignment_file']['tmp_name'], $uploadDir . $filename);
            $filePath = $filename;
        }
        if ($title) {
            $stmt = $pdo->prepare('INSERT INTO assignments (title, description, file_path, course_id, due_date, max_score, created_by) VALUES (:t, :d, :fp, :cid, :dd, :ms, :uid)');
            $stmt->execute(['t' => $title, 'd' => '', 'fp' => $filePath, 'cid' => $selectedCourse, 'dd' => $dueDate, 'ms' => $maxScore, 'uid' => $_SESSION['user_id']]);
            $message = 'Assignment created!';
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_resource'])) {
        $title = trim($_POST['title'] ?? '');
        if ($title && isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/';
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['resource_file']['name']);
            move_uploaded_file($_FILES['resource_file']['tmp_name'], $uploadDir . $filename);
            $stmt = $pdo->prepare('INSERT INTO notes (title, description, file_path, course_id, uploaded_by, file_size) VALUES (:t, :d, :fp, :cid, :uid, :fs)');
            $stmt->execute(['t' => $title, 'd' => 'Teacher resource', 'fp' => $filename, 'cid' => $selectedCourse, 'uid' => $_SESSION['user_id'], 'fs' => $_FILES['resource_file']['size']]);
            $message = 'Resource uploaded!';
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
        $submissionId = intval($_POST['submission_id']);
        $score = intval($_POST['score']);
        $feedback = trim($_POST['feedback'] ?? '');
        $maxScore = intval($_POST['max_score'] ?? 100);
        if (isset($_FILES['marked_file']) && $_FILES['marked_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/marked/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $markedFilename = 'marked_' . $submissionId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['marked_file']['name']);
            move_uploaded_file($_FILES['marked_file']['tmp_name'], $uploadDir . $markedFilename);
            $feedback .= "\n[MARKED_FILE:" . $markedFilename . "]";
        }
        if ($score >= 0 && $score <= $maxScore) {
            $stmt = $pdo->prepare('UPDATE submissions SET score = :sc, feedback = :fb, status = "graded", graded_at = NOW() WHERE id = :id');
            $stmt->execute(['sc' => $score, 'fb' => $feedback, 'id' => $submissionId]);
            $message = 'Assignment graded!';
        }
    }
    
    $stmt = $pdo->prepare('SELECT sub.id AS submission_id, sub.file_path, sub.status, sub.score, sub.feedback, sub.submitted_at, a.title AS assignment_title, a.max_score, s.student_id, s.full_name AS student_name FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id JOIN students s ON sub.student_id = s.id WHERE a.course_id = :cid ORDER BY sub.status ASC, sub.submitted_at DESC');
    $stmt->execute(['cid' => $selectedCourse]);
    $submissions = $stmt->fetchAll();
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1rem; max-width: 800px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        h1 { font-size: 1.4rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .msg { padding: 0.6rem 0.8rem; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.82rem; }
        .msg-ok { background: #f0fdf4; color: #16a34a; }
        .msg-err { background: #fef2f2; color: #dc2626; }
        
        .toolbar { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .toolbar a { padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; background: #fff; border: 1px solid #e5e7eb; color: #4b5563; }
        .toolbar a:hover { background: #f3f4f6; }
        .toolbar a.active-tool { background: #f15a24; color: white; border-color: #f15a24; }
        
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; }
        select, input { padding: 0.55rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.82rem; font-family: inherit; background: #f9fafb; width: 100%; margin-bottom: 0.4rem; }
        .row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 0.9rem; background: #1a1a1a; color: white; border: none; border-radius: 12px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn:hover { background: #333; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.7rem; border-radius: 8px; }
        .sub-item { padding: 0.6rem 0.75rem; border: 1px solid #f3f4f6; border-radius: 10px; margin-bottom: 0.4rem; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 15px; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-graded { background: #f0fdf4; color: #16a34a; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark select, body.dark input { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .toolbar a { background: #1e293b; border-color: #334155; color: #94a3b8; }
        body.dark .sub-item { border-color: #334155; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Teacher Panel</h1>
    <p class="subtitle"><?php echo htmlspecialchars($teacher['full_name']); ?></p>
    
    <div class="toolbar">
        <a href="teacher.php" class="active-tool"><i class="fas fa-home"></i> Main</a>
        <a href="bulk_grade.php"><i class="fas fa-layer-group"></i> Bulk Grade</a>
        <a href="grade_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
        <a href="templates.php"><i class="fas fa-copy"></i> Templates</a>
        <a href="student_report.php"><i class="fas fa-user-graduate"></i> Reports</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="notes.php"><i class="fas fa-file-alt"></i> Notes</a>
    </div>
    
    <?php if ($message): ?><div class="msg msg-ok"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="GET" style="margin-bottom:0.75rem;">
        <select name="course" onchange="this.form.submit()" style="width:auto;">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $selectedCourse == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <div class="card">
        <h2><i class="fas fa-plus-circle"></i> Create Assignment</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="assignment_title" placeholder="Assignment title" required>
            <div class="row">
                <input type="date" name="due_date" required style="flex:1;min-width:120px;">
                <input type="number" name="max_score" value="100" min="1" max="100" style="width:70px;">
            </div>
            <input type="file" name="assignment_file" accept=".pdf,.doc,.docx" required style="font-size:0.8rem;">
            <button type="submit" name="create_assignment" class="btn">Create</button>
        </form>
    </div>
    
    <div class="card">
        <h2><i class="fas fa-upload"></i> Upload Resource</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <input type="text" name="title" placeholder="Resource title" required style="flex:1;">
                <input type="file" name="resource_file" required style="flex:1;font-size:0.8rem;">
            </div>
            <button type="submit" name="upload_resource" class="btn">Upload</button>
        </form>
    </div>
    
    <div class="card">
        <h2><i class="fas fa-check-circle"></i> Grade Submissions (<?php echo count($submissions); ?>)</h2>
        <?php if (count($submissions) > 0): ?>
            <?php foreach ($submissions as $sub): ?>
                <div class="sub-item">
                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.4rem;">
                        <div>
                            <strong style="font-size:0.9rem;"><?php echo htmlspecialchars($sub['assignment_title']); ?></strong>
                            <div style="font-size:0.72rem;color:#6b7280;"><?php echo htmlspecialchars($sub['student_name']); ?> (<?php echo $sub['student_id']; ?>) &middot; <?php echo date('M d', strtotime($sub['submitted_at'])); ?></div>
                        </div>
                        <span class="badge <?php echo $sub['status'] === 'graded' ? 'badge-graded' : 'badge-pending'; ?>"><?php echo $sub['status']; ?></span>
                    </div>
                    <?php if ($sub['status'] === 'graded'): ?>
                        <div style="background:#f0fdf4;padding:0.4rem 0.6rem;border-radius:8px;margin-top:0.3rem;font-size:0.85rem;"><strong>Score: <?php echo $sub['score']; ?>/<?php echo $sub['max_score']; ?></strong></div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" style="margin-top:0.3rem;">
                            <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                            <input type="hidden" name="max_score" value="<?php echo $sub['max_score']; ?>">
                            <div class="row" style="align-items:center;">
                                <input type="number" name="score" placeholder="Score" required style="width:55px;">
                                <span style="font-size:0.8rem;">/ <?php echo $sub['max_score']; ?></span>
                                <input type="text" name="feedback" placeholder="Feedback" style="flex:1;min-width:90px;">
                            </div>
                            <input type="file" name="marked_file" style="font-size:0.7rem;">
                            <button type="submit" name="grade_submission" class="btn btn-sm">Grade</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6b7280;text-align:center;font-size:0.85rem;">No submissions yet.</p>
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
