<?php
/**
 * api.php — REST веб-сервис для формы обратной связи.
 *
 * Единая точка входа. Маршрутизация по HTTP-методу:
 *   GET  /api.php                  — профиль авторизованного пользователя
 *   POST /api.php                  — регистрация нового пользователя
 *   PUT  /api.php                  — обновление данных (требует авторизации)
 *
 * Авторизация: HTTP Basic Auth (login:password в заголовке Authorization).
 */

declare(strict_types=1);

// ─── Настройки БД ─────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'u82419');
define('DB_USER', 'u82419');
define('DB_PASS', '7111555');
define('DB_CHARSET', 'utf8mb4');

// ─── Заголовки ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// Разрешаем CORS для локальной разработки (при необходимости уберите)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight-запрос браузера
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Подключение к БД ─────────────────────────────────────────────────────────
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ─── Создание таблицы при первом запуске ──────────────────────────────────────
function ensureTable(): void
{
    getDb()->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            login      VARCHAR(64)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            name       VARCHAR(128) NOT NULL,
            email      VARCHAR(128) NOT NULL,
            phone      VARCHAR(32)  DEFAULT '',
            comment    TEXT         DEFAULT '',
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ─── Утилиты ──────────────────────────────────────────────────────────────────

/** Отправляет JSON-ответ и завершает скрипт. */
function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Читает тело запроса как JSON (для POST/PUT с Content-Type: application/json).
 *  Для обычного POST (fallback без JS) читает $_POST. */
function getInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // Fallback: обычная HTML-форма без JavaScript
    return $_POST;
}

/** Валидирует входные данные формы. Возвращает массив ошибок (пустой — если всё ОК). */
function validate(array $data): array
{
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

/** Разбирает заголовок Authorization: Basic ... и возвращает ['login', 'password'] или null. */
function parseBasicAuth(): ?array
{
    // PHP может передавать заголовок по-разному в зависимости от конфигурации сервера
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';

    if (!str_starts_with($header, 'Basic ')) {
        return null;
    }

    $decoded = base64_decode(substr($header, 6), true);
    if ($decoded === false) return null;

    $parts = explode(':', $decoded, 2);
    return count($parts) === 2 ? $parts : null;
}

/** Ищет пользователя по логину и проверяет пароль. Возвращает строку из БД или null. */
function authenticate(): ?array
{
    $creds = parseBasicAuth();
    if ($creds === null) return null;

    [$login, $password] = $creds;

    $stmt = getDb()->prepare('SELECT * FROM users WHERE login = ? LIMIT 1');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user) return null;
    if (!password_verify($password, $user['password'])) return null;

    return $user;
}

/** Генерирует уникальный логин на основе имени. */
function generateLogin(string $name): string
{
    // Транслитерируем кириллицу → латиница
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];

    $lower = mb_strtolower($name);
    $latin = strtr($lower, $translit);
    $base  = preg_replace('/[^a-z0-9]/', '', $latin);
    $base  = $base ?: 'user';
    $base  = substr($base, 0, 20);

    // Убеждаемся в уникальности
    $login  = $base;
    $suffix = 1;
    $stmt   = getDb()->prepare('SELECT id FROM users WHERE login = ? LIMIT 1');

    while (true) {
        $stmt->execute([$login]);
        if (!$stmt->fetch()) break;
        $login = $base . $suffix;
        $suffix++;
    }

    return $login;
}

/** Генерирует случайный пароль. */
function generatePassword(int $length = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $pass  = '';
    $max   = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, $max)];
    }
    return $pass;
}

// ─── Основная логика ─────────────────────────────────────────────────────────

try {
    ensureTable();
} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'Ошибка подключения к базе данных.'], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: вернуть профиль авторизованного пользователя ────────────────────────
if ($method === 'GET') {
    $user = authenticate();
    if (!$user) {
        respond(['success' => false, 'message' => 'Требуется авторизация.'], 401);
    }

    respond([
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

// ── POST: регистрация нового пользователя ─────────────────────────────────────
if ($method === 'POST') {
    $input  = getInput();
    $errors = validate($input);

    if ($errors) {
        // Если запрос пришёл без JS (обычная форма) — выводим читаемый HTML
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            http_response_code(422);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>Ошибки валидации:</h2><ul>';
            foreach ($errors as $err) {
                echo '<li>' . htmlspecialchars($err) . '</li>';
            }
            echo '</ul><p><a href="javascript:history.back()">← Вернуться</a></p>';
            exit;
        }
        respond(['success' => false, 'errors' => $errors], 422);
    }

    $login    = generateLogin(trim($input['name']));
    $password = generatePassword();
    $hash     = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = getDb()->prepare(
            'INSERT INTO users (login, password, name, email, phone, comment)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $login,
            $hash,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']   ?? ''),
            trim($input['comment'] ?? ''),
        ]);
    } catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Ошибка при сохранении данных.'], 500);
    }

    // Fallback без JS: показываем HTML-страницу с логином и паролем
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains($contentType, 'application/json')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h2>✅ Заявка принята!</h2>';
        echo '<p><strong>Логин:</strong> ' . htmlspecialchars($login) . '</p>';
        echo '<p><strong>Пароль:</strong> ' . htmlspecialchars($password) . '</p>';
        echo '<p>⚠️ Сохраните эти данные — пароль больше не будет показан.</p>';
        echo '<p><a href="/">← На главную</a></p>';
        exit;
    }

    respond([
        'success'    => true,
        'login'      => $login,
        'password'   => $password,
        'profileUrl' => '/api.php',   // адрес для GET-запроса профиля
    ], 201);
}

// ── PUT: обновление данных авторизованного пользователя ──────────────────────
if ($method === 'PUT') {
    $user = authenticate();
    if (!$user) {
        respond(['success' => false, 'message' => 'Требуется авторизация.'], 401);
    }

    $input  = getInput();
    $errors = validate($input);

    if ($errors) {
        respond(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $stmt = getDb()->prepare(
            'UPDATE users SET name=?, email=?, phone=?, comment=? WHERE id=?'
        );
        $stmt->execute([
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']   ?? ''),
            trim($input['comment'] ?? ''),
            $user['id'],
        ]);
    } catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Ошибка при обновлении данных.'], 500);
    }

    respond(['success' => true, 'message' => 'Данные успешно обновлены.']);
}

// ── Остальные методы не поддерживаются ───────────────────────────────────────
respond(['success' => false, 'message' => 'Метод не поддерживается.'], 405);
