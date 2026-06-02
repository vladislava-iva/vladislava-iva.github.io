<?php

function getUserId_post($request) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    
    if (empty($login)) {
        echo json_encode(['success' => false, 'error' => 'Логин не указан']);
        exit;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT id FROM form_submissions WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['success' => true, 'id' => $user['id']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
    }
    exit;
}