<?php
header('Content-Type: text/html; charset=UTF-8');

$errors = [];
$values = $_POST;

/* ================== ВАЛИДАЦИЯ ================== */

/* ФИО */
if (
    empty($_POST['fio']) ||
    mb_strlen($_POST['fio']) > 150 ||
    !preg_match('/^[А-Яа-яA-Za-z ]+$/u', $_POST['fio'])
) {
    $errors['fio'] = "ФИО должно содержать только буквы и пробелы и быть не длиннее 150 символов";
}

/* Телефон */
if (
    empty($_POST['phone']) ||
    !preg_match('/^\+?[0-9]{10,15}$/', $_POST['phone'])
) {
    $errors['phone'] = "Телефон должен содержать только цифры (допускается + в начале)";
}

/* Email */
if (
    empty($_POST['email']) ||
    !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)
) {
    $errors['email'] = "Некорректный email";
}

/* Дата рождения */
if (empty($_POST['birth_date'])) {
    $errors['birth_date'] = "Некорректная дата рождения";
}

/* Пол */
if (
    empty($_POST['gender']) ||
    !in_array($_POST['gender'], ['male','female'])
) {
    $errors['gender'] = "Некорректный пол";
}

/* Языки */
if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
    $errors['languages'] = "Не выбраны языки программирования";
}

/* Биография */
if (empty($_POST['biography'])) {
    $errors['biography'] = "Биография не заполнена";
}

/* Контракт */
if (empty($_POST['contract_agreed'])) {
    $errors['contract_agreed'] = "Необходимо согласие с контрактом";
}

/* ================== ЕСЛИ ЕСТЬ ОШИБКИ ================== */

if (!empty($errors)) {

    setcookie('errors', json_encode($errors), 0);
    setcookie('values', json_encode($values), 0);

    header('Location: index.php');
    exit();
}

/* ================== ЕСЛИ УСПЕХ ================== */

try {

    $db = new PDO(
        'mysql:host=localhost;dbname=web4sem;charset=utf8mb4',
        'webuser',
        'webpass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

// Генерируем фиктивные логин и пароль для совместимости с 5 лабой
$login = 'lab4_' . substr(md5(uniqid()), 0, 8);
$pass_hash = password_hash('temp', PASSWORD_DEFAULT);

$stmt = $db->prepare(
  "INSERT INTO application
  (name, phone, email, birthdate, gender, bio, contract, login, pass_hash)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->execute([
  $_POST['fio'],
  $_POST['phone'],
  $_POST['email'],
  $_POST['birth_date'],
  $_POST['gender'],
  $_POST['biography'],
  1,
  $login,
  $pass_hash
]);


    $appId = $db->lastInsertId();

    $stmt2 = $db->prepare(
        "INSERT INTO application_language
         (application_id, language_id)
         VALUES (?, ?)"
    );

    foreach ($_POST['languages'] as $lid) {
        $stmt2->execute([$appId, $lid]);
    }

} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

/* СОХРАНЯЕМ УСПЕШНЫЕ ДАННЫЕ НА 1 ГОД */
setcookie('values', json_encode($values), time() + 60*60*24*365);
setcookie('save', '1', time() + 60);

header('Location: index.php');
exit();