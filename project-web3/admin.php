<?php
declare(strict_types=1);

require_once 'db_config.php';

// ========== HTTP АВТОРИЗАЦИЯ ==========
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Доступ запрещен. Требуется авторизация.');
}

// ========== ОБРАБОТКА ДЕЙСТВИЙ ==========

// Удаление пользователя
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin.php');
    exit;
}

// Редактирование пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, email = ?, phone = ?, comment = ? 
        WHERE id = ?
    ");
    $stmt->execute([$name, $email, $phone, $comment, $id]);

    header('Location: admin.php');
    exit;
}

// ========== ЗАГРУЗКА ДАННЫХ ==========
$users = $pdo->query("
    SELECT * FROM users 
    ORDER BY id DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        input, textarea { width: 100%; padding: 6px; box-sizing: border-box; }
        button { padding: 8px 12px; background: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background: #218838; }
        .delete-btn { background: #dc3545; }
        .delete-btn:hover { background: #c82333; }
    </style>
</head>
<body>

<h1>Админ-панель — Пользователи</h1>

<table>
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
            <form method="POST">
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['login']) ?></td>
                <td><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>"></td>
                <td><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"></td>
                <td><input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></td>
                <td><textarea name="comment" rows="2"><?= htmlspecialchars($user['comment']) ?></textarea></td>
                <td><?= $user['created_at'] ?></td>
                <td>
                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                    <button type="submit">Сохранить</button>
                    <a href="?delete=<?= $user['id'] ?>" 
                       class="delete-btn" 
                       style="padding:8px 12px; color:white; text-decoration:none;"
                       onclick="return confirm('Удалить пользователя?')">
                        Удалить
                    </a>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;">Пользователей пока нет</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>