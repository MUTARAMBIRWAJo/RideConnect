<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
echo 'Base path: '.$app->basePath().PHP_EOL;
echo 'Storage path: '.$app->storagePath().PHP_EOL;
