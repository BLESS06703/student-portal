<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
} catch (PDOException $e) { $student = ['full_name' => 'Student', 'course_name' => 'N/A']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussions - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; }
        .card { background: #fff; border-radius: 16px; padding: 3rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.04); max-width: 500px; }
        .icon { font-size: 3rem; margin-bottom: 1rem; color: #d1d5db; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #6b7280; margin-bottom: 1.5rem; }
        .btn { padding: 0.7rem 1.5rem; background: #1a1a1a; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fas fa-comments"></i></div>
        <h1>Discussions</h1>
        <p>Course discussions coming soon. This feature will allow students to discuss topics and share ideas.</p>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
