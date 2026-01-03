<?php
// Database configuration
$db_host = 'sql105.infinityfree.com';
$db_port = '3306';
$db_name = 'if0_40819230_todo_manager';
$db_user = 'if0_40819230';
$db_pass = '2yIUkCno1tUFG';
$db_charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Napaka pri povezavi s podatkovno bazo: " . $e->getMessage());
}
