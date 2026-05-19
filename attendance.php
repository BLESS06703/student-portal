<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!in_array($user['role'], ['class_rep', 'teacher', 'admin'])) { header('Location: dashboard.php'); exit; }
    
    $courseId = $user['course_id'];
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Mark attendance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
        foreach ($_POST['status'] as $studentId => $status) {
            $stmt = $pdo->prepare('INSERT INTO attendance (student_id, course_id, date, status, marked_by) VALUES (:sid, :cid, :d, :s, :mid) ON DUPLICATE KEY UPDATE status = :s2');
            $stmt->execute(['sid' => $studentId, 'cid' => $courseId, 'd' => $date, 's' => $status, 'mid' => $_SESSION['user_id'], 's2' => $status]);
        }
        $message = 'Attendance marked for ' . $date;
    }
    
    // Get students
    $stmt = $pdo->prepare('SELECT * FROM students WHERE course_id = :cid AND status = "approved" ORDER BY full_name');
    $stmt->execute(['cid' => $courseId]);
    $students = $stmt->fetchAll();
    
    // Get existing attendance
    $stmt = $pdo->prepare('SELECT student_id, status FROM attendance WHERE course_id = :cid AND date = :d');
    $stmt->execute(['cid' => $courseId, 'd' => $date]);
    $existing = [];
    while ($row = $stmt->fetch()) { $existing[$row['student_id']] = $row['status']; }
    
} catch (PDOException $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; padding: 1.5rem; max-width: 700px; margin: 0 auto; }
        h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; }
        .card { background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1rem; }
        .student-row { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; gap: 0.5rem; }
        select { padding: 0.4rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.8rem; }
        .btn { padding: 0.6rem 1.2rem; background: #1a1a1a; color: white; border: none; border-radius: 14px; font-weight: 600; cursor: pointer; }
        .msg { padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; background: #f0fdf4; color: #16a34a; }
    </style>
</head>
<body>
    <h1>Attendance - <?php echo date('F d, Y', strtotime($date)); ?></h1>
    <?php if (isset($message)): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="card">
        <form method="GET" style="margin-bottom:1rem;">
            <input type="date" name="date" value="<?php echo $date; ?>" onchange="this.form.submit()" style="padding:0.5rem;border:1.5px solid #e5e7eb;border-radius:10px;">
        </form>
        
        <form method="POST">
            <?php foreach ($students as $s): ?>
                <div class="student-row">
                    <span><?php echo htmlspecialchars($s['full_name']); ?></span>
                    <select name="status[<?php echo $s['id']; ?>]">
                        <option value="present" <?php echo ($existing[$s['id']] ?? '') === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo ($existing[$s['id']] ?? '') === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="late" <?php echo ($existing[$s['id']] ?? '') === 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="excused" <?php echo ($existing[$s['id']] ?? '') === 'excused' ? 'selected' : ''; ?>>Excused</option>
                    </select>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="mark_attendance" class="btn" style="width:100%;margin-top:1rem;">Save Attendance</button>
        </form>
    </div>
    
    <a href="dashboard.php" style="color:#6b7280;">Back to Dashboard</a>
</body>
</html>
