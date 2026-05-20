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
    
    // Save template
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $maxScore = intval($_POST['max_score'] ?? 100);
        $filePath = '';
        
        if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/templates/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'template_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['template_file']['name']);
            move_uploaded_file($_FILES['template_file']['tmp_name'], $uploadDir . $filename);
            $filePath = $filename;
        }
        
        if ($title) {
            $stmt = $pdo->prepare('INSERT INTO assignment_templates (teacher_id, title, description, max_score, file_path) VALUES (:tid, :t, :d, :ms, :fp)');
            $stmt->execute(['tid' => $_SESSION['user_id'], 't' => $title, 'd' => $desc, 'ms' => $maxScore, 'fp' => $filePath]);
            $message = 'Template saved!';
        }
    }
    
    // Delete template
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare('DELETE FROM assignment_templates WHERE id = :id AND teacher_id = :tid');
        $stmt->execute(['id' => intval($_GET['delete']), 'tid' => $_SESSION['user_id']]);
        $message = 'Template deleted.';
    }
    
    // Use template — create assignment from it
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_template'])) {
        $templateId = intval($_POST['template_id']);
        $courseId = intval($_POST['course_id']);
        $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        
        $stmt = $pdo->prepare('SELECT * FROM assignment_templates WHERE id = :id');
        $stmt->execute(['id' => $templateId]);
        $template = $stmt->fetch();
        
        if ($template) {
            $stmt = $pdo->prepare('INSERT INTO assignments (title, description, file_path, course_id, due_date, max_score, created_by) VALUES (:t, :d, :fp, :cid, :dd, :ms, :uid)');
            $stmt->execute(['t' => $template['title'], 'd' => $template['description'], 'fp' => $template['file_path'], 'cid' => $courseId, 'dd' => $dueDate, 'ms' => $template['max_score'], 'uid' => $_SESSION['user_id']]);
            $message = 'Assignment created from template!';
        }
    }
    
    // Get templates
    $stmt = $pdo->prepare('SELECT * FROM assignment_templates WHERE teacher_id = :tid ORDER BY created_at DESC');
    $stmt->execute(['tid' => $_SESSION['user_id']]);
    $templates = $stmt->fetchAll();
    
    // Get courses for dropdown
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Templates - Teacher Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 800px; margin: 0 auto; }
        h1 { font-size: 1.4rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .msg { padding: 0.6rem 0.8rem; border-radius: 10px; margin-bottom: 0.75rem; font-size: 0.82rem; }
        .msg-ok { background: #f0fdf4; color: #16a34a; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; }
        input, select, textarea { padding: 0.55rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.82rem; font-family: inherit; background: #f9fafb; width: 100%; margin-bottom: 0.4rem; }
        .row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 0.9rem; background: #1a1a1a; color: white; border: none; border-radius: 12px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .btn-orange { background: #f15a24; }
        .btn-orange:hover { background: #d44a1a; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.7rem; border-radius: 8px; }
        .btn-danger { background: #ef4444; }
        .template-item { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; gap: 0.5rem; }
        .template-item:last-child { border-bottom: none; }
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark input, body.dark select, body.dark textarea { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .template-item { border-color: #334155; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="teacher.php"><i class="fas fa-arrow-left"></i> Teacher Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Assignment Templates</h1>
    <p class="subtitle">Save and reuse assignment structures</p>
    
    <?php if ($message): ?><div class="msg msg-ok"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="card">
        <h2>Save New Template</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Template title" required>
            <textarea name="description" placeholder="Description" rows="2"></textarea>
            <div class="row">
                <input type="number" name="max_score" value="100" style="width:100px;">
                <input type="file" name="template_file" style="flex:1;">
            </div>
            <button type="submit" name="save_template" class="btn btn-orange"><i class="fas fa-save"></i> Save Template</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Your Templates (<?php echo count($templates); ?>)</h2>
        <?php if (count($templates) > 0): ?>
            <?php foreach ($templates as $t): ?>
                <div class="template-item">
                    <div>
                        <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                        <div style="font-size:0.75rem;color:#6b7280;">Max: <?php echo $t['max_score']; ?> pts <?php if ($t['file_path']): ?>&middot; File attached<?php endif; ?></div>
                    </div>
                    <div style="display:flex;gap:0.35rem;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="template_id" value="<?php echo $t['id']; ?>">
                            <select name="course_id" required style="width:auto;font-size:0.7rem;padding:0.3rem;margin:0;">
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="due_date" required style="width:auto;font-size:0.7rem;padding:0.3rem;margin:0;">
                            <button type="submit" name="use_template" class="btn btn-sm">Use</button>
                        </form>
                        <a href="?delete=<?php echo $t['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6b7280;text-align:center;">No templates saved yet.</p>
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
