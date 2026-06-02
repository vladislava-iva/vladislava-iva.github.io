<?php

// auth_basic.php - HTTP Authentication Script v 1.0
//##################################################

function auth(&$request, $r) {
  // TODO: запрашивать пользователя из БД.
  $users = array(
    'admin' => '123',
  );
  if (empty($user) && !empty($_SERVER['PHP_AUTH_USER'])) {
    $user = array(
      'login' => $_SERVER['PHP_AUTH_USER'],
      'pass' => $users[$_SERVER['PHP_AUTH_USER']]
    );
    $request['user'] = $user;
  }
  if (!isset($_SERVER['PHP_AUTH_USER']) || empty($user) || $_SERVER['PHP_AUTH_USER'] != $user['login'] || $_SERVER['PHP_AUTH_PW'] != $user['pass']) {
    unset($user);
    $response = array(
      'headers' => array(sprintf('WWW-Authenticate: Basic realm="%s"', conf('sitename')), 'HTTP/1.0 401 Unauthorized'),
      'entity' => theme('401', $request),
    );
    return $response;
  }
}
