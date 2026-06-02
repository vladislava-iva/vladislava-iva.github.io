<?php

function front_get($request) {
    return theme('form_page', []);
}

function front_post($request) {
    // CORS заголовки
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Обработка preflight (OPTIONS) запроса
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
    
    // Получаем данные из POST или JSON
    $input = $_POST;
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true);
    }
    
    // Валидация
    $errors = [];
    if (empty($input['field-name-1'])) {
        $errors['name'] = 'Имя обязательно';
    }
    if (empty($input['field-email'])) {
        $errors['email'] = 'E-mail обязателен';
    }
    if (empty($input['agree'])) {
        $errors['agree'] = 'Необходимо согласие на обработку данных';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Санитизация
    $name = trim(strip_tags($input['field-name-1']));
    $phone = trim(strip_tags($input['phone'] ?? ''));
    $email = trim(strip_tags($input['field-email']));
    $comment = trim(strip_tags($input['field-name-2'] ?? ''));
    
    // Генерация логина и пароля
    $login = 'user_' . rand(10000, 99999);
    $password = bin2hex(random_bytes(4));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Сохраняем в БД
    try {
        global $db;
        $stmt = $db->prepare("INSERT INTO form_submissions (name, phone, email, comment, login, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $comment, $login, $password_hash]);
        $id = $db->lastInsertId();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => ['db' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Ответ
    $response = [
        'success' => true,
        'message' => 'Данные сохранены',
        'login' => $login,
        'password' => $password,
        'profile_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/project1/profile/' . $id
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}