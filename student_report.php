<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = '';
$error = '';
$reportData = null;

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $teacher = $stmt->fetch();
    
    if (!in_array($teacher['role'], ['teacher', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    $selectedCourse = $_GET['course'] ?? ($courses[0]['id'] ?? '');
    $selectedStudent = $_GET['student'] ?? '';
    $studentSearch = trim($_GET['student_search'] ?? '');
    
    // Get students with search
    if ($studentSearch) {
        $stmt = $pdo->prepare('SELECT id, student_id, full_name FROM students WHERE course_id = :cid AND status = "approved" AND (full_name LIKE :ss OR student_id LIKE :ss2) ORDER BY full_name LIMIT 50');
        $stmt->execute(['cid' => $selectedCourse, 'ss' => "%$studentSearch%", 'ss2' => "%$studentSearch%"]);
    } else {
        $stmt = $pdo->prepare('SELECT id, student_id, full_name FROM students WHERE course_id = :cid AND status = "approved" ORDER BY full_name LIMIT 50');
        $stmt->execute(['cid' => $selectedCourse]);
    }
    $students = $stmt->fetchAll();
    
    // Generate report
    if ($selectedStudent) {
        $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
        $stmt->execute(['id' => $selectedStudent]);
        $reportData = $stmt->fetch();
        
        // Single optimized query for metrics
        $stmt = $pdo->prepare('SELECT 
            AVG(sub.score) as avg_score, 
            COUNT(sub.id) as total_subs,
            SUM(CASE WHEN sub.status = "graded" THEN 1 ELSE 0 END) as graded_count
            FROM submissions sub 
            JOIN assignments a ON sub.assignment_id = a.id 
            WHERE sub.student_id = :sid AND a.course_id = :cid');
        $stmt->execute(['sid' => $selectedStudent, 'cid' => $selectedCourse]);
        $metrics = $stmt->fetch();
        
        // Submissions
        $stmt = $pdo->prepare('SELECT sub.score, sub.status, sub.submitted_at, sub.graded_at, sub.feedback, a.title, a.max_score FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id WHERE sub.student_id = :sid ORDER BY sub.submitted_at DESC');
        $stmt->execute(['sid' => $selectedStudent]);
        $reportData['submissions'] = $stmt->fetchAll();
        
        // Exam results
        $stmt = $pdo->prepare('SELECT * FROM exam_results WHERE student_id = :sid AND course_id = :cid ORDER BY exam_date DESC');
        $stmt->execute(['sid' => $selectedStudent, 'cid' => $selectedCourse]);
        $reportData['exams'] = $stmt->fetchAll();
        
        // Attendance
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present FROM attendance WHERE student_id = :sid AND course_id = :cid");
        $stmt->execute(['sid' => $selectedStudent, 'cid' => $selectedCourse]);
        $reportData['attendance'] = $stmt->fetch();
        
        $reportData['metrics'] = $metrics;
    }
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report - Teacher Panel</title>
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
        select, input { padding: 0.5rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.82rem; background: #fff; font-family: inherit; }
        
        .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
        .stat-box { background: #f9fafb; padding: 1rem; border-radius: 12px; text-align: center; border-left: 4px solid #d1d5db; }
        .stat-box .value { font-size: 1.5rem; font-weight: 800; }
        .stat-box .label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-box.good { border-left-color: #16a34a; }
        .stat-box.warn { border-left-color: #f59e0b; }
        .stat-box.bad { border-left-color: #ef4444; }
        .stat-box.neutral { border-left-color: #d1d5db; }
        .stat-box .value.na { color: #9ca3af; font-size: 1rem; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        th { text-align: left; padding: 0.5rem; font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 12px; font-size: 0.65rem; font-weight: 700; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-yellow { background: #fef3c7; color: #b45309; }
        .badge-red { background: #fef2f2; color: #dc2626; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .info-row { display: flex; padding: 0.3rem 0; font-size: 0.85rem; }
        .info-label { width: 100px; color: #6b7280; font-weight: 500; flex-shrink: 0; }
        
        .empty-state { text-align: center; padding: 2rem 1rem; color: #9ca3af; }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.2; }
        .empty-state p { font-size: 0.85rem; }
        
        .search-select { position: relative; }
        .search-select input { width: 100%; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; max-height: 200px; overflow-y: auto; z-index: 50; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .search-results.show { display: block; }
        .search-results div { padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.82rem; }
        .search-results div:hover { background: #f3f4f6; }
        
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .card { background: #1e293b; }
        body.dark .stat-box { background: #0f172a; }
        body.dark table, body.dark th, body.dark td { border-color: #334155; }
        body.dark select, body.dark input { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        body.dark .search-results { background: #1e293b; border-color: #334155; }
        body.dark .search-results div:hover { background: #334155; }
        @media (max-width: 600px) { .stats-row { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="teacher.php"><i class="fas fa-arrow-left"></i> Teacher Panel</a>
        <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
    </div>
    
    <h1>Student Performance Report</h1>
    <p class="subtitle">View individual student progress and performance</p>
    
    <div class="card">
        <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <select name="course" onchange="this.form.submit()" style="min-width:150px;">
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selectedCourse == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="search-select" style="flex:1;min-width:200px;">
                <input type="text" name="student_search" id="studentSearch" placeholder="Search student by name or ID..." value="<?php echo htmlspecialchars($studentSearch); ?>" autocomplete="off" oninput="filterStudents()" onfocus="showResults()">
                <input type="hidden" name="student" id="selectedStudent" value="<?php echo $selectedStudent; ?>">
                <div class="search-results" id="searchResults">
                    <?php foreach ($students as $s): ?>
                        <div onclick="selectStudent('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['full_name'] . ' (' . $s['student_id'] . ')'); ?>')"><?php echo htmlspecialchars($s['full_name'] . ' (' . $s['student_id'] . ')'); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn" style="padding:0.5rem 1rem;background:#f15a24;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:600;">View Report</button>
        </form>
    </div>
    
    <?php if ($reportData): 
        $m = $reportData['metrics'];
        $hasSubmissions = ($m['total_subs'] ?? 0) > 0;
        $avg = $hasSubmissions ? ($m['avg_score'] ?? 0) : null;
        $avgCls = !$hasSubmissions ? 'neutral' : ($avg >= 70 ? 'good' : ($avg >= 50 ? 'warn' : 'bad'));
        $att = $reportData['attendance'];
        $hasAttendance = ($att['total'] ?? 0) > 0;
        $attPct = $hasAttendance ? round(($att['present'] / $att['total']) * 100) : null;
        $attCls = !$hasAttendance ? 'neutral' : ($attPct >= 80 ? 'good' : ($attPct >= 60 ? 'warn' : 'bad'));
    ?>
    <div class="card">
        <h2><?php echo htmlspecialchars($reportData['full_name']); ?></h2>
        <div class="info-row"><span class="info-label">Student ID</span><span><?php echo htmlspecialchars($reportData['student_id']); ?></span></div>
        <div class="info-row"><span class="info-label">Course</span><span><?php echo htmlspecialchars($reportData['course_name'] ?? 'N/A'); ?></span></div>
        <div class="info-row"><span class="info-label">Year</span><span>Year <?php echo $reportData['year_level']; ?>, Semester <?php echo $reportData['semester'] ?? 1; ?></span></div>
    </div>
    
    <div class="stats-row">
        <div class="stat-box <?php echo $avgCls; ?>">
            <div class="value <?php echo !$hasSubmissions ? 'na' : ''; ?>"><?php echo $hasSubmissions ? number_format($avg, 1) . '%' : '--'; ?></div>
            <div class="label">Avg Score</div>
        </div>
        <div class="stat-box <?php echo $hasSubmissions ? ($m['graded_count'] > 0 ? 'good' : 'neutral') : 'neutral'; ?>">
            <div class="value"><?php echo $m['total_subs'] ?? 0; ?></div>
            <div class="label">Submissions</div>
        </div>
        <div class="stat-box <?php echo $attCls; ?>">
            <div class="value <?php echo !$hasAttendance ? 'na' : ''; ?>"><?php echo $hasAttendance ? $attPct . '%' : '--'; ?></div>
            <div class="label">Attendance</div>
        </div>
        <div class="stat-box neutral">
            <div class="value"><?php echo count($reportData['exams']); ?></div>
            <div class="label">Exams</div>
        </div>
    </div>
    
    <div class="card">
        <h2>Assignment Submissions</h2>
        <?php if (count($reportData['submissions']) > 0): ?>
            <table>
                <thead><tr><th>Assignment</th><th>Score</th><th>Status</th><th>Submitted</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['submissions'] as $sub): 
                        $badge = $sub['status'] === 'graded' ? ($sub['score'] >= 50 ? 'badge-green' : 'badge-red') : ($sub['status'] === 'submitted' ? 'badge-blue' : 'badge-yellow');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['title']); ?></td>
                            <td><strong><?php echo $sub['status'] === 'graded' ? $sub['score'] . '/' . $sub['max_score'] : '--'; ?></strong></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                            <td><?php echo $sub['submitted_at'] ? date('M d, Y', strtotime($sub['submitted_at'])) : '--'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No submissions yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>Exam Results</h2>
        <?php if (count($reportData['exams']) > 0): ?>
            <table>
                <thead><tr><th>Exam</th><th>Score</th><th>Grade</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['exams'] as $exam): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                            <td><strong><?php echo $exam['score']; ?>/<?php echo $exam['total_marks']; ?></strong></td>
                            <td><span class="badge <?php echo in_array($exam['grade'], ['A','B']) ? 'badge-green' : 'badge-yellow'; ?>"><?php echo $exam['grade']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <p>No exam results yet.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <script>
    function toggleTheme() {
        document.body.classList.toggle("dark");
        var icon = document.querySelector("#themeBtn i");
        if (document.body.classList.contains("dark")) { if(icon) icon.className = "fas fa-sun"; localStorage.setItem("theme", "dark"); }
        else { if(icon) icon.className = "fas fa-moon"; localStorage.setItem("theme", "light"); }
    }
    if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); var i = document.querySelector("#themeBtn i"); if(i) i.className = "fas fa-sun"; }
    
    function showResults() { document.getElementById('searchResults').classList.add('show'); }
    function selectStudent(id, name) {
        document.getElementById('selectedStudent').value = id;
        document.getElementById('studentSearch').value = name;
        document.getElementById('searchResults').classList.remove('show');
        document.querySelector('form').submit();
    }
    function filterStudents() {
        var input = document.getElementById('studentSearch');
        var filter = input.value.toLowerCase();
        var divs = document.querySelectorAll('#searchResults div');
        divs.forEach(function(d) { d.style.display = d.textContent.toLowerCase().includes(filter) ? '' : 'none'; });
        showResults();
    }
    document.addEventListener('click', function(e) { if (!e.target.closest('.search-select')) document.getElementById('searchResults').classList.remove('show'); });
    </script>
</body>
</html>
