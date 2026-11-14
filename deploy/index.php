<?php

/*
| index.php para el hosting cPanel (va dentro de public_html).
| El sistema vive en ../rye (fuera de public_html, así el código y el .env
| no son accesibles desde internet).
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = __DIR__.'/../rye';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

// Las imágenes subidas, CSS y JS están aquí en public_html
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
