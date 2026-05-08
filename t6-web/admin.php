<?php


header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

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
        <link rel="stylesheet" href="style.css">
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
            <p><a href="index.php">← Вернуться на главную</a></p>
        </div>
    </body>
    </html>';
    exit();
}



$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

if ($action === 'delete' && $user_id > 0) {
try {
$db->beginTransaction();

$stmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
$stmt->execute([$user_id]);

$stmt = $db->prepare("DELETE FROM application WHERE id = ?");
$stmt->execute([$user_id]);

$db->commit();

header('Location: admin.php?msg=deleted');
exit();
} catch (PDOException $e) {
$db->rollBack();
$error = "Ошибка удаления: " . $e->getMessage();
}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
$errors = [];

$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$birthdate = $_POST['birthdate'] ?? '';
$gender = $_POST['gender'] ?? '';
$message = trim($_POST['message'] ?? '');
$contract = isset($_POST['contract']) ? 1 : 0;
$languages = $_POST['languages'] ?? [];

if (empty($fullName) || strlen($fullName) > 150 || !preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $fullName)) {
$errors['fullName'] = true;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
$errors['email'] = true;
}

$digitsOnly = preg_replace('/[^0-9]/', '', $phone);
if (empty($phone) || !preg_match('/^[\d\s\-\+\(\)]+$/', $phone) || strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) {
$errors['phone'] = true;
}

$date = DateTime::createFromFormat('Y-m-d', $birthdate);
if (empty($birthdate) || !$date || $date->format('Y-m-d') !== $birthdate) {
$errors['birthdate'] = true;
}

if (empty($gender) || !in_array($gender, ['male', 'female'])) {
$errors['gender'] = true;
}

if (empty($message) || strlen($message) < 4 || strlen($message) > 65535) {
$errors['message'] = true;
}

$allowed_langs = ['pascal','c','cpp','javascript','php','python','java','haskell','clojure','prolog','scala','go'];
if (empty($languages)) {
$errors['languages'] = true;
} else {
foreach ($languages as $lang) {
if (!in_array($lang, $allowed_langs)) {
$errors['languages'] = true;
break;
}
}
}

if (empty($errors)) {
try {
$db->beginTransaction();

$stmt = $db->prepare("UPDATE application SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract = ? WHERE id = ?");
$stmt->execute([$fullName, $phone, $email, $birthdate, $gender, $message, $contract, $user_id]);

$stmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
$stmt->execute([$user_id]);

$langStmt = $db->query("SELECT id, code FROM languages");
$lang_map = [];
while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
$lang_map[$row['code']] = $row['id'];
}

$insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
foreach ($languages as $lang) {
if (isset($lang_map[$lang])) {
$insertLang->execute([$user_id, $lang_map[$lang]]);
}
}

$db->commit();

header('Location: admin.php?msg=updated');
exit();
} catch (PDOException $e) {
$db->rollBack();
$error = "Ошибка обновления: " . $e->getMessage();
}
}
}

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

$users = [];
try {
$stmt = $db->query("
SELECT a.*,
GROUP_CONCAT(l.code ORDER BY l.code) as languages_codes
FROM application a
LEFT JOIN app_languages al ON a.id = al.app_id
LEFT JOIN languages l ON al.lang_id = l.id
GROUP BY a.id
ORDER BY a.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
$error = "Ошибка загрузки данных: " . $e->getMessage();
}

$languageStats = [];
try {
$stmt = $db->query("
SELECT l.code, l.name, COUNT(al.app_id) as count
FROM languages l
LEFT JOIN app_languages al ON l.id = al.lang_id
GROUP BY l.id
ORDER BY count DESC
");
$languageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
$languageStats = [];
}

$editUser = null;
if ($action === 'edit' && $user_id > 0) {
try {
$stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
$stmt->execute([$user_id]);
$editUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($editUser) {
$stmt = $db->prepare("SELECT l.code FROM languages l JOIN app_languages al ON l.id = al.lang_id WHERE al.app_id = ?");
$stmt->execute([$user_id]);
$editUser['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
} catch (PDOException $e) {
$error = "Ошибка загрузки данных для редактирования";
}
}

$languages_list = [
'pascal' => 'Pascal',
'c' => 'C',
'cpp' => 'C++',
'javascript' => 'JavaScript',
'php' => 'PHP',
'python' => 'Python',
'java' => 'Java',
'haskell' => 'Haskell',
'clojure' => 'Clojure',
'prolog' => 'Prolog',
'scala' => 'Scala',
'go' => 'Go'
];

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
    }

    .header h1 { font-size: 1.3rem; }

    .admin-info { font-size: 0.9rem; }

    .stats-section, .users-section {
        background: white;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
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
        min-width: 130px;
    }

    .stat-number { font-size: 1.8rem; font-weight: bold; }
    .stat-label { font-size: 0.8rem; margin-top: 4px; }

    .lang-stats { display: flex; flex-wrap: wrap; gap: 8px; }

    .lang-stat-item {
        background: #f0f0f0;
        padding: 5px 12px;
        border: 1px solid #ccc;
        font-size: 0.9rem;
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .lang-stat-count {
        background: #333;
        color: white;
        padding: 1px 7px;
        font-size: 0.8rem;
    }

    .success-msg { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
    .error-msg   { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb; }

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
    }

    .btn-edit   { background: #ffc107; color: #333; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-save   { background: #333; color: white; padding: 8px 18px; }
    .btn-cancel { background: #777; color: white; padding: 8px 18px; }

    .btn-edit:hover   { background: #e0a800; }
    .btn-delete:hover { background: #c82333; }
    .btn-save:hover   { background: #555; }
    .btn-cancel:hover { background: #999; }

    .badge {
        background: #ddd;
        color: #333;
        padding: 2px 7px;
        font-size: 0.75rem;
        display: inline-block;
        margin: 1px;
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
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #aaa;
        font-size: 0.9rem;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus { outline: 2px solid #333; }

    .form-group textarea { min-height: 80px; resize: vertical; }

    .radio-group { display: inline-block; margin-right: 15px; }
    .radio-group input { width: auto; margin-right: 4px; }

    .checkbox-group { display: flex; align-items: center; gap: 8px; }
    .checkbox-group input { width: auto; }

    select[multiple] { min-height: 120px; }

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
        .header { flex-direction: column; gap: 8px; }
        .stats-grid { flex-direction: column; }
    }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1> Административная панель</h1>
        <div class="admin-info">
            Вы вошли как <strong>admin</strong>
            
        </div>
    </div>

    <?php if ($message): echo $message; endif; ?>
    <?php if (isset($error)): echo '<div class="error-msg"> ' . htmlspecialchars($error) . '</div>'; endif; ?>

<div class="stats-section">
    <h2 class="stats-title"> Статистика</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Всего пользователей</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($languageStats); ?></div>
            <div class="stat-label">Языков программирования</div>
        </div>
    </div>

    <h3 style="margin: 20px 0 10px 0; color: #566777;"> Популярность языков программирования</h3>
    <div class="lang-stats">
        <?php foreach ($languageStats as $lang): ?>
        <div class="lang-stat-item">
            <strong><?php echo htmlspecialchars($lang['name']); ?></strong>
            <span class="lang-stat-count"><?php echo $lang['count']; ?> чел.</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="users-section">
    <h2 class="stats-title"> Все пользователи</h2>
    <?php if (empty($users)): ?>
    <p style="text-align: center; padding: 40px; color: #566777;">Нет зарегистрированных пользователей</p>
    <?php else: ?>
    <table class="users-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>ФИО</th>
            <th>Email</th>
            <th>Телефон</th>
            <th>Дата рождения</th>
            <th>Пол</th>
            <th>Языки</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['fio']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['phone']); ?></td>
            <td><?php echo date('d.m.Y', strtotime($user['birth_date'])); ?></td>
            <td><?php echo $user['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
            <td>
                <?php
                                $langs = explode(',', $user['languages_codes'] ?? '');
                                foreach ($langs as $lang):
                                    if ($lang && isset($languages_list[$lang])):
                                ?>
                <span class="badge"><?php echo $languages_list[$lang]; ?></span>
                <?php
                                    endif;
                                endforeach;
                                ?>
            </td>
            <td>
                <div class="action-buttons">
                    <a href="admin.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit">Редакт.</a>
                    <a href="admin.php?action=delete&id=<?php echo $user['id']; ?>"
                       class="btn-delete"
                       onclick="return confirm('Удалить пользователя <?php echo htmlspecialchars($user['fio']); ?>?')">Удалить</a>
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
            <h2> Редактирование пользователя #<?php echo $editUser['id']; ?></h2>
            <button class="close-modal" onclick="window.location.href='admin.php'">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="fullName" value="<?php echo htmlspecialchars($editUser['fio']); ?>" required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
            </div>

            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone']); ?>" required>
            </div>

            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birthdate" value="<?php echo $editUser['birth_date']; ?>" required>
            </div>

            <div class="form-group">
                <label>Пол *</label>
                <div>
                    <label class="radio-group">
                        <input type="radio" name="gender" value="male" <?php echo $editUser['gender'] == 'male' ? 'checked' : ''; ?>> Мужской
                    </label>
                    <label class="radio-group">
                        <input type="radio" name="gender" value="female" <?php echo $editUser['gender'] == 'female' ? 'checked' : ''; ?>> Женский
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Любимые языки программирования *</label>
                <select name="languages[]" multiple size="6">
                    <?php foreach ($languages_list as $code => $name): ?>
                    <option value="<?php echo $code; ?>"
                    <?php echo in_array($code, $editUser['languages'] ?? []) ? 'selected' : ''; ?>>
                    <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #566777;">Для выбора нескольких: Ctrl + клик</small>
            </div>

            <div class="form-group">
                <label>Биография *</label>
                <textarea name="message" required><?php echo htmlspecialchars($editUser['bio']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="contract" <?php echo $editUser['contract'] ? 'checked' : ''; ?>> С контрактом ознакомлен(а)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"> Сохранить изменения</button>
                <a href="admin.php" class="btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>
