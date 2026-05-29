<?php
declare(strict_types=1);

session_start();

require_once 'db_config.php';

header('Content-Type: application/json; charset=UTF-8');

function response(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    return $_POST;
}

function validate(array $data): array
{
    $errors = [];

    $name = trim($data['name'] ?? '');

    if (!$name) {
        $errors['name'] = 'Введите имя';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $name)) {
        $errors['name'] = 'Имя содержит недопустимые символы';
    } elseif (mb_strlen($name) > 150) {
        $errors['name'] = 'Имя слишком длинное';
    }

    $email = trim($data['email'] ?? '');

    if (!$email) {
        $errors['email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    } elseif (mb_strlen($email) > 100) {
        $errors['email'] = 'Email слишком длинный';
    }

    $phone = trim($data['phone'] ?? '');

    if (!$phone) {
        $errors['phone'] = 'Введите телефон';
    } else {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 10 || strlen($digits) > 11) {
            $errors['phone'] = 'Телефон должен содержать 10-11 цифр';
        }

        if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $errors['phone'] = 'Некорректный телефон';
        }
    }

    $comment = trim($data['comment'] ?? '');

    if (!$comment) {
        $errors['comment'] = 'Введите комментарий';
    } elseif (mb_strlen($comment) < 4) {
        $errors['comment'] = 'Комментарий слишком короткий';
    } elseif (mb_strlen($comment) > 65535) {
        $errors['comment'] = 'Комментарий слишком длинный';
    }

    if (empty($data['agree'])) {
        $errors['agree'] = 'Необходимо согласие';
    }

    return $errors;
}

function generateLogin(): string
{
    return substr(uniqid(), 0, 8);
}

function generatePassword(): string
{
    return substr(md5((string)rand()), 0, 8);
}

function getBasicUser(PDO $pdo): ?array
{
    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        !isset($_SERVER['PHP_AUTH_PW'])
    ) {
        return null;
    }

    $login = $_SERVER['PHP_AUTH_USER'];
    $password = md5($_SERVER['PHP_AUTH_PW']);

    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE login = ? AND password = ?
    ");

    $stmt->execute([$login, $password]);

    return $stmt->fetch() ?: null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $data = getInput();

    $errors = validate($data);

    if ($errors) {

        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

            echo '<h2>Ошибки:</h2><ul>';

            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }

            echo '</ul>';
            exit;
        }

        response([
            'success' => false,
            'errors' => $errors
        ], 422);
    }

    $login = generateLogin();
    $password = generatePassword();

    $stmt = $pdo->prepare("
        INSERT INTO users
        (login, password, name, email, phone, comment)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $login,
        md5($password),
        trim($data['name']),
        trim($data['email']),
        trim($data['phone']),
        trim($data['comment'])
    ]);

    $_SESSION['user_login'] = $login;

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

        echo "<h2>Регистрация успешна</h2>";
        echo "<p>Логин: <strong>$login</strong></p>";
        echo "<p>Пароль: <strong>$password</strong></p>";

        exit;
    }

    response([
        'success' => true,
        'login' => $login,
        'password' => $password
    ], 201);
}

if ($method === 'GET') {

    $user = getBasicUser($pdo);

    if (!$user) {

        header('WWW-Authenticate: Basic realm="API"');

        response([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    unset($user['password']);

    response([
        'success' => true,
        'user' => $user
    ]);
}

if ($method === 'PUT') {

    $user = getBasicUser($pdo);

    if (!$user) {

        header('WWW-Authenticate: Basic realm="API"');

        response([
            'success' => false
        ], 401);
    }

    parse_str(file_get_contents('php://input'), $putData);

    $errors = validate($putData);

    if ($errors) {
        response([
            'success' => false,
            'errors' => $errors
        ], 422);
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            name = ?,
            email = ?,
            phone = ?,
            comment = ?
        WHERE id = ?
    ");

    $stmt->execute([
        trim($putData['name']),
        trim($putData['email']),
        trim($putData['phone']),
        trim($putData['comment']),
        $user['id']
    ]);

    response([
        'success' => true,
        'message' => 'Данные обновлены'
    ]);
}

response([
    'success' => false,
    'message' => 'Метод не поддерживается'
], 405);