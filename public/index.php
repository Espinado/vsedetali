<?php

// На Windows веб-сервер (Apache) часто не видит Git в PATH — из-за этого пакеты вроде sebastian/version
// вызывают proc_open('git') и получают "CreateProcess failed". Добавляем путь к Git в PATH до загрузки vendor.
if (PHP_OS_FAMILY === 'Windows') {
    $path = getenv('Path') ?: '';
    $gitPaths = [
        'C:\\laragon\\bin\\git\\cmd',
        'C:\\Program Files\\Git\\cmd',
    ];
    foreach ($gitPaths as $p) {
        if (is_dir($p) && strpos($path, $p) === false) {
            putenv('Path=' . $path . ';' . $p);
            break;
        }
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
