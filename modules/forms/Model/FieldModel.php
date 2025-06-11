<?php
namespace Modules\Forms\Model;

use PDO;
use PDOException;

class FieldModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todos os campos de um formulário, ordenados.
     */
    public function getByForm(int $formId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * 
             FROM form_fields 
             WHERE form_id = :form_id 
             ORDER BY position ASC"
        );
        $stmt->execute([':form_id' => $formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um campo pelo ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM form_fields WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $field = $stmt->fetch(PDO::FETCH_ASSOC);
        return $field ?: null;
    }

    /**
     * Cria um novo campo, sempre na última posição.
     */
    public function create(
        int $formId,
        string $label,
        string $type,
        bool $isRequired,
        ?array $settings
    ): int {
        // calcula próxima posição
        $posStmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(position),0)+1 AS next_pos 
             FROM form_fields 
             WHERE form_id = :form_id"
        );
        $posStmt->execute([':form_id' => $formId]);
        $position = (int)$posStmt->fetchColumn();

        $sql = "INSERT INTO form_fields
                  (form_id, label, type, is_required, position, settings)
                VALUES
                  (:form_id, :label, :type, :is_required, :position, :settings)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':form_id'     => $formId,
            ':label'       => $label,
            ':type'        => $type,
            ':is_required' => $isRequired ? 1 : 0,
            ':position'    => $position,
            ':settings'    => $settings ? json_encode($settings) : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza um campo existente (sem mexer na posição).
     */
    public function update(
        int $id,
        string $label,
        string $type,
        bool $isRequired,
        ?array $settings
    ): bool {
        $sql = "UPDATE form_fields
                   SET label       = :label,
                       type        = :type,
                       is_required = :is_required,
                       settings    = :settings
                 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':label'       => $label,
            ':type'        => $type,
            ':is_required' => $isRequired ? 1 : 0,
            ':settings'    => $settings ? json_encode($settings) : null,
        ]);
    }

    /**
     * Remove um campo.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM form_fields WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Troca a posição de dois campos (para mover para cima/baixo).
     */
    public function swapPositions(int $id1, int $id2): bool
    {
        try {
            $this->pdo->beginTransaction();
            $sel  = $this->pdo->prepare(
                "SELECT position FROM form_fields WHERE id = :id"
            );
            $upd  = $this->pdo->prepare(
                "UPDATE form_fields SET position = :position WHERE id = :id"
            );

            // pega posições
            $sel->execute([':id' => $id1]);
            $pos1 = (int)$sel->fetchColumn();
            $sel->execute([':id' => $id2]);
            $pos2 = (int)$sel->fetchColumn();

            // faz swap
            $upd->execute([':position' => $pos2, ':id' => $id1]);
            $upd->execute([':position' => $pos1, ':id' => $id2]);

            return $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
