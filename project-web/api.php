<?php
/**
 * Веб-сервис для обработки формы обратной связи.
 *
 * POST /api.php          — регистрация (без авторизации)
 * PUT  /api.php          — обновление профиля (с авторизацией)
 * GET  /api.php?login=X  — получение профиля (с авторизацией)
 *
 * Принимает JSON или XML (определяется по Content-Type).
 * Данные хранятся в файле users.json рядом со скриптом.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Хранилище ────────────────────────────────────────────────────────────────

define('DB_FILE', __DIR__ . '/users.json');

function loadUsers(): array {
    if (!file_exists(DB_FILE)) return [];
    $raw = file_get_contents(DB_FILE);
    return json_decode($raw, true) ?: [];
}

function saveUsers(array $users): void {
    file_put_contents(DB_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ─── Вспомогательные функции ─────────────────────────────────────────────────

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateLogin(string $name): string {
    $base = strtolower(preg_replace('/[^a-zA-Zа-яА-ЯёЁ0-9]/u', '', $name));
    if (empty($base)) $base = 'user';
    return $base . rand(100, 9999);
}

function generatePassword(int $len = 10): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$';
    $pass  = '';
    for ($i = 0; $i < $len; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

/** Парсит тело запроса как JSON или XML. */
function parseBody(): ?array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if (empty($raw)) return null;

    if (stripos($contentType, 'application/xml') !== false ||
        stripos($contentType, 'text/xml') !== false) {
        // XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml === false) return null;
        return json_decode(json_encode($xml), true);
    }

    // По умолчанию — JSON
    return json_decode($raw, true);
}

/** Базовая валидация полей формы. */
function validateFields(array $data, bool $requireAll = true): array {
    $errors = [];

    // Имя
    if ($requireAll || isset($data['name'])) {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Поле "name" обязательно.';
        } elseif (mb_strlen($name) < 2) {
            $errors[] = 'Имя должно быть не менее 2 символов.';
        }
    }

    // Email
    if ($requireAll || isset($data['email'])) {
        $email = trim($data['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'Поле "email" обязательно.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email.';
        }
    }

    // Телефон (необязательный, но если указан — валидируем)
    if (isset($data['phone']) && $data['phone'] !== '') {
        $phone = preg_replace('/\D/', '', $data['phone']);
        if (strlen($phone) < 7 || strlen($phone) > 15) {
            $errors[] = 'Некорректный номер телефона.';
        }
    }

    // Комментарий
    if ($requireAll || isset($data['comment'])) {
        $comment = trim($data['comment'] ?? '');
        if (empty($comment)) {
            $errors[] = 'Поле "comment" обязательно.';
        }
    }

    return $errors;
}

/** Возвращает текущего авторизованного пользователя или null. */
function getAuthorizedUser(array $users): ?array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Basic\s+(.+)$/i', $authHeader, $m)) return null;

    $decoded = base64_decode($m[1]);
    [$login, $password] = explode(':', $decoded, 2) + ['', ''];

    foreach ($users as $u) {
        if ($u['login'] === $login && password_verify($password, $u['password_hash'])) {
            return $u;
        }
    }
    return null;
}

// ─── Маршрутизация ────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$users  = loadUsers();

// GET /api.php — получить профиль авторизованного пользователя
if ($method === 'GET') {
    $user = getAuthorizedUser($users);
    if (!$user) {
        respond(401, ['success' => false, 'message' => 'Необходима авторизация.']);
    }
    respond(200, [
        'success' => true,
        'profile' => [
            'login'   => $user['login'],
            'name'    => $user['name'],
            'email'   => $user['email'],
            'phone'   => $user['phone'] ?? '',
            'comment' => $user['comment'] ?? '',
        ],
    ]);
}

// POST /api.php — регистрация нового пользователя
if ($method === 'POST') {
    $data = parseBody();
    if (!$data) {
        respond(400, ['success' => false, 'message' => 'Не удалось разобрать тело запроса (ожидается JSON или XML).']);
    }

    // Если пользователь уже авторизован — не регистрируем повторно
    $existing = getAuthorizedUser($users);
    if ($existing) {
        respond(400, ['success' => false, 'message' => 'Вы уже зарегистрированы. Используйте PUT для обновления данных.']);
    }

    $errors = validateFields($data, true);
    if ($errors) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    $email = strtolower(trim($data['email']));
    // Проверка уникальности email
    foreach ($users as $u) {
        if ($u['email'] === $email) {
            respond(409, ['success' => false, 'message' => 'Пользователь с таким email уже существует.']);
        }
    }

    $login    = generateLogin($data['name']);
    $password = generatePassword();

    $newUser = [
        'login'         => $login,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'name'          => trim($data['name']),
        'email'         => $email,
        'phone'         => trim($data['phone'] ?? ''),
        'comment'       => trim($data['comment']),
        'created_at'    => date('c'),
    ];

    $users[] = $newUser;
    saveUsers($users);

    respond(201, [
        'success'      => true,
        'message'      => 'Пользователь успешно зарегистрирован.',
        'login'        => $login,
        'password'     => $password,   // возвращаем пароль в открытом виде только при создании
        'profile_url'  => (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                          . '://' . $_SERVER['HTTP_HOST']
                          . '/api.php?profile=' . urlencode($login),
    ]);
}

// PUT /api.php — обновление данных авторизованного пользователя
if ($method === 'PUT') {
    $user = getAuthorizedUser($users);
    if (!$user) {
        respond(401, ['success' => false, 'message' => 'Необходима авторизация (Basic login:password).']);
    }

    $data = parseBody();
    if (!$data) {
        respond(400, ['success' => false, 'message' => 'Не удалось разобрать тело запроса.']);
    }

    $errors = validateFields($data, false);
    if ($errors) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    // Обновляем всё, кроме login и password
    foreach ($users as &$u) {
        if ($u['login'] === $user['login']) {
            if (isset($data['name']))    $u['name']    = trim($data['name']);
            if (isset($data['email']))   $u['email']   = strtolower(trim($data['email']));
            if (isset($data['phone']))   $u['phone']   = trim($data['phone']);
            if (isset($data['comment'])) $u['comment'] = trim($data['comment']);
            $u['updated_at'] = date('c');
            break;
        }
    }
    unset($u);

    saveUsers($users);
    respond(200, ['success' => true, 'message' => 'Данные профиля успешно обновлены.']);
}

// Метод не поддерживается
respond(405, ['success' => false, 'message' => 'Метод не поддерживается.']);