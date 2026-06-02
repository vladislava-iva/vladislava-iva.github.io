<?php

// Показать всех пользователей (GET)
function admin_get($request) {
    global $db;
    $stmt = $db->prepare("SELECT id, name, phone, email, login, created_at FROM form_submissions ORDER BY id DESC");
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return theme('admin', ['submissions' => $submissions]);
}

// Удалить запись (POST с параметром delete)
function admin_post($request, $id) {
    if (is_array($id)) {
        $id = intval($id[0]);
    } else {
        $id = intval($id);
    }
    
    if ($id > 0) {
        global $db;
        $stmt = $db->prepare("DELETE FROM form_submissions WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    return redirect('/project1/admin?deleted=1');
}