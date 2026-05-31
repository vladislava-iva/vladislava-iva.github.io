<?php
// db_config.php — обновлено под стиль _work

$host = 'localhost';
$dbname = 'u82419';
$user = 'u82419';
$pass = '7111555';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}