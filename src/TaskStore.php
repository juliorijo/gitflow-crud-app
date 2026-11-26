<?php
declare(strict_types=1);

final class TaskStore
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM tasks ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM tasks WHERE id = :id');
        $statement->execute(['id' => $id]);
        $task = $statement->fetch(PDO::FETCH_ASSOC);
        return $task === false ? null : $task;
    }

    public function create(string $title, string $description): int
    {
        [$title, $description] = $this->validate($title, $description);
        $statement = $this->db->prepare('INSERT INTO tasks (title, description) VALUES (:title, :description)');
        $statement->execute(['title' => $title, 'description' => $description]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $title, string $description): bool
    {
        [$title, $description] = $this->validate($title, $description);
        $statement = $this->db->prepare('UPDATE tasks SET title = :title, description = :description WHERE id = :id');
        $statement->execute(['id' => $id, 'title' => $title, 'description' => $description]);
        return $statement->rowCount() > 0;
    }

    private function validate(string $title, string $description): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '' || iconv_strlen($title, 'UTF-8') > 120) {
            throw new InvalidArgumentException('El título es obligatorio y no puede superar 120 caracteres.');
        }
        if (iconv_strlen($description, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('La descripción no puede superar 5000 caracteres.');
        }
        return [$title, $description];
    }
}
