<?php
// admin.php - Админ-панель для управления пользователями
session_start();

// ========== ПОДКЛЮЧЕНИЕ К БД ==========
$host = 'localhost';
$dbname = 'u82419';
$user = 'u82419';
$pass = '7111555';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

// ========== HTTP АВТОРИЗАЦИЯ ДЛЯ АДМИНА ==========
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    md5($_SERVER['PHP_AUTH_PW']) !== md5($admin_password)) {
    
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>Доступ запрещен</h1><p>Логин: admin, Пароль: admin123</p>';
    exit;
}

// ========== ОБРАБОТКА ДЕЙСТВИЙ ==========

// Удаление пользователя
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    header('Location: admin.php');
    exit;
}

// Редактирование пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $comment = $_POST['comment'];
    
    $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, comment = ? WHERE id = ?")
        ->execute([$name, $email, $phone, $comment, $user_id]);
    header('Location: admin.php');
    exit;
}

// ========== ПОЛУЧЕНИЕ ДАННЫХ ==========
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$total_users = count($users);

// Получаем данные для редактирования
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель - Заявки Drupal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1300px; margin: 0 auto; }
        .header { background: #333; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header h1 { font-size: 1.3rem; }
        .admin-badge { background: #6a0dad; padding: 5px 12px; border-radius: 5px; font-size: 0.9rem; }
        .stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { background: white; padding: 15px 25px; border-radius: 8px; text-align: center; flex: 1; min-width: 150px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card .num { font-size: 2rem; font-weight: bold; color: #6a0dad; }
        .stat-card .label { color: #666; font-size: 0.9rem; margin-top: 5px; }
        .edit-form { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .edit-form h3 { margin-bottom: 15px; color: #6a0dad; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .section { background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { font-size: 1.2rem; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #6a0dad; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #6a0dad; color: white; }
        tr:hover { background: #f8f9fa; }
        .btn { padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem; text-decoration: none; display: inline-block; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-save { background: #28a745; color: white; padding: 8px 20px; }
        .btn-cancel { background: #6c757d; color: white; padding: 8px 20px; text-decoration: none; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        .btn-save:hover { background: #218838; }
        .btn-cancel:hover { background: #5a6268; }
        @media (max-width: 768px) { th, td { font-size: 0.7rem; padding: 5px; } .stat-card .num { font-size: 1.2rem; } .header { flex-direction: column; gap: 10px; text-align: center; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Админ-панель - Заявки Drupal</h1>
        <div class="admin-badge">Вы вошли как admin</div>
    </div>
    
    <div class="stats">
        <div class="stat-card"><div class="num"><?= $total_users ?></div><div class="label">Всего заявок</div></div>
    </div>
    
    <?php if ($edit_user): ?>
    <div class="edit-form">
        <h3>✏️ Редактирование заявки #<?= $edit_user['id'] ?> (логин: <?= htmlspecialchars($edit_user['login']) ?>)</h3>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
            <div class="form-group"><label>Имя *</label><input type="text" name="name" value="<?= htmlspecialchars($edit_user['name']) ?>" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required></div>
            <div class="form-group"><label>Телефон</label><input type="text" name="phone" value="<?= htmlspecialchars($edit_user['phone']) ?>"></div>
            <div class="form-group"><label>Комментарий *</label><textarea name="comment" required><?= htmlspecialchars($edit_user['comment']) ?></textarea></div>
            <div class="form-group"><button type="submit" name="edit_user" class="btn btn-save">💾 Сохранить изменения</button><a href="admin.php" class="btn btn-cancel">Отмена</a></div>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>📋 Список заявок</h2>
        <?php if (empty($users)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">Нет зарегистрированных пользователей</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>ID</th><th>Логин</th><th>Имя</th><th>Email</th><th>Телефон</th><th>Комментарий</th><th>Дата регистрации</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['login']) ?></strong></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars(mb_substr($user['comment'], 0, 60)) . (mb_strlen($user['comment']) > 60 ? '…' : '') ?></td>
                            <td><?= $user['created_at'] ?></td>
                            <td><a href="?edit=<?= $user['id'] ?>" class="btn btn-edit">✏️ Ред.</a> <a href="?delete=<?= $user['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить пользователя <?= htmlspecialchars($user['name']) ?>?')">🗑️ Удал.</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>