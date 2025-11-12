<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo 'Anmeldung erforderlich';
    exit;
}

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    echo 'config.php fehlt';
    exit;
}

$config = require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Repositories.php';

$pdo = Database::getInstance($config);
$fileRepo = new FileRepository($pdo);

$fileId = (int)($_GET['id'] ?? 0);
$file = $fileRepo->find($fileId);
if (!$file) {
    http_response_code(404);
    echo 'Datei nicht gefunden';
    exit;
}

$path = $config['uploads_dir'] . '/' . $file['stored_name'];
if (!file_exists($path)) {
    http_response_code(404);
    echo 'Datei nicht gefunden';
    exit;
}

header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: inline; filename="' . basename($file['filename']) . '"');
readfile($path);
