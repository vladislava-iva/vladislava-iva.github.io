<?php
/**
 * api.php — РАБОЧАЯ ВЕРСИЯ для таблицы users
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

// ==================== POST — ОСНОВНОЙ ОБРАБОТЧИК ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();
    $errors = validate($input);

    if (!empty($errors)) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    $login = generateLogin();
    $password = generatePassword();
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
        error_log("DB ERROR: " . $e->getMessage());
        respond(500, ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
}

respond(405, ['success' => false, 'message' => 'Метод не поддерживается']);
?>
