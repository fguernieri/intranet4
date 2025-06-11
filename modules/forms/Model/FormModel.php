<?php
namespace Modules\Forms\Model;

use PDO;
use PDOException;

class FormModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todos os formulários cadastrados.
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT id, title, description, created_at FROM forms ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um formulário pelo ID.
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM forms WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        return $form ?: null;
    }

    /**
     * Insere um novo formulário.
     * @param string $title
     * @param string|null $description
     * @return int ID do novo registro
     * @throws PDOException
     */
    public function create(string $title, ?string $description): int
    {
        $sql = "INSERT INTO forms (title, description) VALUES (:title, :description)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza um formulário existente.
     * @param int $id
     * @param string $title
     * @param string|null $description
     * @return bool
     */
    public function update(int $id, string $title, ?string $description): bool
    {
        $sql = "UPDATE forms 
                SET title = :title, description = :description, updated_at = NOW() 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':title'       => $title,
            ':description' => $description,
        ]);
    }

    /**
     * Remove um formulário.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM forms WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
