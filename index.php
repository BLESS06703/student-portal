<?php
echo "<h1>Student Portal</h1>";
echo "<p>Server is running!</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
if ($host) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=railway", getenv('DB_USER'), getenv('DB_PASS'));
        echo "<p style='color:green'>Database connected!</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:orange'>No DB_HOST set. Check Railway Variables.</p>";
}
