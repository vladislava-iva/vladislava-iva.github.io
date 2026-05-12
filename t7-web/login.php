<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['login'])) {
    header('Location: index.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    setcookie(session_name(), '', time() - 3600);
    header('Location: login.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = $_POST['login'] ?? '';
    $pass  = $_POST['pass']  ?? '';

    if (empty($login) || empty($pass)) {
        $error = 'Заполните логин и пароль';
    } else {
        try {
            $stmt = $db->prepare("SELECT id, login, pass_hash FROM application WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $authenticated = false;
            if ($user) {
                if (password_verify($pass, $user['pass_hash'])) {
                    $authenticated = true;
                } elseif ($user['pass_hash'] === md5($pass)) {
                    $newHash = password_hash($pass, PASSWORD_BCRYPT);
                    $upd = $db->prepare("UPDATE application SET pass_hash = ? WHERE id = ?");
                    $upd->execute([$newHash, $user['id']]);
                    $authenticated = true;
                }
            }

            if ($authenticated) {
                session_regenerate_id(true);
                $_SESSION['login'] = $user['login'];
                $_SESSION['uid']   = $user['id'];

                header('Location: index.php');
                exit();
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (PDOException $e) {
            error_log('DB error in login.php: ' . $e->getMessage());
            $error = 'Ошибка сервера. Попробуйте позже.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .login-container { max-width: 400px; margin: 50px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,.1); }
        .login-title { color: #566777; margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #566777; font-weight: 600; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #C2C5CE; border-radius: 4px; font-size: .9rem; }
        .submit-btn { width: 100%; background-color: #566777; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; font-size: .9rem; }
        .submit-btn:hover { background-color: #475361; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .back-link { text-align: center; margin-top: 15px; }
        .back-link a { color: #566777; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Вход в систему</h1>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="" method="post">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login"
                       value="<?php echo htmlspecialchars($_POST['login'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group">
                <label for="pass">Пароль</label>
                <input type="password" id="pass" name="pass" required>
            </div>

            <button type="submit" class="submit-btn">Войти</button>
        </form>

        <div class="back-link">
            <a href="index.php">← Вернуться к форме</a>
        </div>
    </div>
</body>
</html>