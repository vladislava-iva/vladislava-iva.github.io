<?php

// Функция проверки авторизации (для обычных пользователей)
function check_auth() {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        return false;
    }
    
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    
    global $db;
    $stmt = $db->prepare("SELECT * FROM form_submissions WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    
    return false;
}

// Функция для отправки заголовка авторизации
function auth_required() {
    header('WWW-Authenticate: Basic realm="Введите логин и пароль"');
    header('HTTP/1.0 401 Unauthorized');
    return theme('401', []);
}

// Показать профиль (GET)
function profile_get($request, $id) {
    if (is_array($id)) {
        $id = intval($id[0]);
    } else {
        $id = intval($id);
    }
    
    if ($id == 0) {
        return not_found();
    }
    
    $user = check_auth();
    if (!$user) {
        return auth_required();
    }
    
    global $db;
    $stmt = $db->prepare("SELECT * FROM form_submissions WHERE id = ?");
    $stmt->execute([$id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        return not_found();
    }
    
    return theme('profile_form', ['profile' => $profile]);
}

// Обновить профиль (POST)
function profile_post($request, $id) {
    if (empty($id) || (is_array($id) && empty($id[0]))) {
        $id = intval($_POST['id'] ?? 0);
    } elseif (is_array($id)) {
        $id = intval($id[0]);
    } else {
        $id = intval($id);
    }
    
    if ($id == 0) {
        return not_found();
    }
    
    $is_admin = (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == 'admin');
    
    if (!$is_admin) {
        $user = check_auth();
        if (!$user) {
            return auth_required();
        }
    }
    
    global $db;
    
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $comment = trim(strip_tags($_POST['comment'] ?? ''));
    
    $stmt = $db->prepare("UPDATE form_submissions SET name = ?, phone = ?, email = ?, comment = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $email, $comment, $id]);
    
    if ($is_admin) {
        return redirect('/project1/admin?saved=1');
    } else {
        return redirect('/project1/profile/' . $id . '?saved=1');
    }
}