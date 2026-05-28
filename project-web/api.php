<?php
/**
 * api.php — REST веб-сервис для формы обратной связи
 */

// ─── Встроенная конфигурация БД ────────────────────────────────────────────────
$db_user = 'u82419';
$db_pass = '7111555';

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=$db_user;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД: ' . $e->getMessage()]);
    exit;
}

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
    if (empty($name)) {
        $errors['name'] = 'Имя обязательно.';
    } elseif (mb_strlen($name) > 128) {
        $errors['name'] = 'Имя не должно превышать 128 символов.';
    }

    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email.';
    } elseif (mb_strlen($email) > 128) {
        $errors['email'] = 'Email не должен превышать 128 символов.';
    }

    $phone = trim($data['phone'] ?? '');
    if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) {
        $errors['phone'] = 'Некорректный номер телефона.';
    }

    $comment = trim($data['comment'] ?? '');
    if (empty($comment)) {
        $errors['comment'] = 'Комментарий обязателен.';
    } elseif (mb_strlen($comment) > 65535) {
        $errors['comment'] = 'Комментарий слишком длинный.';
    }

    return $errors;
}

function generateLogin(): string {
    global $db;
    do {
        $login = substr(uniqid(), 0, 8);
        $stmt = $db->prepare("SELECT id FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

function generatePassword(): string {
    return substr(md5(rand()), 0, 8);
}

function authenticate(): ?array {
    global $db;
    
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Basic ') !== 0) return null;
    
    $decoded = base64_decode(substr($header, 6), true);
    if ($decoded === false) return null;
    
    $parts = explode(':', $decoded, 2);
    if (count($parts) !== 2) return null;
    
    [$login, $password] = $parts;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE login = ? LIMIT 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if (!$user) return null;
    
    if ($user['password'] === md5($password)) return $user;
    if (password_verify($password, $user['password'])) return $user;
    
    return null;
}

// ─── РОУТИНГ ─────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];

// GET - получение профиля (поддерживаем как с action=profile, так и без)
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Если есть Authorization header - пробуем вернуть профиль
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    if (!empty($auth_header) || $action === 'profile') {
        $user = authenticate();
        if (!$user) {
            respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
        }
        
        respond(200, [
            'success' => true,
            'profile' => [
                'login' => $user['login'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? '',
                'comment' => $user['comment'] ?? ''
            ]
        ]);
    }
    
    // Если нет авторизации - просто возвращаем информацию об API
    respond(200, [
        'success' => true,
        'message' => 'API работает. Используйте POST для отправки формы или PUT для обновления данных.',
        'endpoints' => [
            'POST' => 'Регистрация нового пользователя',
            'PUT' => 'Обновление данных (требуется Basic Auth)',
            'GET with Basic Auth' => 'Получение профиля'
        ]
    ]);
}

// POST - регистрация нового пользователя
if ($method === 'POST') {
    $input = getInput();
    $errors = validate($input);
    
    if (!empty($errors)) {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            http_response_code(422);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Ошибка валидации</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                .error-box { max-width: 500px; margin: 50px auto; background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 4px; }
            </style>
            </head>
            <body>
            <div class="error-box">
                <h2>Ошибки валидации:</h2>
                <ul>';
            foreach ($errors as $err) {
                echo '<li>' . htmlspecialchars($err) . '</li>';
            }
            echo '</ul>
                <a href="javascript:history.back()" class="btn">Вернуться</a>
            </div>
            </body>
            </html>';
            exit;
        }
        respond(422, ['success' => false, 'errors' => array_values($errors)]);
    }
    
    $login = generateLogin();
    $password = generatePassword();
    $passHash = md5($password);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO users (login, password, name, email, phone, comment) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $login,
            $passHash,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone'] ?? ''),
            trim($input['comment'])
        ]);
        
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Регистрация успешна</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                .success-box { max-width: 500px; margin: 50px auto; background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; }
                .credentials { background: white; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 4px; }
            </style>
            </head>
            <body>
            <div class="success-box">
                <h2>Заявка принята!</h2>
                <div class="credentials">
                    <p><strong>Логин:</strong> ' . htmlspecialchars($login) . '</p>
                    <p><strong>Пароль:</strong> ' . htmlspecialchars($password) . '</p>
                </div>
                <p>Сохраните эти данные — пароль больше не будет показан.</p>
                <a href="index.html" class="btn">На главную</a>
            </div>
            </body>
            </html>';
            exit;
        }
        
        respond(201, [
            'success' => true,
            'login' => $login,
            'password' => $password
        ]);
        
    } catch (PDOException $e) {
        error_log("DB ERROR: " . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка при сохранении данных: ' . $e->getMessage()]);
    }
}

// PUT - обновление данных авторизованного пользователя
if ($method === 'PUT') {
    $user = authenticate();
    if (!$user) {
        respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
    }
    
    $input = getInput();
    $errors = validate($input);
    
    if (!empty($errors)) {
        respond(422, ['success' => false, 'errors' => array_values($errors)]);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE users SET name = ?, email = ?, phone = ?, comment = ? WHERE id = ?
        ");
        $stmt->execute([
            trim($input['name']),
            trim($input['email']),
            trim($input['phone'] ?? ''),
            trim($input['comment']),
            $user['id']
        ]);
        
        respond(200, ['success' => true, 'message' => 'Данные успешно обновлены.']);
        
    } catch (PDOException $e) {
        error_log("DB ERROR: " . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка при обновлении данных.']);
    }
}

respond(405, ['success' => false, 'message' => 'Метод не поддерживается.']);
?>
