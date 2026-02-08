<?php

declare(strict_types=1);

namespace Pairity\Database\Auditing;

use Pairity\DTO\BaseDTO;

/**
 * Class Auditor
 *
 * Handles calculating diffs and logging changes for auditable models.
 */
class Auditor
{
    /**
     * Calculate changes between a DTO's attributes and its current state.
     * Note: In a full implementation, we might track 'original' values in the DTO.
     * For now, we'll assume we are logging the state as it is at the moment of the event.
     *
     * @param BaseDTO $dto
     * @return array
     */
    public function getChanges(BaseDTO $dto): array
    {
        // Simple implementation: return all attributes as 'new'
        // In Milestone 15/future, we should add 'original' attribute tracking to BaseDTO.
        return [
            'old' => [],
            'new' => $dto->toArray()
        ];
    }

    /**
     * Record an audit entry.
     *
     * @param BaseDTO $dto
     * @param string $event
     * @param array $oldValues
     * @param array $newValues
     * @return void
     */
    public function record(BaseDTO $dto, string $event, array $oldValues, array $newValues): void
    {
        $dao = $dto->getDao();
        if (!$dao) {
            return;
        }

        $db = $dao->getDb();
        $connection = $db->connection();
        
        $primaryKey = $dao->getPrimaryKey();
        $auditableId = $dto->toArray()[$primaryKey] ?? null;

        $sql = "INSERT INTO audits (auditable_type, auditable_id, event, old_values, new_values, created_at) VALUES (?, ?, ?, ?, ?, ?)";
        
        $connection->execute($sql, [
            get_class($dto),
            $auditableId,
            $event,
            json_encode($oldValues),
            json_encode($newValues),
            date('Y-m-d H:i:s')
        ]);
    }
}
