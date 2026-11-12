<?php

use App\Support\EnvBootstrap;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// A raw upload without vendor/ cannot boot at all. Say so instead of a blank
// fatal error — this is the first thing a buyer sees if the zip was partial.
if (! is_file(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../bootstrap/install-blocked.php';
    install_blocked(
        'Application files are incomplete',
        'The <code>vendor/</code> folder is missing, so the application cannot start. '
        .'Re-upload the complete package (it includes <code>vendor/</code>), or run '
        .'<code>composer install --no-dev</code> on the server.'
    );
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Make a fresh upload bootable before the framework starts: create .env,
// generate APP_KEY, use file-based drivers until the database exists. On an
// installed site this is a no-op.
try {
    EnvBootstrap::ensure(dirname(__DIR__));
} catch (Throwable $e) {
    require __DIR__.'/../bootstrap/install-blocked.php';
    install_blocked(
        'The application folder is not writable',
        'The installer could not create or update the <code>.env</code> file: '
        .'<code>'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</code>. '
        .'Make the application root folder writable by the web server user and reload this page.'
    );
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
