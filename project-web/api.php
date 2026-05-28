<?php
/**
 * api.php — Финальная версия для таблицы users
 */

$user = 'u82419';
$pass = '7111555';
$db = new PDO(
    "mysql:host=localhost;dbname=$user;charset=utf8mb4", 
    $user, 
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
    if (empty($name) || mb_strlen($name) > 128) {
        $errors[] = 'Имя обязательно (до 128 символов)';
    }

    $email = trim($data['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 128) {
        $errors[] = 'Укажите корректный email';
    }

    $comment = trim($data['comment'] ?? '');
    if (empty($comment)) {
        $errors[] = 'Комментарий обязателен';
    }

    return $errors;
}

// ==================== POST - НОВАЯ ЗАЯВКА ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();
    $errors = validate($input);

    if (!empty($errors)) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    $login = substr(uniqid(), 0, 8);
    $password = substr(md5(rand()), 0, 8);
    $passHash = md5($password);

    try {
        $stmt = $db->prepare("INSERT INTO users (login, password, name, email, phone, comment) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $login,
            $passHash,
            $input['name'],
            $input['email'],
            $input['phone'] ?? '',
            $input['comment']
        ]);

        respond(201, [
            'success' => true,
            'login' => $login,
            'password' => $password
        ]);

    } catch (PDOException $e) {
        error_log("INSERT ERROR: " . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = authenticate();
    if (!$user) respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);
    
    respond(200, ['success' => true, 'profile' => [
        'login' => $user['login'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'comment' => $user['comment']
    ]]);
}

if ($method === 'PUT') {
    $user = authenticate();
    if (!$user) respond(401, ['success' => false, 'message' => 'Требуется авторизация.']);

    $input = getInput();
    $errors = validate($input);
    if ($errors) respond(422, ['success' => false, 'errors' => $errors]);

    try {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, phone=?, comment=? WHERE id=?");
        $stmt->execute([$input['name'], $input['email'], $input['phone'] ?? '', $input['comment'], $user['id']]);
    } catch (PDOException $e) {
        respond(500, ['success' => false, 'message' => 'Ошибка обновления']);
    }

    respond(200, ['success' => true, 'message' => 'Данные обновлены']);
}

respond(405, ['success' => false, 'message' => 'Метод не поддерживается']);
?>
