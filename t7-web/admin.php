<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

$auth_success = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $stmt = $db->prepare("SELECT pass_hash FROM admins WHERE login = ?");
        $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            if (password_verify($_SERVER['PHP_AUTH_PW'], $admin['pass_hash'])) {
                $auth_success = true;
            } elseif ($admin['pass_hash'] === md5($_SERVER['PHP_AUTH_PW'])) {
                $newHash = password_hash($_SERVER['PHP_AUTH_PW'], PASSWORD_BCRYPT);
                $db->prepare("UPDATE admins SET pass_hash = ? WHERE login = ?")
                   ->execute([$newHash, $_SERVER['PHP_AUTH_USER']]);
                $auth_success = true;
            }
        }
    } catch (PDOException $e) {
        error_log('DB error in admin.php auth: ' . $e->getMessage());
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
    <style>
        .container { max-width: 500px; margin: 100px auto; text-align: center; font-family: Arial, sans-serif; }
        h1 { color: #721c24; }
        p { color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>401 Требуется авторизация</h1>
        <p>Для доступа к административной панели введите логин и пароль.</p>
        <p><a href="index.php">← Вернуться на главную</a></p>
    </div>
</body>
</html>';
    exit();
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        http_response_code(403);
        die('Ошибка: недействительный CSRF-токен.');
    }

    $user_id = (int)($_POST['id'] ?? 0);
    if ($user_id > 0) {
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM app_languages WHERE app_id = ?")->execute([$user_id]);
            $db->prepare("DELETE FROM application WHERE id = ?")->execute([$user_id]);
            $db->commit();
            header('Location: admin.php?msg=deleted');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('DB error in admin.php delete: ' . $e->getMessage());
            $error = 'Ошибка удаления. Попробуйте позже.';
        }
    }
}

$action  = $_GET['action'] ?? '';
$user_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
    
    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        http_response_code(403);
        die('Ошибка: недействительный CSRF-токен.');
    }

    $errors = [];

    $fullName  = trim($_POST['fullName'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender    = $_POST['gender'] ?? '';
    $msgText   = trim($_POST['message'] ?? '');
    $contract  = isset($_POST['contract']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];

    if (empty($fullName) || strlen($fullName) > 150 || !preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $fullName)) $errors['fullName'] = true;
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $errors['email'] = true;
    $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone) || !preg_match('/^[\d\s\-\+\(\)]+$/', $phone) || strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) $errors['phone'] = true;
    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (empty($birthdate) || !$date || $date->format('Y-m-d') !== $birthdate) $errors['birthdate'] = true;
    if (empty($gender) || !in_array($gender, ['male', 'female'])) $errors['gender'] = true;
    if (empty($msgText) || strlen($msgText) < 4 || strlen($msgText) > 65535) $errors['message'] = true;

    $allowed_langs = ['pascal','c','cpp','javascript','php','python','java','haskell','clojure','prolog','scala','go'];
    if (empty($languages)) {
        $errors['languages'] = true;
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowed_langs, true)) { $errors['languages'] = true; break; }
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "UPDATE application SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract = ? WHERE id = ?"
            );
            $stmt->execute([$fullName, $phone, $email, $birthdate, $gender, $msgText, $contract, $user_id]);

            $db->prepare("DELETE FROM app_languages WHERE app_id = ?")->execute([$user_id]);

            $lang_map = [];
            foreach ($db->query("SELECT id, code FROM languages")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $lang_map[$row['code']] = $row['id'];
            }

            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($languages as $lang) {
                if (isset($lang_map[$lang])) $insertLang->execute([$user_id, $lang_map[$lang]]);
            }

            $db->commit();
            header('Location: admin.php?msg=updated');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('DB error in admin.php edit: ' . $e->getMessage());
            $error = 'Ошибка обновления. Попробуйте позже.';
        }
    }
}

$statusMessage = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted': $statusMessage = '<div class="success-msg">Пользователь успешно удален</div>'; break;
        case 'updated': $statusMessage = '<div class="success-msg">Данные пользователя успешно обновлены</div>'; break;
    }
}

$users = [];
try {
    $stmt = $db->query("
        SELECT a.*, GROUP_CONCAT(l.code ORDER BY l.code) as languages_codes
        FROM application a
        LEFT JOIN app_languages al ON a.id = al.app_id
        LEFT JOIN languages l ON al.lang_id = l.id
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB error in admin.php users list: ' . $e->getMessage());
    $error = 'Ошибка загрузки данных. Попробуйте позже.';
}

$languageStats = [];
try {
    $stmt = $db->query("
        SELECT l.code, l.name, COUNT(al.app_id) as count
        FROM languages l
        LEFT JOIN app_languages al ON l.id = al.lang_id
        GROUP BY l.id ORDER BY count DESC
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
        error_log('DB error in admin.php editUser: ' . $e->getMessage());
        $error = 'Ошибка загрузки данных для редактирования.';
    }
}

$languages_list = [
    'pascal' => 'Pascal', 'c' => 'C', 'cpp' => 'C++',
    'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
    'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
    'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
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

    * { 
        box-sizing: border-box; 
        margin: 0; 
        padding: 0; 
        font-family: Arial, sans-serif; 
    }

    body { 
        background: #eee; 
        padding: 20px; 
    }

    .container { 
        max-width: 1200px; 
        margin: 0 auto; 
    }

    .header { 
        background: #333; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 6px 6px 0 0; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px;
    }

    .header h1 { 
        font-size: 1.2rem; 
    }

    .admin-info { 
        font-size: .85rem; 
    }

    .stats-section { 
        background: white; 
        padding: 20px; 
        border-radius: 6px; 
        margin-bottom: 20px; 
    }

    .stats-title { 
        font-size: 1rem; 
        color: #333; 
        margin-bottom: 15px; 
    }

    .stats-grid { 
        display: flex; 
        gap: 15px; 
        flex-wrap: wrap; 
    }

    .stat-card { 
        background: #f5f5f5; 
        padding: 15px 25px; 
        border-radius: 6px; 
        text-align: center; 
    }

    .stat-number { 
        font-size: 2rem; 
        font-weight: bold; 
        color: #333; 
    }

    .stat-label { 
        font-size: .8rem; 
        color: #666; 
    }

    .lang-stats { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 10px; 
    }

    .lang-stat-item { 
        background: #f0f0f0; 
        padding: 8px 12px; 
        border-radius: 4px; 
        font-size: .85rem; 
    }

    .lang-stat-count { 
        background: #333; 
        color: white; 
        padding: 2px 6px; 
        border-radius: 3px; 
        margin-left: 6px; 
        font-size: .75rem; 
    }

    .users-section { 
        background: white; 
        padding: 20px; 
        border-radius: 6px; 
    }

    .users-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: .85rem; 
    }

    .users-table th, 
    .users-table td { 
        padding: 10px; 
        border: 1px solid #ddd; 
        text-align: left; 
        vertical-align: top; 
    }

    .users-table th { 
        background: #333; 
        color: white; 
    }

    .users-table tr:nth-child(even) { 
        background: #f9f9f9; 
    }

    .action-buttons { 
        display: flex; 
        gap: 6px; 
        flex-wrap: wrap; 
    }

    .btn-edit { 
        background: #4a7fbd; 
        color: white; 
        padding: 4px 10px; 
        border-radius: 3px; 
        text-decoration: none; 
        font-size: .8rem; 
    }

    .btn-delete { 
        background: #c0392b; 
        color: white; 
        padding: 4px 10px; 
        border-radius: 3px; 
        font-size: .8rem; 
        border: none; 
        cursor: pointer; 
    }

    .btn-save { 
        background: #27ae60; 
        color: white; 
        padding: 6px 16px; 
        border-radius: 3px; 
        border: none; 
        cursor: pointer; 
        font-size: .9rem; 
    }

    .btn-cancel { 
        background: #aaa; 
        color: white; 
        padding: 6px 16px; 
        border-radius: 3px; 
        text-decoration: none; 
        font-size: .9rem; 
    }

    .badge { 
        background: #4a7fbd; 
        color: white; 
        padding: 2px 7px; 
        border-radius: 10px; 
        font-size: .75rem; 
        display: inline-block; 
        margin: 1px; 
    }

    .success-msg { 
        background: #d4edda; 
        color: #155724; 
        padding: 10px; 
        border-radius: 4px; 
        margin-bottom: 15px; 
    }

    .error-msg { 
        background: #f8d7da; 
        color: #721c24; 
        padding: 10px; 
        border-radius: 4px; 
        margin-bottom: 15px; 
    }

    .modal { 
        display: <?php echo $editUser ? 'flex' : 'none'; ?>; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,.5); 
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
        border-radius: 6px; 
    }

    .modal-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 15px; 
        padding-bottom: 10px; 
        border-bottom: 1px solid #ccc; 
    }

    .modal-header h2 { 
        font-size: 1.1rem; 
        color: #333; 
    }

    .close-modal { 
        background: none; 
        border: none; 
        font-size: 1.4rem; 
        cursor: pointer; 
    }

    .form-group { 
        margin-bottom: 12px; 
    }

    .form-group label { 
        display: block; 
        margin-bottom: 4px; 
        font-weight: bold; 
        font-size: .9rem; 
    }

    .form-group input, 
    .form-group textarea, 
    .form-group select { 
        width: 100%; 
        padding: 6px 8px; 
        border: 1px solid #aaa; 
        font-size: .9rem; 
        border-radius: 3px; 
    }

    .form-group textarea { 
        min-height: 80px; 
        resize: vertical; 
    }

    .radio-group { 
        display: inline-block; 
        margin-right: 15px; 
    }

    .radio-group input { 
        width: auto; 
        margin-right: 4px; 
    }

    .checkbox-group { 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }

    .checkbox-group input { 
        width: auto; 
    }

    select[multiple] { 
        min-height: 120px; 
    }

    .form-actions { 
        display: flex; 
        gap: 10px; 
        margin-top: 15px; 
        padding-top: 12px; 
        border-top: 1px solid #ccc; 
    }

</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Административная панель</h1>
        <div class="admin-info">
            Вы вошли как <strong><?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </div>

    <?php if ($statusMessage): echo $statusMessage; endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="stats-section">
        <h2 class="stats-title">Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)$totalUsers; ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)count($languageStats); ?></div>
                <div class="stat-label">Языков программирования</div>
            </div>
        </div>
        <h3 style="margin: 20px 0 10px; color: #566777;">Популярность языков программирования</h3>
        <div class="lang-stats">
            <?php foreach ($languageStats as $lang): ?>
            <div class="lang-stat-item">
                <strong><?php echo htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="lang-stat-count"><?php echo (int)$lang['count']; ?> чел.</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="users-section">
        <h2 class="stats-title">Все пользователи</h2>
        <?php if (empty($users)): ?>
        <p style="text-align:center; padding:40px; color:#566777;">Нет зарегистрированных пользователей</p>
        <?php else: ?>
        <table class="users-table">
            <thead>
            <tr>
                <th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th>
                <th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo (int)$user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['fio'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($user['birth_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $user['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                <td>
                    <?php
                    $langs = explode(',', $user['languages_codes'] ?? '');
                    foreach ($langs as $lang):
                        if ($lang && isset($languages_list[$lang])):
                    ?>
                    <span class="badge"><?php echo htmlspecialchars($languages_list[$lang], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="admin.php?action=edit&id=<?php echo (int)$user['id']; ?>" class="btn-edit">Редакт.</a>
                        <form method="POST" action="admin.php" style="display:inline"
                              onsubmit="return confirm('Удалить пользователя <?php echo htmlspecialchars($user['fio'], ENT_QUOTES, 'UTF-8'); ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn-delete">Удалить</button>
                        </form>
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
<div class="modal" style="display:flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактирование пользователя #<?php echo (int)$editUser['id']; ?></h2>
            <button class="close-modal" onclick="window.location.href='admin.php'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="fullName" value="<?php echo htmlspecialchars($editUser['fio'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birthdate" value="<?php echo htmlspecialchars($editUser['birth_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
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
                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo in_array($code, $editUser['languages'] ?? []) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#566777;">Для выбора нескольких: Ctrl + клик</small>
            </div>
            <div class="form-group">
                <label>Биография *</label>
                <textarea name="message" required><?php echo htmlspecialchars($editUser['bio'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="contract" <?php echo $editUser['contract'] ? 'checked' : ''; ?>> С контрактом ознакомлен(а)
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">Сохранить изменения</button>
                <a href="admin.php" class="btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>