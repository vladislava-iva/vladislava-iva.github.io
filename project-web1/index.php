<?php
// index.php - Главная страница Drupal лендинга
session_start();

// ========== ПОДКЛЮЧЕНИЕ К БД ==========
$host = 'localhost';
$dbname = 'u82419';
$user = 'u82419';
$pass = '7111555';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

// ========== ОБРАБОТКА AJAX ЗАПРОСОВ ==========
$action = $_GET['action'] ?? '';

// РЕГИСТРАЦИЯ (новая заявка)
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    // Валидация
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Имя обязательно';
    } elseif (mb_strlen($name) > 128) {
        $errors[] = 'Имя слишком длинное';
    }
    
    if (empty($email)) {
        $errors[] = 'Email обязателен';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    } elseif (mb_strlen($email) > 128) {
        $errors[] = 'Email слишком длинный';
    }
    
    if (empty($comment)) {
        $errors[] = 'Комментарий обязателен';
    } elseif (mb_strlen($comment) > 65535) {
        $errors[] = 'Комментарий слишком длинный';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    // Генерация логина и пароля
    do {
        $login = 'user_' . bin2hex(random_bytes(4));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    
    $password = bin2hex(random_bytes(4));
    $hash = md5($password);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (login, password, name, email, phone, comment, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$login, $hash, $name, $email, $phone, $comment]);
        
        echo json_encode([
            'success' => true,
            'login' => $login,
            'password' => $password
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении: ' . $e->getMessage()]);
    }
    exit;
}

// ПОЛУЧЕНИЕ ПРОФИЛЯ (для авторизованных)
if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Basic\s+(.*)$/i', $auth, $matches)) {
        $credentials = base64_decode($matches[1]);
        list($login, $password) = explode(':', $credentials, 2);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND password = ?");
        $stmt->execute([$login, md5($password)]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'profile' => [
                    'login' => $user['login'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'comment' => $user['comment'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    }
    exit;
}

// ОБНОВЛЕНИЕ ДАННЫХ
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Basic\s+(.*)$/i', $auth, $matches)) {
        echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
        exit;
    }
    
    $credentials = base64_decode($matches[1]);
    list($login, $password) = explode(':', $credentials, 2);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND password = ?");
    $stmt->execute([$login, md5($password)]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
        exit;
    }
    
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    $errors = [];
    if (empty($name)) $errors[] = 'Имя обязательно';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email обязателен';
    if (empty($comment)) $errors[] = 'Комментарий обязателен';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, comment = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $comment, $user['id']]);
        echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка обновления']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поддержка сайтов на Drupal</title>

    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>
    
    <link rel="stylesheet" href="отзывы.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <style>
        .auth-panel {
            background: #f8f9fa;
            border-left: 4px solid #6c5ce7;
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-message {
            padding: 10px;
            margin-top: 15px;
            border-radius: 4px;
        }
        .form-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .form-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .profile-block {
            margin-top: 12px;
            padding: 12px;
            background: #e9ecef;
            border-radius: 4px;
        }
        .btn-sm {
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header>
        <div class="video-background">
            <video autoplay muted loop playsinline>
                <source src="video.mp4" type="video/mp4">
            </video>
            <div class="video-overlay"></div>
        </div>
        
        <nav class="liens-section">
            <div class="mobile-logo"></div>
            <button class="navbar-toggler" id="mobileMenuButton">
                <span class="navbar-toggler-icon">☰</span>
            </button>
            <ul class="navbar-nav ms-auto" id="mainNav">
                <li class="nav-item"><a class="nav-link" href="#list-section">ПОДДЕРЖКА DRUPAL</a></li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link" id="adminDropdown">АДМИНИСТРИРОВАНИЕ ▼</a>
                    <div class="dropdown-menu" id="adminMenu">
                        <a class="dropdown-item" href="#">МИГРАЦИЯ</a>
                        <a class="dropdown-item" href="#">БЭКАПЫ</a>
                        <a class="dropdown-item" href="#">АУДИТ БЕЗОПАСНОСТИ</a>
                        <a class="dropdown-item" href="#">ОПТИМИЗАЦИЯ СКОРОСТИ</a>
                        <a class="dropdown-item" href="#">ПЕРЕЕЗД НА HTTPS</a>
                    </div>
                </li>
                <li class="nav-item"><a class="nav-link" href="#form-section">ПРОДВИЖЕНИЕ</a></li>
                <li class="nav-item"><a href="#form-section" class="nav-link">РЕКЛАМА</a></li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link" id="aboutDropdown">О НАС ▼</a>
                    <div class="dropdown-menu" id="aboutMenu">
                        <a class="dropdown-item" href="#">КОМАНДА</a>
                        <a class="dropdown-item" href="#">DRUPALGIVE</a>
                        <a class="dropdown-item" href="#">БЛОГ</a>
                        <a class="dropdown-item" href="#">КУРСЫ DRUPAL</a>
                    </div>
                </li>
                <li class="nav-item"><a href="#form-section" class="nav-link">ПРОЕКТЫ</a></li>
                <li class="nav-item"><a href="#form-section" class="nav-link">КОНТАКТЫ</a></li>
            </ul>
        </nav>
        
        <div class="header-container">
            <div class="container">
                <div class="row">
                    <div id="text-gauche" class="header-texte">
                        <h1>Поддержка <br> сайтов на Drupal</h1>
                        <p>Сопровождение и поддержка сайтов на любых версий и запущенности</p>
                        <button><a href="#tarif-section" class="tarif-link">ТАРИФЫ</a></button>
                    </div>
                    <div id="text-droit" class="header-texte text">
                        <div class="texte"><h3>#1<img id="cup" src="cup.png"></h3><p>Drupal-разработчик в России по версии Рейтинга Рунета</p></div>
                        <div class="texte"><h3>3+</h3><p>средний опыт специалистов более 3 лет</p></div>
                        <div class="texte"><h3>14</h3><p>лет опыта в сфере Drupal</p></div>
                        <div class="texte"><h3>50+</h3><p>модулей и тем в формате DrupalGive</p></div>
                        <div class="texte"><h3>90 000+</h3><p>часов поддержки сайтов на Drupal</p></div>
                        <div class="texte"><h3>300+</h3><p>Проектов на поддержке</p></div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <main>
        <section id="table-section" class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10 col-xl-8">
                        <div class="mb-4 col-lg-8 col-xs-6 px-0">
                            <h2 class="h4 text-start fw-bold mb-3" style="color: #333; font-size: 30px;">13 лет совершенствует компетенции в Друпал <br>поддержке!</h2>
                            <p class="text-start mb-0" style="color: #666; font-size: 14px; line-height: 1.5;">Разрабатываем и оптимизируем модули, расширяем функциональность сайтов, обновляем дизайн</p>
                        </div>
                        <div id="competention">
                            <div class="row">
                                <div id="competence1" class="col-sm-3 col-xs-6"><img class="i1" src="competency-1.svg"><p class="t1">Добавление информации на сайт, создание новых разделов</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-2.svg"><p class="t1">Разработка и оптимизация модулей сайта</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-3.svg"><p class="t1">Интеграция с CRM, 1C, платежными системами, любыми веб-сервисами</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-4.svg"><p class="t1">Любые доработки функционала и дизайна</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-5.svg"><p class="t1">Аудит и мониторинг безопасности Drupal сайтов</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-6.svg"><p class="t1">Миграция, импорт контента и апгрейд Drupal</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-7.svg"><p class="t1">Оптимизация и ускорение Drupal-сайтов</p></div>
                                <div class="col-sm-3 col-xs-6"><img class="i1" src="competency-8.svg"><p class="t1">Веб-маркетинг, консультации и работы по SEO</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10 col-xl-8">
                        <div class="mb-4"><h2 class="support-title">Поддержка от Drupal-coder</h2></div>
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">01.</span><div class="support-text"><h5>Постановка задачи по Email</h5><p>Удобная модель постановки задач, при которой задачи фиксируются и не теряются.</p></div><div class="support-img"><img src="support1.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">02.</span><div class="support-text"><h5>Система Helpdesk</h5><p>Просмотр всех заявок и отработанных часов через браузер.</p></div><div class="support-img"><img src="support2.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">03.</span><div class="support-text"><h5>Расширенная поддержка</h5><p>Поддержка с 6:00 до 22:00 без выходных.</p></div><div class="support-img"><img src="support3.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">04.</span><div class="support-text"><h5>Персональный менеджер</h5><p>Менеджер всегда в курсе состояния проекта.</p></div><div class="support-img"><img src="support4.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">05.</span><div class="support-text"><h5>Способы оплаты</h5><p>Безналичный расчет и электронные деньги.</p></div><div class="support-img"><img src="support5.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">06.</span><div class="support-text"><h5>SLA и NDA</h5><p>Работа в рамках соглашений качества и конфиденциальности.</p></div><div class="support-img"><img src="support6.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">07.</span><div class="support-text"><h5>Штатные специалисты</h5><p>Только проверенные специалисты, без фрилансеров.</p></div><div class="support-img"><img src="support7.svg" alt=""></div></div></div>
                            <div class="col-12 col-md-6 col-lg-3 mb-4"><div class="support-item"><span class="support-number">08.</span><div class="support-text"><h5>Каналы связи</h5><p>Телефон, Skype, мессенджеры.</p></div><div class="support-img"><img src="support8.svg" alt=""></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="forme-section">
            <div class="container">
                <div class="row">
                    <div class="col-12 col-md-6 image-col order-2 order-md-1"><img class="laptop img-fluid" src="laptop.png" alt="Drupal Expert"></div>
                    <div class="col-12 col-md-6 content-col order-1 order-md-2">
                        <h1 class="titre2">Экспертиза в Drupal, опыт 14 лет!</h1>
                        <div class="features">
                            <div class="test-container"><p class="mots">Только системный <br> подход – контроль <br> версий, резервирование <br> и тестирование!</p></div>
                            <div class="test-container special"><p class="mots">Только Drupal сайты, не <br> берем на поддержку сайты <br> на других CMS!</p></div>
                            <div class="test-container special"><p class="mots">Участвуем в разработке ядра <br> Drupal и модулей на <br> Drupal.org, разрабатываем <br> <a href="https://drupal-coder.ru/drupalgive" class="orange-line">свои модули Drupal</a></p></div>
                            <div class="test-container"><p class="mots">Поддерживаем сайты на <br> Drupal 5, 6, 7 и 8</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="tarif-section" id="tarif-section">
            <div class="tarif-tittle"><h2>Тарифы</h2></div>
            <div class="tarif-case">
                <div class="tarif-card"><h3>Стартовый</h3><ul><li>Консультации и работы по SEO</li><li>Услуги дизайнера</li><li>Неиспользованные оплаченные часы переносятся на следующий месяц</li><li>Предоплата от 6 000 рублей в месяц</li></ul><a href="#form-section" class="tarif-btn">СВЯЖИТЕСЬ С НАМИ!</a></div>
                <div class="tarif-card featured"><h3>Бизнес</h3><ul><li>Консультации и работы по SEO</li><li>Услуги дизайнера</li><li>Высокое время реакции — до 2 рабочих дней</li><li>Неиспользованные оплаченные часы не переносятся</li><li>Предоплата от 30 000 рублей в месяц</li></ul><a href="#form-section" class="tarif-btn">СВЯЖИТЕСЬ С НАМИ!</a></div>
                <div class="tarif-card"><h3>VIP</h3><ul><li>Консультации и работы по SEO</li><li>Услуги дизайнера</li><li>Максимальное время реакции — в день обращения</li><li>Неиспользованные оплаченные часы не переносятся</li><li>Предоплата от 270 000 рублей в месяц</li></ul><a href="#form-section" class="tarif-btn">СВЯЖИТЕСЬ С НАМИ!</a></div>
            </div>
            <div class="tarif-text"><p>Вам не подходят наши тарифы? Оставьте заявку и мы предложим вам индивидуальные условия!</p><a href="#individual-rate">ПОЛУЧИТЬ ИНДИВИДУАЛЬНЫЙ ТАРИФ</a></div>
        </section>

        <section class="services-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10 col-xl-8">
                        <div class="services-header mb-4 col-lg-8 col-xl-12 px-0"><h2 class="services-title h4 text-start fw-bold mb-3" style="color: #333; font-size: 35px;">Наши профессиональные разработки выполняют быстро любые задачи</h2></div>
                        <div id="services-list"><div class="services-grid row"><div class="service-card col-12 col-lg-4"><img class="service-icon" src="competency-20.svg" alt="Google Analytics Configuration"><h4 class="service-time">От 1ч</h4><p class="service-description">Настройка события GA в <br> интернет-магазине</p></div><div class="service-card col-12 col-lg-4"><img class="service-icon" src="competency-21.svg" alt="Mobile Website Development"><h4 class="service-time">От 20ч</h4><p class="service-description">Разработка мобильной <br> версии сайта</p></div><div class="service-card col-12 col-lg-4"><img class="service-icon" src="competency-22.svg" alt="Payment Module Integration"><h4 class="service-time">От 8ч</h4><p class="service-description">Интеграция <br> модуля оплаты</p></div></div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="team-section" class="py-5">
            <div class="container">
                <h2 class="text-center mb-5">Команда</h2>
                <div class="row team-members">
                    <div class="col-6 col-lg-4"><img class="team-img" src="img1.jpg" alt=""><h5 class="team-name">Сергей Синица</h5><p class="team-role">Руководитель отдела веб-разработки, канд. техн. наук, заместитель директора</p></div>
                    <div class="col-6 col-lg-4"><img class="team-img" src="img2.jpg" alt=""><h5 class="team-name">Роман Агабеков</h5><p class="team-role">Руководитель отдела DevOPS, директор</p></div>
                    <div class="col-6 col-lg-4"><img class="team-img" src="img3.jpg" alt=""><h5 class="team-name">Алексей Синица</h5><p class="team-role">Руководитель отдела поддержки сайтов</p></div>
                    <div class="col-6 col-lg-4"><img class="team-img" src="img4.jpg" alt=""><h5 class="team-name">Дарья Бочкарёва</h5><p class="team-role">Руководитель отдела продвижения, контекстной рекламы и контент‑поддержки сайтов</p></div>
                    <div class="col-6 col-lg-4"><img class="team-img" src="img5.jpg" alt=""><h5 class="team-name">Ирина Торкунова</h5><p class="team-role">Менеджер по работе с клиентами</p></div>
                    <div class="col-6 col-lg-4"><img class="team-img" src="img6.jpg" alt=""><h5 class="team-name">Алексей Зубенко</h5><p class="team-role">Web-разработчик</p></div>
                </div>
            </div>
        </section>

        <section class="cases-split">
            <h1>Последние кейсы</h1>
            <div class="two-columns">
                <div class="left-column">
                    <div class="img-top-left"><img src="p1.jpg" alt="Ноутбук"></div>
                    <div class="img-middle-left"><img src="p2.jpg" alt="Кэширование"></div>
                    <div class="img-bottom-left"><img src="p3.jpg" alt="АВ-тестирование"></div>
                </div>
                <div class="right-column">
                    <div class="top-right"><img src="p4.jpg" alt="Яндекс.Метрика"></div>
                    <div class="bottom-right">
                        <div class="bottom-right-left bottom-right-single"><img src="p5.jpg" alt="Drupal 7"></div>
                        <div class="bottom-right-left bottom-right-double">
                            <div class="small-top"><img src="p6.jpg" alt="HACK"></div>
                            <div class="small-bottom"><img src="p7.jpg" alt="Обмен товарами"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="reviews-container" aria-label="Отзывы клиентов" id="reviews">
            <h1 class="section-title">Отзывы</h1>
            <!-- Отзывы - оставьте как в вашем файле -->
        </section>

        <section class="clients-section">
            <div class="container">
                <h3 class="block-title">С нами работают</h3>
                <div class="view-header">Десятки компаний доверяют нам самое ценное, что у них есть в интернете – свои сайты. Мы делаем всё, чтобы наше сотрудничество было долгим.</div>
                <div class="clients-carousel" id="carousel1">
                    <div class="client-logo"><img src="kubs.jpg" alt="Кубанский университет"></div>
                    <div class="client-logo"><img src="gaz.jpg" alt="Газпром"></div>
                    <div class="client-logo"><img src="atom.jpg" alt="Росатом"></div>
                    <div class="client-logo"><img src="vtb.jpg" alt="ВТБ"></div>
                </div>
                <div class="clients-carousel" id="carousel2">
                    <div class="client-logo"><img src="kubs.jpg" alt="Кубанский университет"></div>
                    <div class="client-logo"><img src="gaz.jpg" alt="Газпром"></div>
                    <div class="client-logo"><img src="atom.jpg" alt="Росатом"></div>
                    <div class="client-logo"><img src="vtb.jpg" alt="ВТБ"></div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <h2 class="text-center mb-5">FAQ</h2>
                <div class="faq-list">
                    <!-- FAQ пункты - оставьте как в вашем файле -->
                </div>
            </div>
        </section>

        <section id="form-section">
            <div class="container">
                <div class="row">
                    <div id="text" class="col-12 col-md-6 mb-5 mb-md-0">
                        <div class="text1">Оставить заявку на<br> поддержку сайта</div>
                        <div class="text2">Срочно нужна поддержка сайта? Ваша команда не успевает<br> справиться самостоятельно или предыдущий подрядчик не<br>справился с работой? Тогда вам стоило к нам! Просто оставьте<br> заявку и наш менеджер с вами свяжется!</div>
                        <div class="contacts">
                            <ul class="ul">
                                <li class="phone"><a href="tel:88002222673" class="tel">8 800 222-26-73</a></li>
                                <li class="block-form-email"><a href="mailto:info@drupal-coder.ru" class="info" style="text-decoration: underline">info@drupal-coder.ru</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div id="form" class="col-12 col-md-6">
                        <noscript>
                            <p style="background:#fff3cd;border:1px solid #ffc107;padding:8px 12px;border-radius:4px;font-size:13px;">⚠️ JavaScript отключён. Форма будет отправлена стандартным способом.</p>
                        </noscript>

                        <div id="auth-panel-container"></div>

                        <form id="feedbackForm" method="POST" class="row g-3">
                            <div class="col-12"><input type="text" class="form-control" id="field-name-1" name="name" placeholder="Ваше имя" required></div>
                            <div class="col-12"><input type="tel" class="form-control" id="phone" name="phone" placeholder="Телефон"></div>
                            <div class="col-12"><input type="email" class="form-control" id="field-email" name="email" placeholder="E-mail" required></div>
                            <div class="col-12"><textarea class="form-control" id="field-name-2" name="comment" rows="4" placeholder="Ваш комментарий" required></textarea></div>
                            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="agree" name="agree" required><label class="form-check-label" for="agree">Отправляя заявку, я даю согласие на обработку своих персональных данных.</label></div></div>
                            <div class="col-12"><button id="submitBtn" type="submit" class="btn-submit">ОТПРАВИТЬ</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-purple text-white text-center py-4">
        <div class="container">
            <div class="divider"></div>
            <div class="social-icons mb-3">
                <a href="https://www.facebook.com/initlabkr/" class="text-white mx-2" target="_blank"><i class="fab fa-facebook-f fa-lg"></i></a>
                <a href="https://vk.com/initlab" class="text-white mx-2" target="_blank"><i class="fab fa-vk fa-lg"></i></a>
                <a href="https://teleg.one/initlabbot" class="text-white mx-2" target="_blank"><i class="fab fa-telegram fa-lg"></i></a>
                <a href="https://www.youtube.com/channel/UCyFEbngMB-bK2ulNtd_W43A" class="text-white mx-2" target="_blank"><i class="fab fa-youtube fa-lg"></i></a>
            </div>
            <div class="footer-text">
                <p class="mb-1">Проект ООО «Инитлаб», Краснодар, Россия.</p>
                <p class="mb-0">Drupal является зарегистрированной торговой маркой Dries Buytaert.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    
    <script>
    // ========== ОСНОВНОЙ СКРИПТ ДЛЯ ФОРМЫ ==========
    const API_URL = window.location.href.split('?')[0];
    
    function getAuthHeader() {
        const creds = sessionStorage.getItem('userCredentials');
        if (!creds) return null;
        return 'Basic ' + btoa(creds);
    }
    
    function showMessage(form, message, type) {
        const old = form.querySelector('.form-message');
        if (old) old.remove();
        const div = document.createElement('div');
        div.className = `form-message ${type}`;
        div.textContent = message;
        form.appendChild(div);
        setTimeout(() => div.remove(), 6000);
    }
    
    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    async function doLogin(login, password) {
        try {
            const res = await fetch(API_URL + '?action=profile', {
                headers: { 'Authorization': 'Basic ' + btoa(login + ':' + password) }
            });
            const data = await res.json();
            if (data.success && data.profile) {
                sessionStorage.setItem('userCredentials', login + ':' + password);
                renderAuthPanel();
                fillFormFromProfile(data.profile);
                showMessage(document.getElementById('feedbackForm'), '✅ Вход выполнен успешно!', 'success');
                return true;
            } else {
                alert('Неверный логин или пароль');
                return false;
            }
        } catch (e) {
            alert('Ошибка соединения');
            return false;
        }
    }
    
    function logout() {
        sessionStorage.removeItem('userCredentials');
        renderAuthPanel();
        document.getElementById('feedbackForm')?.reset();
        showMessage(document.getElementById('feedbackForm'), 'Вы вышли из системы', 'success');
    }
    
    async function loadProfile() {
        let profileBlock = document.getElementById('profile-block');
        if (!profileBlock) {
            const panel = document.getElementById('auth-panel');
            if (panel) {
                const div = document.createElement('div');
                div.id = 'profile-block';
                div.className = 'profile-block';
                div.style.display = 'none';
                panel.appendChild(div);
                profileBlock = div;
            }
        }
        if (profileBlock) {
            profileBlock.style.display = 'block';
            profileBlock.innerHTML = 'Загрузка...';
            try {
                const res = await fetch(API_URL + '?action=profile', {
                    headers: { 'Authorization': getAuthHeader() }
                });
                const data = await res.json();
                if (data.success && data.profile) {
                    const p = data.profile;
                    profileBlock.innerHTML = `<table style="width:100%"><tr><td><strong>Логин:</strong></td><td>${escapeHtml(p.login)}</td></tr><tr><td><strong>Имя:</strong></td><td>${escapeHtml(p.name)}</td></tr><tr><td><strong>Email:</strong></td><td>${escapeHtml(p.email)}</td></tr><tr><td><strong>Телефон:</strong></td><td>${escapeHtml(p.phone || '—')}</td></tr><tr><td><strong>Комментарий:</strong></td><td>${escapeHtml(p.comment || '—')}</td></tr></table>`;
                    fillFormFromProfile(p);
                } else {
                    profileBlock.innerHTML = 'Не удалось загрузить профиль';
                }
            } catch (e) {
                profileBlock.innerHTML = 'Ошибка загрузки';
            }
        }
    }
    
    function fillFormFromProfile(profile) {
        const nameInput = document.getElementById('field-name-1');
        const emailInput = document.getElementById('field-email');
        const phoneInput = document.getElementById('phone');
        const commentInput = document.getElementById('field-name-2');
        if (nameInput) nameInput.value = profile.name || '';
        if (emailInput) emailInput.value = profile.email || '';
        if (phoneInput) phoneInput.value = profile.phone || '';
        if (commentInput) commentInput.value = profile.comment || '';
    }
    
    function renderAuthPanel() {
        const container = document.getElementById('auth-panel-container');
        if (!container) return;
        const creds = sessionStorage.getItem('userCredentials');
        if (creds) {
            const login = creds.split(':')[0];
            container.innerHTML = `<div class="auth-panel" id="auth-panel"><div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;"><div><strong>👋 Вы вошли как:</strong> ${escapeHtml(login)}</div><div><button id="btn-show-profile" class="btn-sm" style="background:#6c5ce7;color:white;margin-right:8px;">📋 Мой профиль</button><button id="btn-logout" class="btn-sm" style="background:#dc3545;color:white;">🚪 Выйти</button></div></div><div id="profile-block" class="profile-block" style="display:none;"></div></div>`;
            document.getElementById('btn-logout')?.addEventListener('click', logout);
            document.getElementById('btn-show-profile')?.addEventListener('click', loadProfile);
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) submitBtn.textContent = '🔄 ОБНОВИТЬ ДАННЫЕ';
        } else {
            container.innerHTML = `<div class="auth-panel" id="auth-panel"><div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;"><div><strong>🔐 Уже есть аккаунт?</strong></div><button id="btn-show-login" class="btn-sm" style="background:#6c5ce7;color:white;">Войти</button></div><div id="login-form-inline" style="display:none;margin-top:12px;"><input id="il-login" type="text" placeholder="Логин" style="padding:8px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:150px;"><input id="il-pass" type="password" placeholder="Пароль" style="padding:8px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:150px;"><button id="btn-do-login" style="padding:8px 16px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;">OK</button><span id="il-error" style="color:red;margin-left:8px;"></span></div></div>`;
            document.getElementById('btn-show-login')?.addEventListener('click', () => { document.getElementById('login-form-inline').style.display = 'block'; });
            document.getElementById('btn-do-login')?.addEventListener('click', async () => {
                const login = document.getElementById('il-login').value.trim();
                const pass = document.getElementById('il-pass').value;
                const errEl = document.getElementById('il-error');
                if (!login || !pass) { errEl.textContent = 'Введите логин и пароль'; return; }
                await doLogin(login, pass);
            });
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) submitBtn.textContent = '📨 ОТПРАВИТЬ';
        }
    }
    
    class FeedbackForm {
        constructor() {
            this.form = document.getElementById('feedbackForm');
            this.submitBtn = document.getElementById('submitBtn');
            if (!this.form) return;
            this.init();
        }
        
        init() {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
            this.restoreFormData();
            this.form.addEventListener('input', () => this.saveFormData());
        }
        
        saveFormData() {
            const data = { name: document.getElementById('field-name-1')?.value || '', phone: document.getElementById('phone')?.value || '', email: document.getElementById('field-email')?.value || '', comment: document.getElementById('field-name-2')?.value || '' };
            try { localStorage.setItem('feedbackFormData', JSON.stringify(data)); } catch(e) {}
        }
        
        restoreFormData() {
            try {
                const saved = localStorage.getItem('feedbackFormData');
                if (!saved) return;
                const data = JSON.parse(saved);
                const nameInput = document.getElementById('field-name-1');
                if (nameInput && data.name) nameInput.value = data.name;
                const phoneInput = document.getElementById('phone');
                if (phoneInput && data.phone) phoneInput.value = data.phone;
                const emailInput = document.getElementById('field-email');
                if (emailInput && data.email) emailInput.value = data.email;
                const commentInput = document.getElementById('field-name-2');
                if (commentInput && data.comment) commentInput.value = data.comment;
            } catch(e) {}
        }
        
        collectData() {
            return { name: document.getElementById('field-name-1')?.value.trim() || '', phone: document.getElementById('phone')?.value.trim() || '', email: document.getElementById('field-email')?.value.trim() || '', comment: document.getElementById('field-name-2')?.value.trim() || '' };
        }
        
        async handleSubmit(e) {
            e.preventDefault();
            if (!this.form.checkValidity()) { showMessage(this.form, 'Пожалуйста, заполните все обязательные поля', 'error'); return; }
            const isLoggedIn = !!sessionStorage.getItem('userCredentials');
            const originalText = this.submitBtn?.textContent || 'Отправить';
            if (this.submitBtn) { this.submitBtn.disabled = true; this.submitBtn.textContent = 'Отправка...'; }
            try {
                const fd = new FormData();
                const data = this.collectData();
                fd.append('name', data.name); fd.append('phone', data.phone); fd.append('email', data.email); fd.append('comment', data.comment);
                const url = isLoggedIn ? API_URL + '?action=update' : API_URL + '?action=register';
                const headers = isLoggedIn ? { 'Authorization': getAuthHeader() } : {};
                const response = await fetch(url, { method: 'POST', headers, body: fd });
                const result = await response.json();
                if (result.success && result.login && result.password) {
                    showMessage(this.form, `✅ Заявка принята! Логин: ${result.login} | Пароль: ${result.password} — сохраните их!`, 'success');
                    sessionStorage.setItem('userCredentials', result.login + ':' + result.password);
                    renderAuthPanel();
                    this.form.reset();
                    try { localStorage.removeItem('feedbackFormData'); } catch(e) {}
                } else if (result.success) {
                    showMessage(this.form, '✅ Данные успешно обновлены!', 'success');
                    this.form.reset();
                    try { localStorage.removeItem('feedbackFormData'); } catch(e) {}
                } else if (result.errors) {
                    showMessage(this.form, '❌ ' + result.errors.join('. '), 'error');
                } else {
                    showMessage(this.form, '❌ ' + (result.message || 'Ошибка'), 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showMessage(this.form, '❌ Ошибка соединения с сервером', 'error');
            } finally {
                if (this.submitBtn) { this.submitBtn.disabled = false; this.submitBtn.textContent = originalText; }
            }
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        new FeedbackForm();
        renderAuthPanel();
    });
    if (sessionStorage.getItem('userCredentials')) { renderAuthPanel(); }
    
    // ========== КАРУСЕЛИ ==========
    $(document).ready(function(){
        function prepareCarousel($carousel) {
            var $slides = $carousel.find('.client-logo');
            var originalCount = $slides.length;
            if (originalCount < 8) {
                var clones = $slides.clone();
                $carousel.append(clones);
                if (originalCount < 4) { $carousel.append($slides.clone()); }
            }
        }
        var slickOptions = { autoplay: true, autoplaySpeed: 2500, pauseOnHover: false, arrows: false, centerMode: true, centerPadding: '10%', slidesToShow: 5, slidesToScroll: 1, infinite: true, speed: 600, cssEase: 'linear', responsive: [{ breakpoint: 1200, settings: { slidesToShow: 4, centerPadding: '8%' } }, { breakpoint: 992, settings: { slidesToShow: 3, centerPadding: '6%' } }, { breakpoint: 768, settings: { slidesToShow: 2, centerPadding: '10%' } }, { breakpoint: 480, settings: { slidesToShow: 1, centerPadding: '20%' } }] };
        if ($('#carousel1').length) { var $carousel1 = $('#carousel1'); prepareCarousel($carousel1); var options1 = $.extend({}, slickOptions); options1.autoplaySpeed = 2000; $carousel1.slick(options1); }
        if ($('#carousel2').length) { var $carousel2 = $('#carousel2'); prepareCarousel($carousel2); var options2 = $.extend({}, slickOptions); options2.autoplaySpeed = 2200; options2.rtl = false; $carousel2.slick(options2); setTimeout(function() { $carousel2.slick('slickNext'); }, 100); }
    });
    
    // ========== FAQ ==========
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const isVisible = answer.style.display === 'block';
            document.querySelectorAll('.faq-answer').forEach(ans => { ans.style.display = 'none'; });
            document.querySelectorAll('.faq-question').forEach(q => { q.classList.remove('active'); });
            if (!isVisible) { answer.style.display = 'block'; this.classList.add('active'); }
        });
    });
    
    // ========== СЛАЙДЕР ОТЗЫВОВ ==========
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.review-card');
        const prevBtns = document.querySelectorAll('.prev-btn');
        const nextBtns = document.querySelectorAll('.next-btn');
        const pageNumbers = document.querySelectorAll('.current-page');
        let currentIndex = 0;
        const total = cards.length;
        function updateSlider() {
            cards.forEach(card => card.classList.remove('active'));
            if (cards[currentIndex]) cards[currentIndex].classList.add('active');
            const pageNum = (currentIndex + 1).toString().padStart(2, '0');
            pageNumbers.forEach(el => el.textContent = pageNum);
        }
        prevBtns.forEach(btn => btn.addEventListener('click', function(e) { e.preventDefault(); currentIndex = (currentIndex - 1 + total) % total; updateSlider(); }));
        nextBtns.forEach(btn => btn.addEventListener('click', function(e) { e.preventDefault(); currentIndex = (currentIndex + 1) % total; updateSlider(); }));
        updateSlider();
    });
    
    // ========== МОБИЛЬНОЕ МЕНЮ ==========
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mainNav = document.getElementById('mainNav');
        if (mobileMenuButton && mainNav) {
            mobileMenuButton.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); mainNav.classList.toggle('mobile-open'); this.classList.toggle('active'); });
        }
        function setupDropdown(dropdownId, menuId) {
            const dropdown = document.getElementById(dropdownId);
            const menu = document.getElementById(menuId);
            if (dropdown && menu) {
                dropdown.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    if (window.innerWidth <= 768) {
                        this.classList.toggle('open');
                        menu.classList.toggle('show');
                        document.querySelectorAll('.dropdown-menu').forEach(m => { if (m !== menu && m.classList.contains('show')) { m.classList.remove('show'); const od = document.getElementById(m.id.replace('Menu', 'Dropdown')); if (od) od.classList.remove('open'); } });
                    } else {
                        document.querySelectorAll('.dropdown-menu.show').forEach(m => { if (m !== menu) m.classList.remove('show'); });
                        menu.classList.toggle('show');
                    }
                });
                menu.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', () => {
                        menu.classList.remove('show');
                        dropdown.classList.remove('open');
                        if (window.innerWidth <= 768 && mainNav) { mainNav.classList.remove('mobile-open'); if (mobileMenuButton) mobileMenuButton.classList.remove('active'); }
                    });
                });
            }
        }
        setupDropdown('adminDropdown', 'adminMenu');
        setupDropdown('aboutDropdown', 'aboutMenu');
        document.addEventListener('click', function(e) {
            if (mainNav && mobileMenuButton) { if (!mobileMenuButton.contains(e.target) && !mainNav.contains(e.target)) { mainNav.classList.remove('mobile-open'); mobileMenuButton.classList.remove('active'); } }
            if (window.innerWidth > 768) { document.querySelectorAll('.dropdown-menu.show').forEach(menu => { const dropdownId = menu.id.replace('Menu', 'Dropdown'); const dropdown = document.getElementById(dropdownId); if (dropdown && !dropdown.contains(e.target) && !menu.contains(e.target)) { menu.classList.remove('show'); } }); }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) { if (mainNav) mainNav.classList.remove('mobile-open'); if (mobileMenuButton) mobileMenuButton.classList.remove('active'); document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show')); document.querySelectorAll('.nav-link.open').forEach(l => l.classList.remove('open')); }
            else { if (mainNav) { mainNav.style.display = 'none !important'; mainNav.classList.remove('mobile-open'); } }
        });
        if (window.innerWidth <= 768 && mainNav) { mainNav.style.display = 'none !important'; }
    });
    </script>
</body>
</html>