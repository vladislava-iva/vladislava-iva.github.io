<?php
/**
 * admin.php - Административная панель для управления пользователями
 */

require_once __DIR__ . '/db_config.php';

// HTTP Basic Authentication
$auth_success = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    // Проверка администратора (можно создать отдельную таблицу admins или использовать фиксированные данные)
    $admin_login = 'admin';
    $admin_pass_hash = md5('admin123'); // admin123
    
    if ($_SERVER['PHP_AUTH_USER'] === $admin_login && md5($_SERVER['PHP_AUTH_PW']) === $admin_pass_hash) {
        $auth_success = true;
    }
}

if (!$auth_success) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>401 Требуется авторизация</title>
        <style>
            body { font-family: Arial, sans-serif; background: #eee; padding: 20px; }
            .container { max-width: 500px; margin: 100px auto; text-align: center; background: white; padding: 30px; border-radius: 8px; }
            h1 { color: #721c24; }
            .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>401 Требуется авторизация</h1>
            <p>Для доступа к административной панели необходимо ввести логин и пароль.</p>
            <p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> admin123</p>
            <a href="index.html" class="btn">← Вернуться на главную</a>
        </div>
    </body>
    </html>';
    exit();
}

// Обработка действий
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

// Удаление пользователя
if ($action === 'delete' && $user_id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: admin.php?msg=deleted');
        exit();
    } catch (PDOException $e) {
        $error = "Ошибка удаления: " . $e->getMessage();
    }
}

// Редактирование пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
    $errors = [];
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($name) || strlen($name) > 128) {
        $errors['name'] = true;
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
        $errors['email'] = true;
    }
    
    if (empty($comment)) {
        $errors['comment'] = true;
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, comment = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $comment, $user_id]);
            header('Location: admin.php?msg=updated');
            exit();
        } catch (PDOException $e) {
            $error = "Ошибка обновления: " . $e->getMessage();
        }
    }
}

// Получение сообщений
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted':
            $message = '<div class="success-msg">Пользователь успешно удален</div>';
            break;
        case 'updated':
            $message = '<div class="success-msg">Данные пользователя успешно обновлены</div>';
            break;
    }
}

// Получение списка пользователей
$users = [];
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Ошибка загрузки данных: " . $e->getMessage();
}

// Получение данных для редактирования
$editUser = null;
if ($action === 'edit' && $user_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $editUser = $stmt->fetch();
        if (!$editUser) {
            $error = "Пользователь не найден";
        }
    } catch (PDOException $e) {
        $error = "Ошибка загрузки данных для редактирования";
    }
}

$totalUsers = count($users);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Административная панель</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #eee; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: #333;
            color: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { font-size: 1.3rem; }
        .admin-info { font-size: 0.9rem; }
        .logout-btn { background: #dc3545; color: white; padding: 5px 15px; text-decoration: none; border-radius: 4px; margin-left: 15px; }
        .logout-btn:hover { background: #c82333; }
        
        .stats-section, .users-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-title {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #333;
            border-left: 3px solid #333;
            padding-left: 10px;
        }
        .stats-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: #333;
            color: white;
            padding: 15px 25px;
            text-align: center;
            border-radius: 8px;
            min-width: 130px;
        }
        .stat-number { font-size: 1.8rem; font-weight: bold; }
        .stat-label { font-size: 0.8rem; margin-top: 4px; }
        
        .success-msg { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #c3e6cb; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #f5c6cb; }
        
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 0.9rem;
        }
        .users-table th { background: #f5f5f5; font-weight: bold; }
        .users-table tr:hover { background: #fafafa; }
        
        .action-buttons { display: flex; gap: 6px; }
        .btn-edit, .btn-delete, .btn-save, .btn-cancel {
            padding: 4px 10px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            border-radius: 4px;
        }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-save { background: #28a745; color: white; padding: 8px 18px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 18px; text-decoration: none; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        .btn-save:hover { background: #218838; }
        .btn-cancel:hover { background: #5a6268; }
        
        .badge {
            background: #17a2b8;
            color: white;
            padding: 2px 7px;
            font-size: 0.75rem;
            display: inline-block;
            margin: 1px;
            border-radius: 10px;
        }
        
        .modal {
            display: <?php echo ($editUser) ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            border-radius: 8px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ccc;
        }
        .modal-header h2 { font-size: 1.1rem; color: #333; }
        .close-modal { background: none; border: none; font-size: 1.4rem; cursor: pointer; }
        
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9rem; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #aaa;
            font-size: 0.9rem;
            border-radius: 4px;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #ccc;
        }
        
        @media (max-width: 768px) {
            .users-table th, .users-table td { padding: 7px; font-size: 0.8rem; }
            .action-buttons { flex-direction: column; }
            .header { flex-direction: column; gap: 8px; text-align: center; }
            .stats-grid { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Административная панель</h1>
        <div class="admin-info">
            Вы вошли как <strong>admin</strong>
            <a href="index.html" class="logout-btn">На сайт</a>
        </div>
    </div>

    <?php if ($message): echo $message; endif; ?>
    <?php if (isset($error)): echo '<div class="error-msg">⚠️ ' . htmlspecialchars($error) . '</div>'; endif; ?>

    <div class="stats-section">
        <h2 class="stats-title">📈 Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
        </div>
    </div>

    <div class="users-section">
        <h2 class="stats-title">👥 Все пользователи</h2>
        <?php if (empty($users)): ?>
            <p style="text-align: center; padding: 40px; color: #566777;">Нет зарегистрированных пользователей</p>
        <?php else: ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Телефон</th>
                        <th>Комментарий</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['login']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($user['comment'], 0, 50)) . (mb_strlen($user['comment']) > 50 ? '…' : ''); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="admin.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit">✏️ Редакт.</a>
                                <a href="admin.php?action=delete&id=<?php echo $user['id']; ?>"
                                   class="btn-delete"
                                   onclick="return confirm('Удалить пользователя <?php echo htmlspecialchars($user['name']); ?>?')">🗑️ Удалить</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($editUser): ?>
<div class="modal" style="display: flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Редактирование пользователя #<?php echo $editUser['id']; ?> (<?php echo htmlspecialchars($editUser['login']); ?>)</h2>
            <button class="close-modal" onclick="window.location.href='admin.php'">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Имя *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($editUser['name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Телефон</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone']); ?>">
            </div>
            <div class="form-group">
                <label>Комментарий *</label>
                <textarea name="comment" required><?php echo htmlspecialchars($editUser['comment']); ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">💾 Сохранить изменения</button>
                <a href="admin.php" class="btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>