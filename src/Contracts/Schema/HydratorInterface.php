<?php

declare(strict_types=1);

namespace Pairity\Contracts\Schema;

/**
 * Interface HydratorInterface
 *
 * Defines the contract for high-performance DTO hydrators.
 */
interface HydratorInterface
{
    /**
     * Hydrate a DTO instance from raw data.
     *
     * @param array<string, mixed> $data
     * @param object $instance The DTO instance to hydrate.
     * @return void
     */
    public function hydrate(array $data, object $instance): void;
}
