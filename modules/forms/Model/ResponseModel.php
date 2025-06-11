<?php
namespace Modules\Forms\Model;

use PDO;
use PDOException;

class ResponseModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insere um registro em form_responses e retorna o novo ID.
     */
    public function create(int $formId): int
    {
        $sql = "INSERT INTO form_responses (form_id) VALUES (:form_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':form_id' => $formId]);
        return (int)$this->pdo->lastInsertId();
    }
}
