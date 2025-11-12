<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'config.php fehlt. Kopiere config.sample.php und passe die Zugangsdaten an.']);
    exit;
}

$config = require __DIR__ . '/../src/bootstrap.php';

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LLMClient.php';
require_once __DIR__ . '/../src/Repositories.php';

$pdo = Database::getInstance($config);
$userRepo = new UserRepository($pdo);
$projectRepo = new ProjectRepository($pdo);
$documentRepo = new DocumentRepository($pdo);
$blockRepo = new TextBlockRepository($pdo);
$suggestionRepo = new SuggestionRepository($pdo);
$fileRepo = new FileRepository($pdo);
$llm = new LLMClient($config);

action_switch:
$action = $_GET['action'] ?? '';

function jsonResponse($data): void
{
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        if ($username === '' || $password === '') {
            http_response_code(422);
            jsonResponse(['error' => 'Benutzername und Passwort erforderlich']);
        }
        if ($userRepo->findByUsername($username)) {
            http_response_code(409);
            jsonResponse(['error' => 'Benutzer existiert bereits']);
        }
        $userRepo->createUser($username, $password, false);
        jsonResponse(['message' => 'Registrierung eingereicht. Bitte auf Freigabe warten.']);

    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $user = $userRepo->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Ungültige Zugangsdaten']);
        }
        if ((int)$user['is_approved'] !== 1) {
            http_response_code(403);
            jsonResponse(['error' => 'Account wurde noch nicht freigeschaltet']);
        }
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'is_admin' => (bool)$user['is_admin'],
        ];
        jsonResponse(['message' => 'Login erfolgreich', 'user' => $_SESSION['user']]);

    case 'logout':
        session_destroy();
        jsonResponse(['message' => 'Abgemeldet']);

    case 'session':
        jsonResponse(['user' => $_SESSION['user'] ?? null]);

    case 'projects':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            jsonResponse(['projects' => $projectRepo->all()]);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            if ($name === '') {
                http_response_code(422);
                jsonResponse(['error' => 'Projektname erforderlich']);
            }
            $id = $projectRepo->create($name, $description);
            jsonResponse(['project' => ['id' => $id, 'name' => $name, 'description' => $description]]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $id = (int)($_GET['id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $projectRepo->update($id, $name, $description);
            jsonResponse(['message' => 'Projekt aktualisiert']);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $id = (int)($_GET['id'] ?? 0);
            $projectRepo->delete($id);
            jsonResponse(['message' => 'Projekt gelöscht']);
        }
        break;

    case 'documents':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $projectId = (int)($_GET['project_id'] ?? 0);
            jsonResponse(['documents' => $documentRepo->listByProject($projectId)]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $projectId = (int)($data['project_id'] ?? 0);
            $title = trim($data['title'] ?? '');
            if ($projectId === 0 || $title === '') {
                http_response_code(422);
                jsonResponse(['error' => 'Projekt und Titel erforderlich']);
            }
            $docId = $documentRepo->create($projectId, $title);
            jsonResponse(['document' => $documentRepo->get($docId)]);
        }
        break;

    case 'document':
        Auth::requireLogin();
        $documentId = (int)($_GET['id'] ?? 0);
        $document = $documentRepo->get($documentId);
        if (!$document) {
            http_response_code(404);
            jsonResponse(['error' => 'Dokument nicht gefunden']);
        }
        jsonResponse(['document' => $document]);

    case 'block':
        Auth::requireLogin();
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $documentId = (int)($data['document_id'] ?? 0);
            $position = (int)($data['position'] ?? 0);
            $block = $blockRepo->create($documentId, $position);
            jsonResponse(['block' => $block]);
        }
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            $blockId = (int)($_GET['id'] ?? 0);
            $type = $data['type'] ?? '';
            if ($type === 'left') {
                $text = $data['text'] ?? '';
                $blockRepo->updateLeft($blockId, $text);
                if (trim($text) === '') {
                    $blockRepo->updateRight($blockId, '');
                } else {
                    $summary = $llm->summarize($text);
                    if ($summary !== null) {
                        $blockRepo->updateRight($blockId, $summary);
                    }
                }
                $updated = $blockRepo->find($blockId);
                jsonResponse(['block' => $updated]);
            }
            if ($type === 'right') {
                $summary = $data['summary'] ?? '';
                $block = $blockRepo->find($blockId);
                if (!$block) {
                    http_response_code(404);
                    jsonResponse(['error' => 'Block nicht gefunden']);
                }
                $blockRepo->updateRight($blockId, $summary);
                $generated = null;
                $suggestionId = null;
                if (trim($block['left_text']) === '' && trim($summary) !== '') {
                    $generated = $llm->expandFromBullets($summary);
                    if ($generated) {
                        $blockRepo->updateLeft($blockId, $generated);
                    }
                } elseif (trim($block['left_text']) !== '' && trim($summary) !== '') {
                    $revision = $llm->suggestRevision($block['left_text'], $summary);
                    if ($revision) {
                        $suggestionId = $suggestionRepo->create($blockId, $revision);
                    }
                }
                $updated = $blockRepo->find($blockId);
                jsonResponse(['block' => $updated, 'generatedText' => $generated, 'suggestionId' => $suggestionId]);
            }
        }
        if ($method === 'DELETE') {
            $blockId = (int)($_GET['id'] ?? 0);
            $blockRepo->delete($blockId);
            jsonResponse(['message' => 'Block gelöscht']);
        }
        break;

    case 'suggestion':
        Auth::requireLogin();
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $block = $blockRepo->find($id);
            if (!$block) {
                http_response_code(404);
                jsonResponse(['error' => 'Block nicht gefunden']);
            }
            $suggestionId = (int)($data['suggestion_id'] ?? 0);
            $suggestionText = '';
            foreach ($block['suggestions'] as $suggestion) {
                if ((int)$suggestion['id'] === $suggestionId) {
                    $suggestionText = $suggestion['suggestion_text'];
                    break;
                }
            }
            if ($suggestionText === '') {
                http_response_code(404);
                jsonResponse(['error' => 'Vorschlag nicht gefunden']);
            }
            $blockRepo->updateLeft($id, $suggestionText);
            $summary = $llm->summarize($suggestionText);
            if ($summary) {
                $blockRepo->updateRight($id, $summary);
            }
            $suggestionRepo->delete($suggestionId);
            jsonResponse(['block' => $blockRepo->find($id)]);
        }
        if ($method === 'DELETE') {
            $suggestionId = (int)($_GET['id'] ?? 0);
            $suggestionRepo->delete($suggestionId);
            jsonResponse(['message' => 'Vorschlag entfernt']);
        }
        break;

    case 'files':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $projectId = (int)($_GET['project_id'] ?? 0);
            $documentId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : null;
            jsonResponse(['files' => $fileRepo->list($projectId, $documentId)]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $projectId = (int)($_POST['project_id'] ?? 0);
            $documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : null;
            if (!isset($_FILES['file'])) {
                http_response_code(422);
                jsonResponse(['error' => 'Keine Datei hochgeladen']);
            }
            $file = $_FILES['file'];
            $storedName = uniqid('upload_', true) . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
            $target = $config['uploads_dir'] . '/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                http_response_code(500);
                jsonResponse(['error' => 'Datei konnte nicht gespeichert werden']);
            }
            $fileId = $fileRepo->create($projectId, $documentId, $file['name'], $storedName, $file['type']);
            jsonResponse(['file' => $fileRepo->find($fileId)]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $fileId = (int)($_GET['id'] ?? 0);
            $file = $fileRepo->find($fileId);
            if ($file) {
                $path = $config['uploads_dir'] . '/' . $file['stored_name'];
                if (file_exists($path)) {
                    unlink($path);
                }
                $fileRepo->delete($fileId);
            }
            jsonResponse(['message' => 'Datei gelöscht']);
        }
        break;

    case 'pendingUsers':
        Auth::requireAdmin();
        jsonResponse(['users' => $userRepo->getPendingUsers()]);

    case 'approveUser':
        Auth::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($data['user_id'] ?? 0);
        $userRepo->approveUser($userId);
        jsonResponse(['message' => 'Benutzer freigeschaltet']);

    default:
        http_response_code(404);
        jsonResponse(['error' => 'Unbekannte Aktion']);
}
