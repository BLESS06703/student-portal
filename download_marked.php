<?php
session_start();
if (!isset($_SESSION['user_id'])) { exit('Access denied.'); }

$file = $_GET['file'] ?? '';
$filepath = '/data/data/com.termux/files/home/web_uploads/marked/' . basename($file);

if (file_exists($filepath)) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}
echo 'File not found.';
