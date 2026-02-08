<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Scopes;

use Pairity\Database\Query\Builder;

/**
 * Class TenantScope
 *
 * Automatically applies tenant-based scoping to queries.
 */
class TenantScope
{
    /**
     * @var mixed|null
     */
    protected static mixed $tenantId = null;

    /**
     * @var string
     */
    protected static string $tenantColumn = 'tenant_id';

    /**
     * Set the active tenant ID.
     *
     * @param mixed $tenantId
     * @return void
     */
    public static function setTenantId(mixed $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    /**
     * Get the active tenant ID.
     *
     * @return mixed
     */
    public static function getTenantId(): mixed
    {
        return self::$tenantId;
    }

    /**
     * Set the tenant column name.
     *
     * @param string $column
     * @return void
     */
    public static function setTenantColumn(string $column): void
    {
        self::$tenantColumn = $column;
    }

    /**
     * Get the tenant column name.
     *
     * @return string
     */
    public static function getTenantColumn(): string
    {
        return self::$tenantColumn;
    }

    /**
     * Apply the scope to the given query builder.
     *
     * @param Builder $builder
     * @return void
     */
    public function apply(Builder $builder): void
    {
        if (self::$tenantId !== null) {
            $builder->where(self::$tenantColumn, self::$tenantId);
        }
    }
}
