<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $teacher = $stmt->fetch();
    
    if (!in_array($teacher['role'], ['teacher', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    $selectedCourse = $_GET['course'] ?? ($courses[0]['id'] ?? '');
    $selectedAssignment = $_GET['assignment'] ?? '';
    
    // Get assignments for course
    $stmt = $pdo->prepare('SELECT id, title, max_score FROM assignments WHERE course_id = :cid ORDER BY title');
    $stmt->execute(['cid' => $selectedCourse]);
    $assignments = $stmt->fetchAll();
    
    // Handle bulk grading
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_grade'])) {
        $scores = $_POST['score'] ?? [];
        $feedbacks = $_POST['feedback'] ?? [];
        $count = 0;
        
        foreach ($scores as $submissionId => $score) {
            if ($score !== '') {
                $feedback = $feedbacks[$submissionId] ?? '';
                $maxScore = intval($_POST['max_score'] ?? 100);
                $stmt = $pdo->prepare('UPDATE submissions SET score = :sc, feedback = :fb, status = "graded", graded_at = NOW() WHERE id = :id');
                $stmt->execute(['sc' => intval($score), 'fb' => $feedback, 'id' => intval($submissionId)]);
                $count++;
            }
        }
        $message = "$count submissions graded successfully!";
    }
    
    // Get pending submissions for selected assignment
    $submissions = [];
    if ($selectedAssignment) {
        $stmt = $pdo->prepare('SELECT sub.id AS submission_id, sub.file_path, sub.status, s.student_id, s.full_name AS student_name FROM submissions sub JOIN students s ON sub.student_id = s.id WHERE sub.assignment_id = :aid AND sub.status != "graded" ORDER BY s.full_name');
        $stmt->execute(['aid' => $selectedAssignment]);
        $submissions = $stmt->fetchAll();
    }
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Grade - Teacher Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.25rem; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .msg { padding: 0.6rem 0.8rem; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.82rem; }
        .msg-ok { background: #f0fdf4; color: #16a34a; }
        .msg-err { background: #fef2f2; color: #dc2626; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        select, input { padding: 0.5rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.82rem; font-family: inherit; background: #f9fafb; }
        .btn { padding: 0.5rem 0.9rem; background: #1a1a1a; color: white; border: none; border-radius: 12px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn:hover { background: #333; }
        .btn-orange { background: #f15a24; }
        .btn-orange:hover { background: #d44a1a; }
        .student-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; }
        .student-row:last-child { border-bottom: none; }
        .student-name { flex: 1; min-width: 150px; font-weight: 500; }
        .score-input { width: 60px; text-align: center; }
        .feedback-input { flex: 1; min-width: 120px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark select, body.dark input { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .student-row { border-color: #334155; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="teacher.php"><i class="fas fa-arrow-left"></i> Teacher Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Bulk Grading</h1>
    <p class="subtitle">Grade multiple submissions at once for an assignment</p>
    
    <?php if ($message): ?><div class="msg msg-ok"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="card">
        <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <select name="course" onchange="this.form.submit()">
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selectedCourse == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="assignment" onchange="this.form.submit()">
                <option value="">Select Assignment</option>
                <?php foreach ($assignments as $a): ?>
                    <option value="<?php echo $a['id']; ?>" <?php echo $selectedAssignment == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['title']); ?> (<?php echo $a['max_score']; ?> pts)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <?php if ($selectedAssignment && count($submissions) > 0): ?>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="max_score" value="<?php echo $assignments[array_search($selectedAssignment, array_column($assignments, 'id'))]['max_score'] ?? 100; ?>">
                <div style="margin-bottom:0.5rem;font-weight:600;font-size:0.85rem;">
                    <?php echo count($submissions); ?> pending submissions
                </div>
                <?php foreach ($submissions as $sub): ?>
                    <div class="student-row">
                        <span class="student-name"><?php echo htmlspecialchars($sub['student_name']); ?> <small style="color:#6b7280;">(<?php echo $sub['student_id']; ?>)</small></span>
                        <a href="download_submission.php?id=<?php echo $sub['submission_id']; ?>" class="btn" style="font-size:0.7rem;padding:0.25rem 0.5rem;" title="Download"><i class="fas fa-download"></i></a>
                        <input type="number" name="score[<?php echo $sub['submission_id']; ?>]" class="score-input" placeholder="Score" min="0">
                        <input type="text" name="feedback[<?php echo $sub['submission_id']; ?>]" class="feedback-input" placeholder="Feedback">
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="bulk_grade" class="btn btn-orange" style="width:100%;margin-top:1rem;justify-content:center;"><i class="fas fa-check-circle"></i> Grade All</button>
            </form>
        </div>
    <?php elseif ($selectedAssignment): ?>
        <div class="card"><p style="color:#6b7280;text-align:center;">No pending submissions for this assignment.</p></div>
    <?php endif; ?>
    
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
