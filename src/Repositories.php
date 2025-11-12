<?php

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createUser(string $username, string $password, bool $isAdmin = false): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, is_admin, is_approved) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $isAdmin ? 1 : 0,
            $isAdmin ? 1 : 0,
        ]);
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function getPendingUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, created_at FROM users WHERE is_approved = 0');
        return $stmt->fetchAll();
    }

    public function approveUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_approved = 1 WHERE id = ?');
        $stmt->execute([$userId]);
    }
}

class ProjectRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function create(string $name, string $description = ''): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO projects (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $description = ''): void
    {
        $stmt = $this->pdo->prepare('UPDATE projects SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }
}

class DocumentRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByProject(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function create(int $projectId, string $title): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO documents (project_id, title) VALUES (?, ?)');
        $stmt->execute([$projectId, $title]);
        return (int)$this->pdo->lastInsertId();
    }

    public function get(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        if (!$document) {
            return null;
        }

        $stmtBlocks = $this->pdo->prepare('SELECT * FROM text_blocks WHERE document_id = ? ORDER BY position ASC');
        $stmtBlocks->execute([$documentId]);
        $blocks = $stmtBlocks->fetchAll();
        foreach ($blocks as &$block) {
            $stmtSuggestions = $this->pdo->prepare('SELECT * FROM change_suggestions WHERE text_block_id = ? ORDER BY created_at DESC');
            $stmtSuggestions->execute([$block['id']]);
            $block['suggestions'] = $stmtSuggestions->fetchAll();
        }
        unset($block);
        $document['blocks'] = $blocks;

        $stmtFiles = $this->pdo->prepare('SELECT * FROM files WHERE document_id = ? ORDER BY created_at DESC');
        $stmtFiles->execute([$documentId]);
        $document['files'] = $stmtFiles->fetchAll();

        return $document;
    }
}

class TextBlockRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $documentId, int $position): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO text_blocks (document_id, position, left_text, right_summary) VALUES (?, ?, "", "")');
        $stmt->execute([$documentId, $position]);
        $id = (int)$this->pdo->lastInsertId();
        return $this->find($id);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM text_blocks WHERE id = ?');
        $stmt->execute([$id]);
        $block = $stmt->fetch();
        if ($block) {
            $stmtSuggestions = $this->pdo->prepare('SELECT * FROM change_suggestions WHERE text_block_id = ? ORDER BY created_at DESC');
            $stmtSuggestions->execute([$id]);
            $block['suggestions'] = $stmtSuggestions->fetchAll();
        }
        return $block ?: null;
    }

    public function updateLeft(int $id, string $text): void
    {
        $stmt = $this->pdo->prepare('UPDATE text_blocks SET left_text = ? WHERE id = ?');
        $stmt->execute([$text, $id]);
    }

    public function updateRight(int $id, string $summary): void
    {
        $stmt = $this->pdo->prepare('UPDATE text_blocks SET right_summary = ? WHERE id = ?');
        $stmt->execute([$summary, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM text_blocks WHERE id = ?');
        $stmt->execute([$id]);
    }
}

class SuggestionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $blockId, string $text): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO change_suggestions (text_block_id, suggestion_text) VALUES (?, ?)');
        $stmt->execute([$blockId, $text]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM change_suggestions WHERE id = ?');
        $stmt->execute([$id]);
    }
}

class FileRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(int $projectId, ?int $documentId = null): array
    {
        if ($documentId) {
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE project_id = ? AND document_id = ? ORDER BY created_at DESC');
            $stmt->execute([$projectId, $documentId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function create(int $projectId, ?int $documentId, string $filename, string $storedName, string $mimeType): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO files (project_id, document_id, filename, stored_name, mime_type) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$projectId, $documentId, $filename, $storedName, $mimeType]);
        return (int)$this->pdo->lastInsertId();
    }

    public function find(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        return $file ?: null;
    }

    public function delete(int $fileId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ?');
        $stmt->execute([$fileId]);
    }
}
