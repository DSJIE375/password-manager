<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'you_DB_NAME');
define('DB_USER', 'you_DB_USER');
define('DB_PASS', 'you_DB_PASS');
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here-change-this');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>