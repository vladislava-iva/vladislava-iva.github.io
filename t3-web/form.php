<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

$errors = [];

try {
  $db = new PDO(
    'mysql:host=localhost;dbname=web4sem;charset=utf8mb4',
    'webuser',
    'webpass',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  die("ошибка подключения к БД: " . $e->getMessage());
}

if (
    empty($_POST['fio']) ||
    mb_strlen($_POST['fio']) > 150 ||
    !preg_match('/^[А-Яа-яЁёA-Za-z ]+$/u', $_POST['fio'])
) {
    $errors[] = 'фио должно содержать только буквы и пробелы и быть не длиннее 150 символов';
}

if (
    empty($_POST['phone']) ||
    !preg_match('/^\+?[0-9]{10,15}$/', trim($_POST['phone']))
) {
    $errors[] = 'телефон должен содержать только цифры и состоять из 10–15 символов';
}

if (
    empty($_POST['email']) ||
    !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)
) {
    $errors[] = 'некорректный адрес электронной почты';
}

if (empty($_POST['birth_date'])) {
    $errors[] = 'дата рождения не заполнена';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $_POST['birth_date']);
    if (!$d) {
        $errors[] = 'некорректная дата рождения';
    }
}

if (
    empty($_POST['gender']) ||
    !in_array($_POST['gender'], ['муж', 'жен'], true)
) {
    $errors[] = 'некорректное значение пола';
}

if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
    $errors[] = 'необходимо выбрать хотя бы один язык программирования';
} else {
    
    $validIds = $db->query("SELECT id FROM language")->fetchAll(PDO::FETCH_COLUMN);
    $validIds = array_map('intval', $validIds);

    foreach ($_POST['languages'] as $lid) {
        if (!in_array((int)$lid, $validIds, true)) {
            $errors[] = 'выбран недопустимый язык программирования';
            break;
        }
    }
}

if (empty(trim($_POST['biography'] ?? ''))) {
    $errors[] = 'биография не заполнена';
}

if (empty($_POST['contract_agreed'])) {
    $errors[] = 'необходимо подтвердить ознакомление с контрактом';
}

//если есть ошибки , то возвращаем на форму
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    header('Location: index.php?errors=1');
    exit;
}

//запись в БД 
try {
    $stmt = $db->prepare(
        "INSERT INTO application
         (name, phone, email, birthdate, gender, bio, contract)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        trim($_POST['fio']),
        trim($_POST['phone']),
        trim($_POST['email']),
        $_POST['birth_date'],
        $_POST['gender'],
        trim($_POST['biography']),
        1
    ]);

    $appId = (int)$db->lastInsertId();

    $stmt2 = $db->prepare(
        "INSERT INTO application_language (app_id, lang_id) VALUES (?, ?)"
    );
    foreach ($_POST['languages'] as $lid) {
        $stmt2->execute([$appId, (int)$lid]);
    }

} catch (PDOException $e) {
    $_SESSION['form_errors'] = ['ошибка при сохранении данных'];
    // error_log('DB error: ' . $e->getMessage());
    header('Location: index.php?errors=1');
    exit;
}

header('Location: index.php?save=1');
exit;
