<?php
/**
 * api.php — REST веб-сервис для формы обратной связи.
 *
 * GET  /api.php  — профиль авторизованного пользователя (Basic Auth)
 * POST /api.php  — регистрация нового пользователя
 * PUT  /api.php  — обновление данных (Basic Auth)
 */

// ─── Подключение к БД (как в рабочем задании) ────────────────────────────────
$user = 'u82419';
$pass = '7111555';
$db = new PDO(
    "mysql:host=localhost;dbname=$user;charset=utf8mb4", $user, $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ─── Заголовки ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

/** Генерация логина — как в рабочем задании (_yes): uniqid() обрезанный до 8 символов */
function generateLogin(): string {
    global $db;
    do {
        $login = substr(uniqid(), 0, 8);
        $stmt = $db->prepare("SELECT id FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
    } while ($stmt->fetch()); // повторяем пока не уникальный
    return $login;
}

/** Генерация пароля — как в рабочем задании (_yes): md5(rand()) обрезанный до 8 символов */
function generatePassword(): string {
    return substr(md5(rand()), 0, 8);
}

/** Разбирает Authorization: Basic ... → ['login', 'password'] или null */
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

/** Авторизация через Basic Auth. Проверяет md5 (как в _yes) и bcrypt */
function authenticate(): ?array {
    global $db;
    $creds = parseBasicAuth();
    if ($creds === null) return null;

    [$login, $password] = $creds;

    $stmt = $db->prepare("SELECT * FROM users WHERE login = ? LIMIT 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) return null;

    // Поддерживаем оба формата хэша: md5 (старый) и bcrypt (новый)
    if ($user['pass_hash'] === md5($password)) return $user;
    if (password_verify($password, $user['pass_hash'])) return $user;

    return null;
}

// ─── Маршрутизация ────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];

// GET — профиль авторизованного пользователя
if ($method === 'GET') {
    $user = authenticate();
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
}

// POST — регистрация нового пользователя
if ($method === 'POST') {
    $input  = getInput();
    $errors = validate($input);

    if ($errors) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            // Fallback без JS — возвращаем HTML
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

    $login    = generateLogin();
    $password = generatePassword();
    $passHash = md5($password); // как в _yes

    try {
        $stmt = $db->prepare(
            "INSERT INTO users (login, pass_hash, name, email, phone, comment)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $login,
            $passHash,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']    ?? ''),
            trim($input['comment']  ?? ''),
        ]);
    } catch (PDOException $e) {
        error_log('DB error POST: ' . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка при сохранении данных.']);
    }

    // Fallback без JS — возвращаем HTML
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
        'profileUrl' => 'http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web/api.php',
    ]);
}

// PUT — обновление данных авторизованного пользователя
if ($method === 'PUT') {
    $user = authenticate();
    if (!$user) {
        respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
    }

    $input  = getInput();
    $errors = validate($input);

    if ($errors) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    try {
        $stmt = $db->prepare(
            "UPDATE users SET name=?, email=?, phone=?, comment=? WHERE id=?"
        );
        $stmt->execute([
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']   ?? ''),
            trim($input['comment'] ?? ''),
            $user['id'],
        ]);
    } catch (PDOException $e) {
        error_log('DB error PUT: ' . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка при обновлении данных.']);
    }

    respond(200, ['success' => true, 'message' => 'Данные успешно обновлены.']);
}

respond(405, ['success' => false, 'message' => 'Метод не поддерживается.']);
