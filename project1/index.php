<?php
include('./settings.php');

// Выключаем отображение ошибок после отладки.
ini_set('display_errors', DISPLAY_ERRORS);
//error_reporting(E_ALL & E_STRICT);
//ini_set("mysql.trace_mode","On");

// Папки со скриптами и модулями.
ini_set('include_path', INCLUDE_PATH);



include('init.php');
include('db.php');

$request = array(
  'url' => isset($_GET['q']) ? $_GET['q'] : '',
  'method' => isset($_POST['method']) && in_array($_POST['method'], array('get', 'post', 'put', 'delete')) ? $_POST['method'] : $_SERVER['REQUEST_METHOD'],
  'get' => !empty($_GET) ? $_GET : array(),
  'post' => !empty($_POST) ? $_POST : array(),
  'put' => !empty($_POST) && !empty($_POST['method']) && $_POST['method'] == 'put' ? $_POST : array(),
  'delete' => !empty($_POST) && !empty($_POST['method']) && $_POST['method'] == 'put' ? $_POST : array(),
  'Content-Type' => 'text/html',
);

$response = init($request, $urlconf);

if (!empty($response['headers'])) {
  foreach ($response['headers'] as $key => $value) {
    if (is_string($key)) {
      header(sprintf('%s: %s', $key, $value));
    }
    else {
      header($value);
    }
  }
}

if (!empty($response['entity'])) {
  print($response['entity']);
}
