<?php
/**
 * api.php — REST веб-сервис для формы обратной связи.
 *
 * GET  /api.php  — профиль авторизованного пользователя (Basic Auth)
 * POST /api.php  — регистрация нового пользователя
 * PUT  /api.php  — обновление данных (Basic Auth)
 */

// ─── Заголовки ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Настройки БД ─────────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_name = 'u82419';
$db_user = 'u82419';
$db_pass = '7111555';

// ─── Вспомогательные функции ─────────────────────────────────────────────────

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function validate(array $data): array {
    $errors = [];

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        $errors[] = 'Имя обязательно.';
    } elseif (mb_strlen($name) > 128) {
        $errors[] = 'Имя слишком длинное (макс. 128 символов).';
    }

    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors[] = 'Email обязателен.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email.';
    }

    $phone = trim($data['phone'] ?? '');
    if ($phone !== '' && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) {
        $errors[] = 'Некорректный номер телефона.';
    }

    $comment = trim($data['comment'] ?? '');
    if ($comment === '') {
        $errors[] = 'Комментарий обязателен.';
    }

    return $errors;
}

function generateLogin($pdo): string {
    do {
        $login = substr(uniqid(), 0, 8);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

function generatePassword(): string {
    return substr(md5(rand()), 0, 8);
}

function parseBasicAuth(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
    if (stripos($header, 'Basic ') !== 0) return null;
    $decoded = base64_decode(substr($header, 6), true);
    if ($decoded === false) return null;
    $parts = explode(':', $decoded, 2);
    return count($parts) === 2 ? $parts : null;
}

function authenticate($pdo): ?array {
    $creds = parseBasicAuth();
    if ($creds === null) return null;
    [$login, $password] = $creds;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? LIMIT 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return null;
    if ($user['pass_hash'] === md5($password)) return $user;
    if (password_verify($password, $user['pass_hash'])) return $user;
    return null;
}

// ─── Основная логика ─────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];

// GET — профиль авторизованного пользователя
if ($method === 'GET') {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $user = authenticate($pdo);
        if (!$user) {
            respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
        }
        respond(200, [
            'success' => true,
            'profile' => [
                'login'   => $user['login'],
                'name'    => $user['name'],
                'email'   => $user['email'],
                'phone'   => $user['phone'],
                'comment' => $user['comment'],
            ],
        ]);
    } catch (Exception $e) {
        error_log('DB error GET: ' . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка сервера. Попробуйте позже.']);
    }
}

// POST — регистрация нового пользователя
if ($method === 'POST') {
    $input  = getInput();
    $errors = validate($input);

    if ($errors) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            http_response_code(422);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>Ошибки валидации:</h2><ul>';
            foreach ($errors as $err) {
                echo '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul><p><a href="javascript:history.back()">← Вернуться</a></p>';
            exit;
        }
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        $login    = generateLogin($pdo);
        $password = generatePassword();
        $passHash = md5($password);

        $stmt = $pdo->prepare(
            "INSERT INTO users (login, pass_hash, name, email, phone, comment)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $login,
            $passHash,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']   ?? ''),
            trim($input['comment'] ?? ''),
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log('DB error POST: ' . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка базы данных. Попробуйте позже.']);
    }

    // Fallback без JS
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h2>✅ Заявка принята!</h2>';
        echo '<p><strong>Логин:</strong> ' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><strong>Пароль:</strong> ' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>⚠️ Сохраните эти данные — пароль больше не будет показан.</p>';
        echo '<p><a href="index.html">← На главную</a></p>';
        exit;
    }

    respond(201, [
        'success'    => true,
        'login'      => $login,
        'password'   => $password,
        'profileUrl' => 'api.php',
    ]);
}

// PUT — обновление данных авторизованного пользователя
if ($method === 'PUT') {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $user = authenticate($pdo);
        if (!$user) {
            respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
        }

        $input  = getInput();
        $errors = validate($input);
        if ($errors) {
            respond(422, ['success' => false, 'errors' => $errors]);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "UPDATE users SET name=?, email=?, phone=?, comment=? WHERE id=?"
        );
        $stmt->execute([
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']   ?? ''),
            trim($input['comment'] ?? ''),
            $user['id'],
        ]);
        $pdo->commit();

        respond(200, ['success' => true, 'message' => 'Данные успешно обновлены.']);
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log('DB error PUT: ' . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка базы данных. Попробуйте позже.']);
    }
}

respond(405, ['success' => false, 'message' => 'Метод не поддерживается.']);
