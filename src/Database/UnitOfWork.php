<?php

declare(strict_types=1);

namespace Pairity\Database;

use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Exceptions\DatabaseException;

/**
 * Class UnitOfWork
 *
 * Tracks changes to DTOs and coordinates their persistence.
 */
class UnitOfWork
{
    public const STATE_NEW = 'new';
    public const STATE_CLEAN = 'clean';
    public const STATE_DIRTY = 'dirty';
    public const STATE_DELETED = 'deleted';

    /**
     * @var array<string, object>
     */
    protected array $states = [];

    /**
     * @var \SplObjectStorage
     */
    protected \SplObjectStorage $dtoStates;

    /**
     * UnitOfWork constructor.
     *
     * @param DatabaseManagerInterface $db
     */
    public function __construct(
        protected DatabaseManagerInterface $db
    ) {
        $this->dtoStates = new \SplObjectStorage();
    }

    /**
     * Track a DTO instance.
     *
     * @param BaseDTO $dto
     * @param string $state
     * @return void
     */
    public function track(BaseDTO $dto, string $state = self::STATE_CLEAN): void
    {
        $this->dtoStates[$dto] = $state;
    }

    /**
     * Register a new DTO.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function registerNew(BaseDTO $dto): void
    {
        $this->track($dto, self::STATE_NEW);
    }

    /**
     * Register a dirty DTO.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function registerDirty(BaseDTO $dto): void
    {
        if (isset($this->dtoStates[$dto]) && $this->dtoStates[$dto] === self::STATE_NEW) {
            return;
        }
        $this->track($dto, self::STATE_DIRTY);
    }

    /**
     * Register a deleted DTO.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function registerDeleted(BaseDTO $dto): void
    {
        $this->track($dto, self::STATE_DELETED);
    }

    /**
     * Get the state of a DTO.
     *
     * @param BaseDTO $dto
     * @return string|null
     */
    public function getState(BaseDTO $dto): ?string
    {
        return $this->dtoStates[$dto] ?? null;
    }

    /**
     * Commit all tracked changes to the database.
     *
     * @return void
     * @throws DatabaseException
     */
    public function commit(): void
    {
        $connections = $this->getConnectionsToCommit();

        foreach ($connections as $connectionName) {
            $this->db->connection($connectionName)->beginTransaction();
        }

        try {
            foreach ($this->dtoStates as $dto) {
                $state = $this->dtoStates[$dto];
                $dao = $dto->getDao();

                if (!$dao) {
                    $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
                    throw new DatabaseException($translator->trans('error.uow_no_dao'));
                }

                match ($state) {
                    self::STATE_NEW, self::STATE_DIRTY => $dao->save($dto),
                    self::STATE_DELETED => $this->deleteDto($dao, $dto),
                    default => null,
                };
            }

            foreach ($connections as $connectionName) {
                $this->db->connection($connectionName)->commit();
            }

            $this->clear();
        } catch (\Throwable $e) {
            foreach ($connections as $connectionName) {
                $this->db->connection($connectionName)->rollBack();
            }

            if ($e instanceof DatabaseException && $e->getMessage() === $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class)->trans('error.uow_no_dao')) {
                throw $e;
            }

            $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
            throw new DatabaseException(
                $translator->trans('error.uow_commit_failed', ['message' => $e->getMessage()]),
                0,
                $e,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Delete a DTO via its DAO.
     *
     * @param BaseDAO $dao
     * @param BaseDTO $dto
     * @return void
     */
    protected function deleteDto(BaseDAO $dao, BaseDTO $dto): void
    {
        $data = $dto->toArray();
        $primaryKey = $dao->getPrimaryKey();
        $id = $data[$primaryKey] ?? null;

        if ($id !== null) {
            $dao->delete($id);
        }
    }

    /**
     * Get list of unique connections involved in the current Unit of Work.
     *
     * @return array<string>
     */
    protected function getConnectionsToCommit(): array
    {
        $connections = [];
        foreach ($this->dtoStates as $dto) {
            $state = $this->dtoStates[$dto];
            if ($state === self::STATE_CLEAN) {
                continue;
            }

            $dao = $dto->getDao();
            if ($dao) {
                $connectionName = $dao->getConnectionName();
                $connections[$connectionName] = true;
            }
        }
        return array_keys($connections);
    }

    /**
     * Clear all tracked DTOs.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->dtoStates = new \SplObjectStorage();
    }
}
