<?php
function getDB() {
    $host = getenv('DB_HOST') ?: 'yamabiko.proxy.rlwy.net';
    $port = getenv('DB_PORT') ?: '27745';
    $db   = getenv('DB_NAME') ?: 'railway';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    return new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
