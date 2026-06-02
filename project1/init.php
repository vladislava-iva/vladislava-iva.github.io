<?php

/**
 * Initlab framework 2.0, учебная версия.
 * (с) Сергей Синица 2007-2020.
 * (с) "КубГУ", 2013.
 */

// Диспатчер. Делает запрос $request в соответствии со структурой $urlconf.
function init($request = array(), $urlconf = array()) {
  // Массив HTTP-ответа.
  $response = array();

  // Шаблон страницы по умолчанию.
  $template = 'page';

  // Массив текущего вывода модулей.
  $c = array();

  // Пробергаем по всем определениям ресурсов и для подходящих URL
  // вызываем процедуры их модулей, соответствующие методу HTTP-запроса.
  $q = isset($request['url']) ? $request['url'] : '';
  $method = isset($request['method']) ? $request['method'] : 'get';
  foreach ($urlconf as $url => $r) {
    $matches = array();
    if ($url == '' || $url[0] != '/') {
      // Если не регулярное выражение, то поверяем на равенство.
      if ($url != $q) {
        continue;
      }
    }
    else {
      // Проверяем соответствие URL запроса регулярному выражению.
      if (!preg_match_all($url, $q, $matches)) {
        continue;
      }
    }

    // Аутентификация и инициализация $request['user'].
    if (isset($r['auth'])) {
      require_once($r['auth'] . '.php');
      $auth = auth($request, $r);
      if ($auth) {
        // Аутентификация вернула заголовки 401.
        return $auth;
      }
    }

    // Шаблон всей страницы можно перекрыть для обрабатываемого ресурса в $urlconf.
    if (isset($r['tpl'])) {
      $template = $r['tpl'];
    }

    // Обработка запроса модулем.
    if (!isset($r['module'])) {
      continue;
    }
    require_once($r['module'] . '.php');
    // Собираем имя функции из имени модуля и метода запроса.
    $func = sprintf('%s_%s', $r['module'], $method);
    if (!function_exists($func)) {
      continue;
    }

    // Собираем параметры в массив.
    $params = array($request);
    array_shift($matches);
    foreach ($matches as $key => $match) {
      $params[] = $match[0];
    }

    // Вызываем обработчик запроса в модуле передавая параметры из $params.
    if ($result = call_user_func_array($func, $params)) {
      if (is_array($result)) {
        $response = array_merge($response, $result);
        // Первый модуль отработал запрос и выставил редирект или not found или forbidden.
        // Другие модули уже не отрабатывают запрос.
        // Т.е. важно в каком порядке стоят модули в массиве $res.
        if (!empty($response['headers'])) {
          return $response;
        }
      }
      else {
        $c['#content'][$r['module']] = $result;
      }
    }
  }

  // Если есть вывод модулей, то выводим его через шаблон страницы или шаблон в $urlconf.
  if (!empty($c)) {
    $c['#request'] = $request;
    $response['entity'] = theme($template, $c);
  }
  else {
    $response = not_found();
  }

  // Браузер определяет кодировку страницы по кодировке, выдаваемой вебсервером в заголовке HTTP-ответа.
  $response['headers']['Content-Type'] = 'text/html; charset=' . conf('charset');

  return $response;
}

// Возвращает параметр конфигурации из settings.php.
function conf($key) {
  global $conf;
  return isset($conf[$key]) ? $conf[$key] : FALSE;
}

// Формирует сокращенные URL для ссылок или для текущей страницы.
function url($addr = '', $params = array()) {
  global $conf;
  // Если вызвали без параметров, до делаем ссылку на текущую страницу.
  if ($addr == '' && isset($_GET['q'])) {
    $addr = strip_tags($_GET['q']);
  }
  // В зависимоти от настроек проекта генерируем чистые ссылки или ссылки с параметром.
  $clean = conf('clean_urls');
  $r = $clean ? '/' : '?q=';
  $r .= strip_tags($addr);
  // Добавляем параметры.
  if (count($params) > 0) {
    $r .= $clean ? '?' : '&';
    $r .= implode('&', $params);
  }
  return $r;
}

// Возвращает редирект 302 с заголовком Location.
function redirect($l = NULL) {
  if (is_null($l)) {
    $location = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  }
  else {
    $location = 'http://' . $_SERVER['HTTP_HOST'] . conf('basedir') . url($l);
  }
  return array('headers' => array('Location' => $location));
}

// Возвращает 403.
function access_denied() {
  return array(
    'headers' => array('HTTP/1.1 403 Forbidden'),
    'entity' => theme('403'),
  );
}

// Возвращает 404.
function not_found() {
  return array(
    'headers' => array('HTTP/1.1 404 Not Found'),
    'entity' => theme('404'),
  );
}

// Функция загрузки шаблона с использованием буферизации вывода.
function theme($t, $c = array()) {
  // Путь к файлу шаблона.
  $template = conf('theme') . '/' . str_replace('/', '_', $t) . '.tpl.php';

  // Если нет файла шаблона, то просто печатаем данные слитно.
  if (!file_exists($template)) {
    return implode('', $c);
  }

  // Начинаем буферизацию вывода.
  ob_start();
  // Парсим и включаем файл шаблона, весь вывод попадает в буфер.
  include $template;
  // Достаем содержимое буфера.
  $contents = ob_get_contents();
  // Оканчиваем буферизацию очищая буфер.
  ob_end_clean();
  // Возвращаем контент.
  return $contents;
}