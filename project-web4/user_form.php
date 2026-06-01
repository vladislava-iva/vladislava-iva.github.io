<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = array();

    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', 100000);
        $messages[] = '<div class="success-message">Спасибо, ваши данные сохранены.</div>';
    }

    $errors = array();
    $errors['name']    = !empty($_COOKIE['name_error']);
    $errors['email']   = !empty($_COOKIE['email_error']);
    $errors['phone']   = !empty($_COOKIE['phone_error']);
    $errors['comment'] = !empty($_COOKIE['comment_error']);
    $errors['consent'] = !empty($_COOKIE['consent_error']);

    if ($errors['name']) {
        setcookie('name_error', '', 100000);
        setcookie('name_value', '', 100000);
        $messages[] = '<div class="error-message">Имя должно содержать только буквы, пробелы и дефисы. Длина не более 150 символов.</div>';
    }

    if ($errors['email']) {
        setcookie('email_error', '', 100000);
        setcookie('email_value', '', 100000);
        $messages[] = '<div class="error-message">Email должен быть корректным (например: name@domain.com). Длина не более 100 символов. Email уже может быть занят.</div>';
    }

    if ($errors['phone']) {
        setcookie('phone_error', '', 100000);
        setcookie('phone_value', '', 100000);
        $messages[] = '<div class="error-message">Телефон может содержать только цифры, пробелы, дефисы, скобки и символ +. Должен содержать 10 или 11 цифр.</div>';
    }

    if ($errors['comment']) {
        setcookie('comment_error', '', 100000);
        setcookie('comment_value', '', 100000);
        $messages[] = '<div class="error-message">Комментарий должен содержать минимум 4 символа и не более 65535 символов.</div>';
    }

    if ($errors['consent']) {
        setcookie('consent_error', '', 100000);
        setcookie('consent_value', '', 100000);
        $messages[] = '<div class="error-message">Необходимо подтвердить согласие на обработку данных.</div>';
    }

    $values = array();
    $values['name']    = isset($_COOKIE['name_value'])    ? htmlspecialchars($_COOKIE['name_value'])    : '';
    $values['email']   = isset($_COOKIE['email_value'])   ? htmlspecialchars($_COOKIE['email_value'])   : '';
    $values['phone']   = isset($_COOKIE['phone_value'])   ? htmlspecialchars($_COOKIE['phone_value'])   : '';
    $values['comment'] = isset($_COOKIE['comment_value']) ? htmlspecialchars($_COOKIE['comment_value']) : '';
    $values['consent'] = !empty($_COOKIE['consent_value']);

    include('user_form_view.php');

} else {
    $errors = FALSE;

    // --- name ---
    if (empty($_POST['name'])) {
        setcookie('name_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $name = $_POST['name'];
        if (strlen($name) > 150) {
            setcookie('name_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $name)) {
            setcookie('name_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('name_value', $_POST['name'] ?? '', time() + 365 * 24 * 60 * 60);

    // --- email ---
    $emailError = false;
    if (empty($_POST['email'])) {
        $emailError = true;
    } else {
        $email = $_POST['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailError = true;
        } elseif (strlen($email) > 100) {
            $emailError = true;
        } else {
            // уникальность
            try {
                $stmt = $db->prepare("SELECT id FROM table1 WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $emailError = true;
                }
            } catch (PDOException $e) {
                $emailError = true;
            }
        }
    }
    if ($emailError) {
        setcookie('email_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('email_value', $_POST['email'] ?? '', time() + 365 * 24 * 60 * 60);

    // --- phone ---
    if (empty($_POST['phone'])) {
        setcookie('phone_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $phone = $_POST['phone'];
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($phone) > 20) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('phone_value', $_POST['phone'] ?? '', time() + 365 * 24 * 60 * 60);

    // --- comment ---
    if (empty($_POST['comment'])) {
        setcookie('comment_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $comment = trim($_POST['comment']);
        if ($comment === '' || strlen($comment) < 4 || strlen($comment) > 65535) {
            setcookie('comment_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('comment_value', $_POST['comment'] ?? '', time() + 365 * 24 * 60 * 60);

    // --- consent ---
    if (!isset($_POST['consent']) || $_POST['consent'] !== 'on') {
        setcookie('consent_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('consent_value', isset($_POST['consent']) ? '1' : '', time() + 365 * 24 * 60 * 60);

    if ($errors) {
        header('Location: user_form.php');
        exit();
    }

    // Очищаем куки ошибок
    setcookie('name_error',    '', 100000);
    setcookie('email_error',   '', 100000);
    setcookie('phone_error',   '', 100000);
    setcookie('comment_error', '', 100000);
    setcookie('consent_error', '', 100000);

    try {
        $stmt = $db->prepare(
            "INSERT INTO table1 (name, email, phone, comment, consent) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            trim($_POST['comment']),
            1
        ]);

        setcookie('save', '1');
        header('Location: user_form.php');
        exit();

    } catch (PDOException $e) {
        print('Ошибка базы данных: ' . $e->getMessage());
        exit();
    }
}