<?php

$user = 'u82419';
$pass = '7111555';
$db = new PDO(
    "mysql:host=localhost;dbname=$user;charset=utf8mb4", $user, $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);