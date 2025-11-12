<?php

class Auth
{
    public static function requireLogin(): void
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Nicht angemeldet']);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!($_SESSION['user']['is_admin'] ?? false)) {
            http_response_code(403);
            echo json_encode(['error' => 'Adminrechte erforderlich']);
            exit;
        }
    }
}
