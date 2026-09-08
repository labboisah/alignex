<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Refuse any configuration that could reset an application database.
$root = dirname(__DIR__, 2);
$expected = str_replace('\\', '/', $root.'/storage/framework/testing/adaptive-browser.sqlite');
if (getenv('ADAPTIVE_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $expected) {
    fwrite(STDERR, "Browser preparation requires its isolated SQLite database.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.default') !== 'sqlite'
    || str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected) {
    fwrite(STDERR, "Resolved application configuration is not the isolated browser database.\n");
    exit(1);
}
$status = Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
echo Artisan::output();
exit($status);
