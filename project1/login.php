<?php

function login_post($request) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    $password = trim($input['password'] ?? '');
    
    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Логин и пароль обязательны']);
        exit;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT id FROM form_submissions WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }
    
    // Проверяем пароль через Basic Auth (временно)
    // Для простоты — проверяем через отдельный запрос
    $auth_url = 'http://' . $_SERVER['HTTP_HOST'] . '/project1/profile/' . $user['id'];
    
    // Создаём поток с авторизацией
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic " . base64_encode("$login:$password")
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents($auth_url, false, $context);
    
    if ($result !== false) {
        echo json_encode(['success' => true, 'profile_url' => $auth_url]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Неверный пароль']);
    }
    exit;
}