<?php
$config = require __DIR__ . '/../config.php';

if (!file_exists($config['uploads_dir'])) {
    mkdir($config['uploads_dir'], 0775, true);
}

return $config;
