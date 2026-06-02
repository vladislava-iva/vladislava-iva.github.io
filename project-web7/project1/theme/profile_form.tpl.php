<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование профиля</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        input, textarea, button { width: 100%; padding: 10px; margin: 10px 0; }
        button, .btn { background: #ff0000; color: white; border: none; cursor: pointer; border-radius: 5px; text-decoration: none; display: inline-block; text-align: center; }
        button:hover, .btn:hover { background: #cc0000; }
        .info { background: #d4edda; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .btn-back { display: inline-block; padding: 10px 20px; margin-top: 20px; margin-right: 10px; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: none; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <h1>Редактирование профиля</h1>
    
    <div id="successMessage" class="success-message">
        ✅ Данные успешно сохранены!
    </div>
    
    <div class="info">
        <strong>Ваш логин:</strong> <?= htmlspecialchars($c['profile']['login']) ?><br>
        <strong>ID профиля:</strong> <?= $c['profile']['id'] ?>
    </div>
    
    <form method="POST" action="http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web7/project1/profile/<?= $c['profile']['id'] ?>" id="profileForm">
        <input type="hidden" name="id" value="<?= htmlspecialchars($c['profile']['id']) ?>">
        <input type="text" name="name" value="<?= htmlspecialchars($c['profile']['name']) ?>" placeholder="Имя" required>
        <input type="tel" name="phone" value="<?= htmlspecialchars($c['profile']['phone']) ?>" placeholder="Телефон">
        <input type="email" name="email" value="<?= htmlspecialchars($c['profile']['email']) ?>" placeholder="E-mail" required>
        <textarea name="comment" placeholder="Комментарий"><?= htmlspecialchars($c['profile']['comment']) ?></textarea>
        <button type="submit">Сохранить изменения</button>
    </form>
    
    <a href="http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web7/project1/admin" class="btn btn-back">← Назад в админ-панель</a>
    <a href="http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web7/project/1.html" class="btn btn-back">← Вернуться на главную (форма)</a>

    <script>
        // Показываем сообщение об успехе, если сохранение прошло
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('saved') === '1') {
            const msg = document.getElementById('successMessage');
            if (msg) {
                msg.style.display = 'block';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 3000);
            }
        }
    </script>
</body>
</html>