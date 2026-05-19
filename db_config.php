<?php
// Railway MySQL Connection
function getDBConnection() {
    $host = 'yamabiko.proxy.rlwy.net';
    $port = '27745';
    $dbname = 'railway';
    $user = 'root';
    $pass = 'lpBBXfReELFhpzVsXbKvsUVjAmTJhDCs';
    
    return new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
