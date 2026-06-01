<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма обратной связи</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h1>Форма обратной связи</h1>

    <?php if (!empty($messages)): ?>
        <div id="messages">
            <?php foreach ($messages as $message): ?>
                <?php echo $message; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">

        <div class="form-group">
            <label for="name">Имя *</label>
            <input type="text"
                   id="name"
                   name="name"
                   value="<?php echo htmlspecialchars($values['name'] ?? ''); ?>"
                   class="<?php echo !empty($errors['name']) ? 'error' : ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?php echo htmlspecialchars($values['email'] ?? ''); ?>"
                   class="<?php echo !empty($errors['email']) ? 'error' : ''; ?>">
        </div>

        <div class="form-group">
            <label for="phone">Телефон *</label>
            <input type="text"
                   id="phone"
                   name="phone"
                   value="<?php echo htmlspecialchars($values['phone'] ?? ''); ?>"
                   class="<?php echo !empty($errors['phone']) ? 'error' : ''; ?>">
            <small class="hint">Формат: +7 (999) 123-45-67, 10–11 цифр</small>
        </div>

        <div class="form-group">
            <label for="comment">Комментарий *</label>
            <textarea id="comment"
                      name="comment"
                      class="<?php echo !empty($errors['comment']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($values['comment'] ?? ''); ?></textarea>
            <small class="hint">От 4 до 65535 символов</small>
        </div>

        <div class="checkbox-group <?php echo !empty($errors['consent']) ? 'error' : ''; ?>">
            <input type="checkbox"
                   id="consent"
                   name="consent"
                   <?php echo (!empty($values['consent'])) ? 'checked' : ''; ?>>
            <label for="consent">Согласен(на) на обработку персональных данных *</label>
        </div>

        <button type="submit" class="submit-btn">Отправить</button>
    </form>
</div>
</body>
</html>