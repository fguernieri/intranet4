<?php
namespace Modules\Forms\Model;

use PDO;
use PDOException;

class ResponseValueModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insere um valor de campo em form_response_values.
     */
    public function create(int $responseId, int $fieldId, string $value): bool
    {
        $sql = "INSERT INTO form_response_values 
                  (response_id, field_id, value) 
                VALUES 
                  (:response_id, :field_id, :value)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':response_id' => $responseId,
            ':field_id'    => $fieldId,
            ':value'       => $value
        ]);
    }
}
