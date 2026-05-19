<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied.');
}

$note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$note_id) exit('Invalid note.');

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
// Check if downloading a submission (teacher access)    $isSubmission = isset($_GET['type']) && $_GET['type'] === 'submission';
    $stmt = $pdo->prepare('SELECT course_id FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = :nid AND course_id = :cid');
    $stmt->execute(['nid' => $note_id, 'cid' => $student['course_id']]);
    $note = $stmt->fetch();
    
    if (!$note) exit('Note not found.');
    
    $stmt = $pdo->prepare('UPDATE notes SET downloads = downloads + 1 WHERE id = :id');
    $stmt->execute(['id' => $note_id]);
    
    $filepath = '/data/data/com.termux/files/home/web_uploads/' . $note['file_path'];
    if (!file_exists($filepath)) exit('File missing.');
    
    $filesize = filesize($filepath);
    $filename = basename($note['file_path']);
    
    // Get MIME type without finfo (avoids PHP 8.5 deprecation)
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    
    // Clean all output before sending file
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filepath);
    exit;
} catch (PDOException $e) {
    exit('Error.');
}
