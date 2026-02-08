<?php

declare(strict_types=1);

namespace Pairity\Database\Auditing;

use Pairity\DTO\BaseDTO;
use Pairity\Contracts\Events\DispatcherInterface;

/**
 * Class AuditListener
 *
 * Listens to model events and triggers auditing when applicable.
 */
class AuditListener
{
    /**
     * AuditListener constructor.
     *
     * @param Auditor $auditor
     */
    public function __construct(
        protected Auditor $auditor
    ) {
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param DispatcherInterface $dispatcher
     * @return void
     */
    public function subscribe(DispatcherInterface $dispatcher): void
    {
        $dispatcher->listen('pairity.model.created: *', [$this, 'onCreated']);
        $dispatcher->listen('pairity.model.updated: *', [$this, 'onUpdated']);
        $dispatcher->listen('pairity.model.deleted: *', [$this, 'onDeleted']);
    }

    /**
     * Handle model created event.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function onCreated(BaseDTO $dto): void
    {
        $this->audit($dto, 'created');
    }

    /**
     * Handle model updated event.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function onUpdated(BaseDTO $dto): void
    {
        $this->audit($dto, 'updated');
    }

    /**
     * Handle model deleted event.
     *
     * @param BaseDTO $dto
     * @return void
     */
    public function onDeleted(BaseDTO $dto): void
    {
        $this->audit($dto, 'deleted');
    }

    /**
     * Trigger audit recording if the model is auditable.
     *
     * @param BaseDTO $dto
     * @param string $event
     * @return void
     */
    protected function audit(BaseDTO $dto, string $event): void
    {
        $dao = $dto->getDao();
        
        if ($dao && $dao->getOption('auditable', false)) {
            $changes = $this->auditor->getChanges($dto);
            $this->auditor->record($dto, $event, $changes['old'], $changes['new']);
        }
    }
}
