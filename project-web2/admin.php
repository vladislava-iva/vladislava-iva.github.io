<?php
declare(strict_types=1);

require_once 'db_config.php';

$adminLogin = 'admin';
$adminPassword = 'admin123';

if (
    !isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $adminLogin ||
    $_SERVER['PHP_AUTH_PW'] !== $adminPassword
) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Требуется авторизация');
}

if (isset($_GET['delete'])) {

    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([intval($_GET['delete'])]);

    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            name=?,
            email=?,
            phone=?,
            comment=?
        WHERE id=?
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['comment'],
        $_POST['id']
    ]);
}

$users = $pdo->query("
    SELECT * FROM users
    ORDER BY created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Админ-панель</title>

<style>
table{
    border-collapse: collapse;
    width:100%;
}
td,th{
    border:1px solid #ccc;
    padding:10px;
}
textarea{
    width:100%;
}
input{
    width:100%;
}
</style>
</head>
<body>

<h1>Админ-панель</h1>

<table>

<tr>
    <th>ID</th>
    <th>Логин</th>
    <th>Имя</th>
    <th>Email</th>
    <th>Телефон</th>
    <th>Комментарий</th>
    <th>Дата</th>
    <th>Действия</th>
</tr>

<?php foreach ($users as $user): ?>

<tr>

<form method="POST">

<td><?= $user['id'] ?></td>

<td><?= htmlspecialchars($user['login']) ?></td>

<td>
<input type="text" name="name"
value="<?= htmlspecialchars($user['name']) ?>">
</td>

<td>
<input type="email" name="email"
value="<?= htmlspecialchars($user['email']) ?>">
</td>

<td>
<input type="text" name="phone"
value="<?= htmlspecialchars($user['phone']) ?>">
</td>

<td>
<textarea name="comment"><?= htmlspecialchars($user['comment']) ?></textarea>
</td>

<td><?= $user['created_at'] ?></td>

<td>

<input type="hidden" name="id"
value="<?= $user['id'] ?>">

<button type="submit">Сохранить</button>

<a href="?delete=<?= $user['id'] ?>"
onclick="return confirm('Удалить пользователя?')">
Удалить
</a>

</td>

</form>

</tr>

<?php endforeach; ?>

</table>

</body>
</html>