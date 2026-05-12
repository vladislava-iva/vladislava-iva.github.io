<?php

header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';

if (empty($_COOKIE[session_name()])) {
    session_start();
}
if (!empty($_COOKIE[session_name()])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = array();

    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', 100000);
        setcookie('login', '', 100000);
        setcookie('pass', '', 100000);

        $messages[] = '<div class="success-message">Спасибо, результаты сохранены.</div>';

        if (!empty($_COOKIE['pass'])) {
            $messages[] = sprintf(
                '<div class="info-message">Вы можете <a href="login.php">войти</a> с логином <strong>%s</strong> и паролем <strong>%s</strong> для изменения данных.</div>',
                htmlspecialchars($_COOKIE['login'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($_COOKIE['pass'], ENT_QUOTES, 'UTF-8')
            );
        }
    }

    $errors = array();
    $errors['fullName']  = !empty($_COOKIE['fullName_error']);
    $errors['email']     = !empty($_COOKIE['email_error']);
    $errors['phone']     = !empty($_COOKIE['phone_error']);
    $errors['birthdate'] = !empty($_COOKIE['birthdate_error']);
    $errors['gender']    = !empty($_COOKIE['gender_error']);
    $errors['languages'] = !empty($_COOKIE['languages_error']);
    $errors['message']   = !empty($_COOKIE['message_error']);
    $errors['contract']  = !empty($_COOKIE['contract_error']);

    if ($errors['fullName'])  { setcookie('fullName_error', '', 100000); setcookie('fullName_value', '', 100000);
        $messages[] = '<div class="error-message">ФИО должно содержать только буквы, пробелы и дефисы. Длина не более 150 символов.</div>'; }
    if ($errors['email'])     { setcookie('email_error', '', 100000); setcookie('email_value', '', 100000);
        $messages[] = '<div class="error-message">Email должен быть корректным (например: name@domain.com). Длина не более 100 символов.</div>'; }
    if ($errors['phone'])     { setcookie('phone_error', '', 100000); setcookie('phone_value', '', 100000);
        $messages[] = '<div class="error-message">Телефон может содержать только цифры, пробелы, дефисы, скобки и символ +. Должен содержать 10 или 11 цифр.</div>'; }
    if ($errors['birthdate']) { setcookie('birthdate_error', '', 100000); setcookie('birthdate_value', '', 100000);
        $messages[] = '<div class="error-message">Дата рождения должна быть корректной в формате ГГГГ-ММ-ДД.</div>'; }
    if ($errors['gender'])    { setcookie('gender_error', '', 100000); setcookie('gender_value', '', 100000);
        $messages[] = '<div class="error-message">Выберите пол (Мужской или Женский).</div>'; }
    if ($errors['languages']) { setcookie('languages_error', '', 100000); setcookie('languages_value', '', 100000);
        $messages[] = '<div class="error-message">Выберите хотя бы один язык программирования из списка.</div>'; }
    if ($errors['message'])   { setcookie('message_error', '', 100000); setcookie('message_value', '', 100000);
        $messages[] = '<div class="error-message">Биография должна содержать минимум 4 символа и не более 65535 символов.</div>'; }
    if ($errors['contract'])  { setcookie('contract_error', '', 100000); setcookie('contract_value', '', 100000);
        $messages[] = '<div class="error-message">Необходимо подтвердить ознакомление с контрактом.</div>'; }

    $values = array();
    $values['fullName']  = isset($_COOKIE['fullName_value'])  ? htmlspecialchars($_COOKIE['fullName_value'], ENT_QUOTES, 'UTF-8')  : '';
    $values['email']     = isset($_COOKIE['email_value'])     ? htmlspecialchars($_COOKIE['email_value'], ENT_QUOTES, 'UTF-8')     : '';
    $values['phone']     = isset($_COOKIE['phone_value'])     ? htmlspecialchars($_COOKIE['phone_value'], ENT_QUOTES, 'UTF-8')     : '';
    $values['birthdate'] = isset($_COOKIE['birthdate_value']) ? htmlspecialchars($_COOKIE['birthdate_value'], ENT_QUOTES, 'UTF-8') : '';
    $values['gender']    = isset($_COOKIE['gender_value'])    ? htmlspecialchars($_COOKIE['gender_value'], ENT_QUOTES, 'UTF-8')    : '';
    $values['languages'] = isset($_COOKIE['languages_value']) ? explode(',', $_COOKIE['languages_value']) : [];
    $values['message']   = isset($_COOKIE['message_value'])   ? htmlspecialchars($_COOKIE['message_value'], ENT_QUOTES, 'UTF-8')   : '';
    $values['contract']  = !empty($_COOKIE['contract_value']);

    if (empty($errors) && !empty($_SESSION['login'])) {
        try {
            $stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
            $stmt->execute([$_SESSION['uid']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userData) {
                $values['fullName']  = htmlspecialchars($userData['fio'], ENT_QUOTES, 'UTF-8');
                $values['email']     = htmlspecialchars($userData['email'], ENT_QUOTES, 'UTF-8');
                $values['phone']     = htmlspecialchars($userData['phone'], ENT_QUOTES, 'UTF-8');
                $values['birthdate'] = htmlspecialchars($userData['birth_date'], ENT_QUOTES, 'UTF-8');
                $values['gender']    = htmlspecialchars($userData['gender'], ENT_QUOTES, 'UTF-8');
                $values['message']   = htmlspecialchars($userData['bio'], ENT_QUOTES, 'UTF-8');
                $values['contract']  = (bool)$userData['contract'];

                $langStmt = $db->prepare(
                    "SELECT l.code FROM languages l
                     JOIN app_languages al ON l.id = al.lang_id
                     WHERE al.app_id = ?"
                );
                $langStmt->execute([$_SESSION['uid']]);
                $values['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);

                $messages[] = '<div class="success-message">Вы вошли как ' . htmlspecialchars($_SESSION['login'], ENT_QUOTES, 'UTF-8') . '. Можете изменить данные.</div>';
            }
        } catch (PDOException $e) {
            $messages[] = '<div class="error-message">Произошла ошибка при загрузке данных. Попробуйте позже.</div>';
            error_log('DB error in index.php GET: ' . $e->getMessage());
        }
    }

    include('form.php');
} else {
    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        http_response_code(403);
        die('Ошибка: недействительный CSRF-токен. Пожалуйста, обновите страницу и попробуйте снова.');
    }

    $errors = FALSE;

    if (empty($_POST['fullName'])) {
        setcookie('fullName_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $fullName = $_POST['fullName'];
        if (strlen($fullName) > 150 || !preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $fullName)) {
            setcookie('fullName_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('fullName_value', $_POST['fullName'] ?? '', time() + 365 * 24 * 60 * 60);

    if (empty($_POST['email'])) {
        setcookie('email_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $email = $_POST['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            setcookie('email_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('email_value', $_POST['email'] ?? '', time() + 365 * 24 * 60 * 60);

    if (empty($_POST['phone'])) {
        setcookie('phone_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $phone = $_POST['phone'];
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone) || strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11 || strlen($phone) > 20) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('phone_value', $_POST['phone'] ?? '', time() + 365 * 24 * 60 * 60);

    if (empty($_POST['birthdate'])) {
        setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        if (!$date || $date->format('Y-m-d') !== $_POST['birthdate']) {
            setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('birthdate_value', $_POST['birthdate'] ?? '', time() + 365 * 24 * 60 * 60);

    if (empty($_POST['gender']) || !in_array($_POST['gender'], ['male', 'female'])) {
        setcookie('gender_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('gender_value', $_POST['gender'] ?? '', time() + 365 * 24 * 60 * 60);

    $allowed_langs = ['pascal','c','cpp','javascript','php','python','java','haskell','clojure','prolog','scala','go'];
    if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
        setcookie('languages_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        foreach ($_POST['languages'] as $lang) {
            if (!in_array($lang, $allowed_langs, true)) {
                setcookie('languages_error', '1', time() + 24 * 60 * 60);
                $errors = TRUE;
                break;
            }
        }
    }
    if (!empty($_POST['languages']) && is_array($_POST['languages'])) {
        setcookie('languages_value', implode(',', $_POST['languages']), time() + 365 * 24 * 60 * 60);
    }

    if (empty($_POST['message'])) {
        setcookie('message_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $msg = $_POST['message'];
        if (strlen($msg) < 4 || strlen($msg) > 65535) {
            setcookie('message_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('message_value', $_POST['message'] ?? '', time() + 365 * 24 * 60 * 60);

    if (!isset($_POST['contract']) || $_POST['contract'] !== 'on') {
        setcookie('contract_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('contract_value', isset($_POST['contract']) ? '1' : '', time() + 365 * 24 * 60 * 60);

    if ($errors) {
        header('Location: index.php');
        exit();
    }

    setcookie('fullName_error', '', 100000);
    setcookie('email_error', '', 100000);
    setcookie('phone_error', '', 100000);
    setcookie('birthdate_error', '', 100000);
    setcookie('gender_error', '', 100000);
    setcookie('languages_error', '', 100000);
    setcookie('message_error', '', 100000);
    setcookie('contract_error', '', 100000);

    $isAuthenticated = !empty($_SESSION['login']);

    if ($isAuthenticated) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "UPDATE application SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract = ? WHERE id = ?"
            );
            $stmt->execute([
                $_POST['fullName'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['message'],
                $_POST['contract'] === 'on' ? 1 : 0,
                $_SESSION['uid']
            ]);

            $delStmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
            $delStmt->execute([$_SESSION['uid']]);

            $lang_map = [];
            $langStmt = $db->query("SELECT id, code FROM languages");
            while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['code']] = $row['id'];
            }

            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang) {
                if (isset($lang_map[$lang])) {
                    $insertLang->execute([$_SESSION['uid'], $lang_map[$lang]]);
                }
            }

            $db->commit();
            setcookie('save', '1');
            header('Location: index.php');
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            error_log('DB error in index.php POST (update): ' . $e->getMessage());
            die('Произошла ошибка при сохранении данных. Попробуйте позже.');
        }
    } else {
        $login    = substr(uniqid(), 0, 8);
        $pass     = substr(md5(random_bytes(16)), 0, 8);
        $passHash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "INSERT INTO application (fio, phone, email, birth_date, gender, bio, contract) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_POST['fullName'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['message'],
                $_POST['contract'] === 'on' ? 1 : 0
            ]);

            $app_id = $db->lastInsertId();

            $updateStmt = $db->prepare("UPDATE application SET login = ?, pass_hash = ? WHERE id = ?");
            $updateStmt->execute([$login, $passHash, $app_id]);

            $lang_map = [];
            $langStmt = $db->query("SELECT id, code FROM languages");
            while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['code']] = $row['id'];
            }

            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang) {
                if (isset($lang_map[$lang])) {
                    $insertLang->execute([$app_id, $lang_map[$lang]]);
                }
            }

            $db->commit();

            setcookie('login', $login);
            setcookie('pass', $pass);
            setcookie('save', '1');

            header('Location: index.php');
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            error_log('DB error in index.php POST (insert): ' . $e->getMessage());
            die('Произошла ошибка при сохранении данных. Попробуйте позже.');
        }
    }
}