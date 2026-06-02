<?php

// Выключаем отображение ошибок после отладки.
define('DISPLAY_ERRORS', 1);

// По возможности кладём скрипты и включаемые файлы выше
// публично доступной директории из соображений безопасности.

// Папки со скриптами и модулями.
define('INCLUDE_PATH', './scripts' . PATH_SEPARATOR . './modules');

// Храним настройки в массиве чтоб легче было смотреть (print_r),
// хранить (serialize), оверрайдить и не плодить глобалов.
$conf = array(
  'sitename' => 'Demo Framework',
  'theme' => './theme',
  'charset' => 'UTF-8',
  'clean_urls' => TRUE,
  'display_errors' => 1,
  'date_format' => 'Y.m.d',
  'date_format_2' => 'Y.m.d H:i',
  'date_format_3' => 'd.m.Y',
  'basedir' => '/',
  'login' => 'admin',
  'password' => '123',
  'admin_mail' => 'sin@kubsu.ru',
  // Подключение к базе данных
  'db_host' => 'localhost',
  'db_name' => 'u82379',
  'db_user' => 'u82379',
  'db_psw' => '8400862',
);
// Определения ресурсов для диспатчера.
$urlconf = array(
  '' => array('module' => 'front'),
  '/^admin$/' => array('module' => 'admin', 'auth' => 'auth_basic'),
  '/^admin\/(\d+)$/' => array('module' => 'admin', 'auth' => 'auth_basic'),
  '/^profile\/(\d+)$/' => array('module' => 'profile'),
  '/^login$/' => array('module' => 'login'),
  '/^getUserId$/' => array('module' => 'getUserId'),
  '/^findMyProfile$/' => array('module' => 'findMyProfile'),

/*  '/^order\/(\d+)$/' => array('module' => 'order', 'auth' => 'auth_db_basic'),
  '/^order\/(\d+)\/add$/' => array('module' => 'order_add', 'auth' => 'auth_db_basic'),
  '/^order\/(\d+)\/add\/(\d+)$/' => array('module' => 'order_add', 'auth' => 'auth_db_basic'),
  '/^order\/(\d+)\/bill$/' => array('module' => 'order_bill', 'auth' => 'auth_db_basic', 'tpl' => 'print'),
  '/^order\/(\d+)\/blank$/' => array('module' => 'order_blank', 'auth' => 'auth_db_basic', 'tpl' => 'print'),
  '/^order\/(\d+)\/1c$/' => array('module' => 'order_1c', 'auth' => 'auth_db_basic'),
  '/^order\/(\d+)\/1c2$/' => array('module' => 'order_1c2', 'auth' => 'auth_db_basic'),
  '/^order\/(\d+)\/(\d+)$/' => array('module' => 'model', 'auth' => 'auth_db_basic'),
  '/^stock\/(\d+)$/' => array('module' => 'stock', 'auth' => 'auth_db_basic'),
  '/^tk$/' => array('module' => 'tk', 'auth' => 'auth_db_basic'),
  '/^request\/(\d+)$/' => array('module' => 'request', 'auth' => 'auth_db_basic'),
  '/^request$/' => array('module' => 'request_all', 'auth' => 'auth_db_basic'),
  '/^models$/' => array('module' => 'models', 'auth' => 'auth_db_basic'),
  '/^users$/' => array('module' => 'users', 'auth' => 'auth_db_basic'),
  '/^users\/(\d+)$/' => array('module' => 'user', 'auth' => 'auth_db_basic'),
  '/^shop$/' => array('module' => 'shop', 'auth' => 'auth_db_basic'),
  '/^shop\/(\d+)$/' => array('module' => 'model', 'auth' => 'auth_db_basic'),
  '/^shop\/add\/(\d+)$/' => array('module' => 'shop_add', 'auth' => 'auth_db_basic'),
  '/^archive$/' => array('module' => 'archive', 'auth' => 'auth_db_basic'),
  '/^item\/(\d+)$/' => array('module' => 'item', 'auth' => 'auth_db_basic'),
  '/^item\/(\d+)\/edit$/' => array('module' => 'item_edit', 'auth' => 'auth_db_basic'),
  '/^item\/(\d+)\/repair$/' => array('module' => 'item_repair', 'auth' => 'auth_db_basic'),
  '/^price$/' => array('module' => 'price', 'auth' => 'auth_db_basic'),
  '/^tksearch$/' => array('module' => 'tksearch', 'auth' => 'auth_db_basic'),
  '/^report$/' => array('module' => 'report', 'auth' => 'auth_db_basic'),
  '/^rko$/' => array('module' => 'rko', 'auth' => 'auth_db_basic'),
  '/^return$/' => array('module' => 'return', 'auth' => 'auth_db_basic'),
  '/^sale$/' => array('module' => 'sales', 'auth' => 'auth_db_basic'),
  '/^sale\/(\d+)$/' => array('module' => 'sale', 'auth' => 'auth_db_basic'),
  '/^sale\/(\d+)\/add$/' => array('module' => 'sale_add', 'auth' => 'auth_db_basic'),
  '/^sale\/(\d+)\/add\/(\d+)$/' => array('module' => 'sale_add_item', 'auth' => 'auth_db_basic'),
  '/^sale\/(\d+)\/talon$/' => array('module' => 'sale_talon', 'auth' => 'auth_db_basic', 'tpl' => 'print'),
  '/^sale\/(\d+)\/blank$/' => array('module' => 'sale_blank', 'auth' => 'auth_db_basic', 'tpl' => 'print'),
  '/^repair$/' => array('module' => 'repair', 'auth' => 'auth_db_basic'),
  '/^repair\/(\d+)$/' => array('module' => 'repair_item', 'auth' => 'auth_db_basic', 'tpl' => 'print'),*/
);

// Отрубаем кеш.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
// Выдаем кодировку.
header('Content-Type: text/html; charset=' . $conf['charset']);
