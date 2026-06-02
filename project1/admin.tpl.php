<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 30px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .delete-btn { background: #ff0000; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px; }
        .edit-btn { background: #3498db; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px; text-decoration: none; }
        h1 { color: #333; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: none; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <h1> Админ-панель</h1>
    
    <div id="successMessage" class="success-message">
        ✅ Заявка успешно удалена!
    </div>
    
    <p>Все отправленные заявки:</p>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Логин</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($c['submissions'] as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['login']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <form method="POST" action="/vladislava-iva.github.io/project1/admin/<?= $row['id'] ?>" style="display:inline;">
                    <button type="submit" class="delete-btn" onclick="return confirm('Удалить заявку №<?= $row['id'] ?>?')"> Удалить</button>
                </form>
                <a href="/vladislava-iva.github.io/project1/profile/<?= $row['id'] ?>" class="edit-btn" style="background:#3498db; color:white; text-decoration:none; padding:5px 10px;"> Редактировать</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <p style="margin-top: 20px;"><a href="http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project/1.html">← На главную (форма)</a></p>
    
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('deleted') === '1') {
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