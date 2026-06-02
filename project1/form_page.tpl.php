<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>API Форма</title>
    <style>
        body { font-family: Arial; max-width: 500px; margin: 50px auto; padding: 20px; }
        input, textarea, button { width: 100%; padding: 10px; margin: 10px 0; }
        button { background: #ff0000; color: white; border: none; cursor: pointer; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Отправить заявку</h1>
    <form id="apiForm">
        <input type="text" name="field-name-1" placeholder="Ваше имя" required>
        <input type="tel" name="phone" placeholder="Телефон">
        <input type="email" name="field-email" placeholder="E-mail" required>
        <textarea name="field-name-2" placeholder="Комментарий"></textarea>
        <label>
            <input type="checkbox" name="agree" required> Согласие на обработку данных
        </label>
        <button type="submit">Отправить</button>
    </form>
    <div id="result"></div>

    <script>
        document.getElementById('apiForm').onsubmit = async function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            const res = await fetch('http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project1/', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            const resultDiv = document.getElementById('result');
            if (result.success) {
                resultDiv.innerHTML = `<div class="success"> Данные сохранены!<br>Логин: ${result.login}<br>Пароль: ${result.password}<br><a href="${result.profile_url}">Редактировать профиль</a></div>`;
                form.reset();
            } else {
                resultDiv.innerHTML = `<div class="error"> Ошибка: ${JSON.stringify(result.errors)}</div>`;
            }
        };
    </script>
</body>
</html>