<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Check admin
try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    $stmt = $pdo->prepare('SELECT role FROM students WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') { exit('Access denied.'); }
} catch (PDOException $e) { exit('Error.'); }

$file = basename($_GET['file'] ?? '');
$filepath = '/data/data/com.termux/files/home/backups/' . $file;

if (file_exists($filepath) && strpos($file, 'backup_') === 0) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}
echo 'File not found.';
