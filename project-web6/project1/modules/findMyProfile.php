<?php

// Только POST-обработка
function findMyProfile_post($request) {
    // Запрещаем кеширование авторизации
    header('Cache-Control: no-cache, must-revalidate, no-store');
    header('Pragma: no-cache');
    
    // Проверяем, отправил ли браузер логин и пароль
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        // Запрашиваем логин и пароль
        header('WWW-Authenticate: Basic realm="Введите логин и пароль, которые вы получили после отправки заявки"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<h1>Требуется авторизация</h1>';
        echo '<p>Введите логин и пароль, которые вы получили после отправки заявки.</p>';
        exit;
    }
    
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    
    // Подключаемся к БД
    global $db;
    
    // Ищем пользователя по логину
    $stmt = $db->prepare("SELECT id, password_hash FROM form_submissions WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Проверяем пароль
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Неверный логин или пароль
        header('WWW-Authenticate: Basic realm="Неверный логин или пароль. Попробуйте ещё раз."');
        header('HTTP/1.0 401 Unauthorized');
        echo '<h1>Ошибка авторизации</h1>';
        echo '<p>Неверный логин или пароль.</p>';
        exit;
    }
    
    // Всё правильно — перенаправляем на страницу редактирования профиля
    header('Location: /project1/profile/' . $user['id']);
    exit;
}

// Блокируем GET-доступ
function findMyProfile_get($request) {
    http_response_code(405);
    echo 'Метод не разрешён. Используйте POST.';
    exit;
}