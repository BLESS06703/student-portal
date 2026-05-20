<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$assignmentId = intval($_GET['id'] ?? 0);
if (!$assignmentId) exit('No assignment ID.');

try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
    
    // Get student's course
    $stmt = $pdo->prepare('SELECT course_id FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $studentCourse = $stmt->fetchColumn();
    
    // Get assignment - check same course
    $stmt = $pdo->prepare('SELECT file_path, course_id FROM assignments WHERE id = :aid');
    $stmt->execute(['aid' => $assignmentId]);
    $assignment = $stmt->fetch();
    
    if ($assignment && $assignment['course_id'] == $studentCourse && $assignment['file_path']) {
        $filepath = '/data/data/com.termux/files/home/web_uploads/assignments/' . $assignment['file_path'];
        
        if (file_exists($filepath)) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($assignment['file_path']) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
    
    echo 'File not found or access denied.';
    echo '<br>Assignment ID: ' . $assignmentId;
    echo '<br>Student Course: ' . ($studentCourse ?? 'none');
    echo '<br>Assignment Course: ' . ($assignment['course_id'] ?? 'none');
    echo '<br>File: ' . ($assignment['file_path'] ?? 'none');
    
} catch (PDOException $e) { exit('Error: ' . $e->getMessage()); }
