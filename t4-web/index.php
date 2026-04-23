<?php
header('Content-Type: text/html; charset=UTF-8');

$errors = [];
$values = [];
$messages = [];

if (!empty($_COOKIE['errors'])) {
    $errors = json_decode($_COOKIE['errors'], true);
    setcookie('errors', '', time() - 3600);
}

if (!empty($_COOKIE['values'])) {
    $values = json_decode($_COOKIE['values'], true);
}

if (!empty($_COOKIE['save'])) {
    $messages[] = "Данные успешно сохранены!";
    setcookie('save', '', time() - 3600);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Форма</title>
<link rel="stylesheet" href="styles.css">
<style>
.error {
    border: 2px solid red !important;
}
.message-success {
    color: green;
    margin-bottom: 15px;
}
.message-error {
    color: red;
    margin-bottom: 5px;
}
</style>
</head>
<body>

<header>
    <div class="container header-content">
        <h1 class="site-title">Лабораторная работа №4</h1>
    </div>
</header>

<main>
<div class="container content-wrapper">

<section id="form-section">
<h2>Форма заявки</h2>

<?php
if (!empty($messages)) {
    foreach ($messages as $message) {
        print('<div class="message-success">'.$message.'</div>');
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        print('<div class="message-error">'.$error.'</div>');
    }
}
?>

<form action="form.php" method="POST">

<div class="form-group">
<label>ФИО</label>
<input type="text" name="fio"
<?php if (!empty($errors['fio'])) print 'class="error"'; ?>
value="<?php print htmlspecialchars($values['fio'] ?? ''); ?>">
</div>

<div class="form-group">
<label>Телефон</label>
<input type="tel" name="phone"
<?php if (!empty($errors['phone'])) print 'class="error"'; ?>
value="<?php print htmlspecialchars($values['phone'] ?? ''); ?>">
</div>

<div class="form-group">
<label>Email</label>
<input type="email" name="email"
<?php if (!empty($errors['email'])) print 'class="error"'; ?>
value="<?php print htmlspecialchars($values['email'] ?? ''); ?>">
</div>

<div class="form-group">
<label>Дата рождения</label>
<input type="date" name="birth_date"
<?php if (!empty($errors['birth_date'])) print 'class="error"'; ?>
value="<?php print htmlspecialchars($values['birth_date'] ?? ''); ?>">
</div>

<fieldset>
<legend>Пол</legend>
<input type="radio" name="gender" value="male"
<?php if (($values['gender'] ?? '') == 'male') print 'checked'; ?>> Мужской
<br>
<input type="radio" name="gender" value="female"
<?php if (($values['gender'] ?? '') == 'female') print 'checked'; ?>> Женский
</fieldset>

<div class="form-group">
<label>Любимые языки программирования</label>
<select name="languages[]" multiple size="6"
<?php if (!empty($errors['languages'])) print 'class="error"'; ?>>
<?php
$languages = [
1 => "Pascal",
2 => "C",
3 => "C++",
4 => "JavaScript",
5 => "PHP",
6 => "Python",
7 => "Java",
8 => "Haskell",
9 => "Clojure",
10 => "Prolog",
11 => "Scala",
12 => "Go"
];

foreach ($languages as $id => $name) {
    $selected = (isset($values['languages']) && in_array($id, $values['languages'])) ? 'selected' : '';
    print "<option value=\"$id\" $selected>$name</option>";
}
?>
</select>
</div>

<div class="form-group">
<label>Биография</label>
<textarea name="biography"
<?php if (!empty($errors['biography'])) print 'class="error"'; ?>>
<?php print htmlspecialchars($values['biography'] ?? ''); ?>
</textarea>
</div>

<div class="form-group">
<input type="checkbox" name="contract_agreed" value="1"
<?php if (!empty($values['contract_agreed'])) print 'checked'; ?>>
<label>С контрактом ознакомлен</label>
</div>

<button type="submit">Сохранить</button>

</form>
</section>

</div>
</main>

<footer>
    <div class="container">
        © 2025 Лабораторная работа
    </div>
</footer>

</body>
</html>