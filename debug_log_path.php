<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Base path: " . $app->basePath() . PHP_EOL;
echo "Storage path: " . $app->storagePath() . PHP_EOL;

$logConfig = config('logging.channels.single');
echo "Single channel path: " . $logConfig['path'] . PHP_EOL;
echo "Resolved storage_path('logs/laravel.log'): " . storage_path('logs/laravel.log') . PHP_EOL;

// Also check logger
$logger = Log::getLogger();
echo "Logger handlers: " . count($logger->getHandlers()) . PHP_EOL;
foreach ($logger->getHandlers() as $i => $handler) {
    echo "Handler " . $i . ": " . get_class($handler) . PHP_EOL;
    if (method_exists($handler, 'getUrl')) {
        echo "  URL: " . $handler->getUrl() . PHP_EOL;
    }
    if (method_exists($handler, 'getStream')) {
        echo "  Stream: " . $handler->getStream() . PHP_EOL;
    }
    if (method_exists($handler, 'getPath')) {
        echo "  Path: " . $handler->getPath() . PHP_EOL;
    }
}