<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $teacher = $stmt->fetch();
    
    if (!in_array($teacher['role'], ['teacher', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    $selectedCourse = $_GET['course'] ?? ($courses[0]['id'] ?? '');
    
    // Overall stats
    $stmt = $pdo->prepare('SELECT AVG(sub.score) as avg_score, COUNT(*) as total FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id WHERE a.course_id = :cid AND sub.status = "graded"');
    $stmt->execute(['cid' => $selectedCourse]);
    $overall = $stmt->fetch();
    
    // Grade distribution
    $stmt = $pdo->prepare('SELECT 
        SUM(CASE WHEN sub.score >= 80 THEN 1 ELSE 0 END) as A,
        SUM(CASE WHEN sub.score >= 65 AND sub.score < 80 THEN 1 ELSE 0 END) as B,
        SUM(CASE WHEN sub.score >= 50 AND sub.score < 65 THEN 1 ELSE 0 END) as C,
        SUM(CASE WHEN sub.score >= 40 AND sub.score < 50 THEN 1 ELSE 0 END) as D,
        SUM(CASE WHEN sub.score < 40 THEN 1 ELSE 0 END) as F
        FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id 
        WHERE a.course_id = :cid AND sub.status = "graded"');
    $stmt->execute(['cid' => $selectedCourse]);
    $distribution = $stmt->fetch();
    
    // Per assignment stats
    $stmt = $pdo->prepare('SELECT a.title, a.max_score, AVG(sub.score) as avg, COUNT(*) as count, MIN(sub.score) as min, MAX(sub.score) as max FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id WHERE a.course_id = :cid AND sub.status = "graded" GROUP BY a.id ORDER BY a.title');
    $stmt->execute(['cid' => $selectedCourse]);
    $assignments = $stmt->fetchAll();
    
    // Recent graded
    $stmt = $pdo->prepare('SELECT sub.score, sub.feedback, sub.graded_at, a.title, s.full_name FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id JOIN students s ON sub.student_id = s.id WHERE a.course_id = :cid AND sub.status = "graded" ORDER BY sub.graded_at DESC LIMIT 10');
    $stmt->execute(['cid' => $selectedCourse]);
    $recent = $stmt->fetchAll();
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Analytics - Teacher Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.4rem; font-weight: 800; }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.85rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .top-bar a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .stat-box { background: #f9fafb; padding: 1rem; border-radius: 12px; text-align: center; }
        .stat-box .value { font-size: 1.5rem; font-weight: 800; }
        .stat-box .label { font-size: 0.72rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        
        .bar-container { margin: 0.5rem 0; }
        .bar-label { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 0.2rem; }
        .bar-track { height: 24px; background: #f3f4f6; border-radius: 6px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 6px; transition: width 0.5s; display: flex; align-items: center; padding-left: 0.5rem; color: white; font-size: 0.7rem; font-weight: 600; }
        .bar-A { background: #16a34a; } .bar-B { background: #3b82f6; } .bar-C { background: #f59e0b; } .bar-D { background: #f97316; } .bar-F { background: #ef4444; }
        
        .recent-item { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.82rem; flex-wrap: wrap; gap: 0.5rem; }
        .recent-item:last-child { border-bottom: none; }
        
        select { padding: 0.5rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.82rem; background: #fff; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark .stat-box { background: #0f172a; }
        body.dark .bar-track { background: #334155; }
        body.dark .recent-item { border-color: #334155; }
        body.dark select { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .subtitle { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="teacher.php"><i class="fas fa-arrow-left"></i> Teacher Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Grade Analytics</h1>
    <p class="subtitle">Performance insights for your course</p>
    
    <form method="GET" style="margin-bottom:1rem;">
        <select name="course" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $selectedCourse == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <div class="stats-row">
        <div class="stat-box"><div class="value"><?php echo number_format($overall['avg_score'] ?? 0, 1); ?>%</div><div class="label">Average Score</div></div>
        <div class="stat-box"><div class="value"><?php echo $overall['total'] ?? 0; ?></div><div class="label">Graded Submissions</div></div>
        <div class="stat-box"><div class="value"><?php echo array_sum($distribution); ?></div><div class="label">Total Grades</div></div>
    </div>
    
    <div class="card">
        <h2>Grade Distribution</h2>
        <?php 
        $total = array_sum($distribution);
        $grades = ['A' => 'bar-A', 'B' => 'bar-B', 'C' => 'bar-C', 'D' => 'bar-D', 'F' => 'bar-F'];
        foreach ($grades as $grade => $class):
            $count = $distribution[$grade] ?? 0;
            $pct = $total > 0 ? round(($count / $total) * 100) : 0;
        ?>
            <div class="bar-container">
                <div class="bar-label"><span>Grade <?php echo $grade; ?></span><span><?php echo $count; ?> (<?php echo $pct; ?>%)</span></div>
                <div class="bar-track"><div class="bar-fill <?php echo $class; ?>" style="width:<?php echo $pct; ?>%"><?php echo $pct > 8 ? $pct.'%' : ''; ?></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="card">
        <h2>Per Assignment</h2>
        <?php if (count($assignments) > 0): ?>
            <?php foreach ($assignments as $a): ?>
                <div class="recent-item">
                    <span><strong><?php echo htmlspecialchars($a['title']); ?></strong></span>
                    <span>Avg: <?php echo number_format($a['avg'] ?? 0, 1); ?> | Min: <?php echo $a['min'] ?? 0; ?> | Max: <?php echo $a['max'] ?? 0; ?> | (<?php echo $a['count']; ?> graded)</span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6b7280;">No graded assignments yet.</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>Recently Graded</h2>
        <?php if (count($recent) > 0): ?>
            <?php foreach ($recent as $r): ?>
                <div class="recent-item">
                    <span><?php echo htmlspecialchars($r['full_name']); ?> — <?php echo htmlspecialchars($r['title']); ?></span>
                    <span><strong><?php echo $r['score']; ?></strong> &middot; <?php echo date('M d', strtotime($r['graded_at'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6b7280;">No grades yet.</p>
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
