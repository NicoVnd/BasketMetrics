<?php

// Router du serveur PHP local : les assets virtuels sont servis par Symfony.
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__.$requestPath);
if ($file !== false && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require __DIR__.'/index.php';
