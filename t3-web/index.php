<?php
session_start();
//подключаемся к БД 
$db = new PDO(
  'mysql:host=localhost;dbname=u82419;charset=utf8mb4',
  'u82419',
  '7111555',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

//загружаем список языков из таблицы language
$langs = $db->query("SELECT id, name FROM language ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>задание 3</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <div class="container header-content">
    <div class="site-title">задание 3</div>
  </div>
</header>

<main>
  <div class="container content-wrapper">
    <section id="form-section">

      <?php if (!empty($_GET['save'])): ?>
        <p class="msg-success">данные сохранены</p>
      <?php endif; ?>

      <?php if (!empty($_GET['errors'])): ?>
        <?php
          if (!empty($_SESSION['form_errors'])):
        ?>
          <div class="msg-error">
            <?php foreach ($_SESSION['form_errors'] as $err): ?>
              <p> <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
          </div>
          <?php unset($_SESSION['form_errors']); ?>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="form.php">

        <div class="form-group">
          <label for="fio">фио</label>
          <input type="text" id="fio" name="fio" required>
        </div>

        <div class="form-group">
          <label for="phone">телефон</label>
          <input type="tel" id="phone" name="phone" required>
        </div>

        <div class="form-group">
          <label for="email">email</label>
          <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
          <label for="birth_date">дата рождения</label>
          <input type="date" id="birth_date" name="birth_date" required>
        </div>

        <fieldset>
          <legend>пол</legend>
          <label class="radio-label">
            <input type="radio" name="gender" value="муж" required> мужской
          </label>
          <label class="radio-label">
            <input type="radio" name="gender" value="жен"> женский
          </label>
        </fieldset>

        <div class="form-group">
          <label for="languages">любимые языки программирования<br>
          </label>
          <select id="languages" name="languages[]" multiple required>
            <?php foreach ($langs as $l): ?>
              <option value="<?= (int)$l['id'] ?>">
                <?= htmlspecialchars($l['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="biography">биография</label>
          <textarea id="biography" name="biography" required></textarea>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="contract_agreed" value="1" required>
            с контрактом ознакомлен(а)
          </label>
        </div>

        <button type="submit">сохранить</button>

      </form>
    </section>
  </div>
</main>

<footer>
  <div class="container">задание 3</div>
</footer>

</body>
</html>
