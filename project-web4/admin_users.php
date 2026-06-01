<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

// ========== HTTP-АВТОРИЗАЦИЯ (как в _task6) ==========
$auth_success = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM admins WHERE login = ? AND pass_hash = MD5(?)");
        $stmt->execute([$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']]);
        if ($stmt->fetch()) {
            $auth_success = true;
        }
    } catch (PDOException $e) {
        $auth_success = false;
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
        <link rel="stylesheet" href="styles.css">
        <style>
            .container { max-width: 500px; margin: 100px auto; text-align: center; }
            h1 { color: #721c24; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>401 Требуется авторизация</h1>
            <p>Для доступа к административной панели необходимо ввести логин и пароль.</p>
            <p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> admin123</p>
            <p><a href="user_form.php">← Вернуться к форме</a></p>
        </div>
    </body>
    </html>';
    exit();
}

// ========== ДЕЙСТВИЯ ==========
$action  = $_GET['action'] ?? '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Удаление
if ($action === 'delete' && $user_id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM table1 WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: admin_users.php?msg=deleted');
        exit();
    } catch (PDOException $e) {
        $error = "Ошибка удаления: " . $e->getMessage();
    }
}

// Сохранение после редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
    $editErrors = [];

    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $consent = isset($_POST['consent']) ? 1 : 0;

    if (empty($name) || strlen($name) > 150 || !preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $name)) {
        $editErrors['name'] = true;
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $editErrors['email'] = true;
    } else {
        // уникальность email (исключая текущую запись)
        try {
            $chk = $db->prepare("SELECT id FROM table1 WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                $editErrors['email'] = true;
            }
        } catch (PDOException $e) {
            $editErrors['email'] = true;
        }
    }

    $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone) || !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)
        || strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) {
        $editErrors['phone'] = true;
    }

    if ($comment === '' || strlen($comment) < 4 || strlen($comment) > 65535) {
        $editErrors['comment'] = true;
    }

    if (empty($editErrors)) {
        try {
            $stmt = $db->prepare(
                "UPDATE table1 SET name = ?, email = ?, phone = ?, comment = ?, consent = ? WHERE id = ?"
            );
            $stmt->execute([$name, $email, $phone, $comment, $consent, $user_id]);
            header('Location: admin_users.php?msg=updated');
            exit();
        } catch (PDOException $e) {
            $error = "Ошибка обновления: " . $e->getMessage();
        }
    }
}

// ========== СООБЩЕНИЯ ==========
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted':
            $message = '<div class="success-msg">Запись успешно удалена</div>';
            break;
        case 'updated':
            $message = '<div class="success-msg">Данные успешно обновлены</div>';
            break;
    }
}

// ========== ДАННЫЕ ==========
$users = [];
try {
    $stmt = $db->query("SELECT * FROM table1 ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка загрузки данных: " . $e->getMessage();
}

$totalUsers = count($users);

// Данные для формы редактирования
$editUser = null;
if ($action === 'edit' && $user_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM table1 WHERE id = ?");
        $stmt->execute([$user_id]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Ошибка загрузки записи для редактирования";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Административная панель — Пользователи</title>
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
        gap: 10px;
    }

    .header h1 { font-size: 1.3rem; }

    .admin-info {
        background: #555;
        padding: 5px 12px;
        font-size: 0.9rem;
    }

    .stats-section {
        background: white;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
    }

    .stats-title { color: #333; font-size: 1.1rem; margin-bottom: 12px; }

    .stats-grid { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }

    .stat-card {
        background: #f5f5f5;
        border: 1px solid #ccc;
        padding: 12px 20px;
        text-align: center;
        min-width: 140px;
    }

    .stat-number { font-size: 2rem; font-weight: bold; color: #333; }

    .stat-label { color: #666; font-size: 0.85rem; margin-top: 4px; }

    .users-section {
        background: white;
        padding: 15px 20px;
        border: 1px solid #ccc;
    }

    .users-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .users-table th, .users-table td {
        padding: 9px 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .users-table th { background: #333; color: white; }

    .users-table tr:hover { background: #f9f9f9; }

    .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }

    .btn-edit, .btn-delete, .btn-save, .btn-cancel {
        padding: 4px 10px;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-block;
    }

    .btn-edit   { background: #ffc107; color: #333; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-save   { background: #28a745; color: white; }
    .btn-cancel { background: #6c757d; color: white; }

    .btn-edit:hover   { background: #e0a800; }
    .btn-delete:hover { background: #c82333; }
    .btn-save:hover   { background: #218838; }
    .btn-cancel:hover { background: #5a6268; }

    .success-msg {
        padding: 8px 12px;
        margin-bottom: 12px;
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error-msg {
        padding: 8px 12px;
        margin-bottom: 12px;
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Модальное окно редактирования */
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

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #aaa;
        font-size: 0.9rem;
    }

    .form-group input:focus,
    .form-group textarea:focus { outline: 2px solid #333; }

    .form-group textarea { min-height: 80px; resize: vertical; }

    .checkbox-group { display: flex; align-items: center; gap: 8px; }
    .checkbox-group input { width: auto; }

    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 12px;
        border-top: 1px solid #ccc;
    }

    .consent-yes { color: #155724; font-weight: bold; }
    .consent-no  { color: #721c24; }

    @media (max-width: 768px) {
        .users-table th, .users-table td { padding: 7px; font-size: 0.8rem; }
        .action-buttons { flex-direction: column; }
        .header { flex-direction: column; }
        .stats-grid { flex-direction: column; }
    }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Административная панель — Пользователи</h1>
        <div class="admin-info">Вы вошли как <strong><?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></strong></div>
    </div>

    <?php if ($message): echo $message; endif; ?>
    <?php if (isset($error)): echo '<div class="error-msg">' . htmlspecialchars($error) . '</div>'; endif; ?>

    <div class="stats-section">
        <h2 class="stats-title">Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Всего записей</div>
            </div>
            <div class="stat-card">
                <?php
                $consentCount = array_filter($users, fn($u) => $u['consent'] == 1);
                ?>
                <div class="stat-number"><?php echo count($consentCount); ?></div>
                <div class="stat-label">Дали согласие</div>
            </div>
        </div>
    </div>

    <div class="users-section">
        <h2 class="stats-title">Все записи</h2>
        <?php if (empty($users)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">Нет записей в таблице</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="users-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Комментарий</th>
                    <th>Согласие</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><?php
                        $short = mb_substr($user['comment'], 0, 60);
                        echo htmlspecialchars($short) . (mb_strlen($user['comment']) > 60 ? '…' : '');
                    ?></td>
                    <td>
                        <?php if ($user['consent']): ?>
                            <span class="consent-yes">Да</span>
                        <?php else: ?>
                            <span class="consent-no">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="admin_users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit">Редакт.</a>
                            <a href="admin_users.php?action=delete&id=<?php echo $user['id']; ?>"
                               class="btn-delete"
                               onclick="return confirm('Удалить запись <?php echo htmlspecialchars(addslashes($user['name'])); ?>?')">Удалить</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($editUser): ?>
<div class="modal" style="display: flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактирование записи #<?php echo $editUser['id']; ?></h2>
            <button class="close-modal" onclick="window.location.href='admin_users.php'">&times;</button>
        </div>

        <?php if (!empty($editErrors)): ?>
            <div class="error-msg">Исправьте ошибки в форме.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Имя *</label>
                <input type="text" name="name"
                       value="<?php echo htmlspecialchars($_POST['name'] ?? $editUser['name']); ?>"
                       style="<?php echo !empty($editErrors['name']) ? 'border-color:red;' : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? $editUser['email']); ?>"
                       style="<?php echo !empty($editErrors['email']) ? 'border-color:red;' : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Телефон *</label>
                <input type="text" name="phone"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? $editUser['phone']); ?>"
                       style="<?php echo !empty($editErrors['phone']) ? 'border-color:red;' : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Комментарий *</label>
                <textarea name="comment"
                          style="<?php echo !empty($editErrors['comment']) ? 'border-color:red;' : ''; ?>"
                          required><?php echo htmlspecialchars($_POST['comment'] ?? $editUser['comment']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="consent"
                           <?php echo ($editUser['consent'] ? 'checked' : ''); ?>>
                    Согласие на обработку данных
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Сохранить изменения</button>
                <a href="admin_users.php" class="btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>