<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.0 403'); exit('Access denied.'); }

$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$submission_id) exit('Invalid.');

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'root', 'lpBBXfReELFhpzVsXbKvsUVjAmTJhDCs');
    
    // Check user is teacher or admin
    $stmt = $pdo->prepare('SELECT role FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!in_array($user['role'], ['teacher', 'admin'])) exit('Access denied.');
    
    $stmt = $pdo->prepare('SELECT file_path FROM submissions WHERE id = :id');
    $stmt->execute(['id' => $submission_id]);
    $sub = $stmt->fetch();
    if (!$sub) exit('Not found.');
    
    $filepath = '/data/data/com.termux/files/home/web_uploads/submissions/' . $sub['file_path'];
    if (!file_exists($filepath)) exit('File missing.');
    
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($sub['file_path']) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} catch (PDOException $e) { exit('Error.'); }
