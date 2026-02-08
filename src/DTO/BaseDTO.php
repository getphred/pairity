<?php

declare(strict_types=1);

namespace Pairity\DTO;

/**
 * Class BaseDTO
 *
 * Base class for all generated DTOs.
 *
 * @package Pairity\DTO
 */
abstract class BaseDTO
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @var bool
     */
    protected bool $isProxy = false;

    /**
     * @var array<string, mixed>
     */
    protected array $relations = [];

    /**
     * @var \Pairity\DAO\BaseDAO|null
     */
    protected ?\Pairity\DAO\BaseDAO $dao = null;

    /**
     * BaseDTO constructor.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        if ($this instanceof ProxyInterface) {
            $this->isProxy = true;
        }
    }

    /**
     * Ensure the object is fully loaded if it is a proxy.
     *
     * @return void
     */
    protected function ensureLoaded(): void
    {
        if ($this->isProxy && $this instanceof ProxyInterface && !$this->__isInitialized()) {
            $this->__load();
        }
    }

    /**
     * Set a relationship on the DTO.
     *
     * @param string $relation
     * @param mixed $value
     * @return void
     */
    public function setRelation(string $relation, mixed $value): void
    {
        $this->relations[$relation] = $value;
    }

    /**
     * Get a relationship from the DTO.
     *
     * @param string $relation
     * @return mixed
     */
    public function getRelation(string $relation): mixed
    {
        if (!$this->relationLoaded($relation) && $this->dao && method_exists($this->dao, $relation)) {
            $this->setRelation($relation, $this->dao->{$relation}($this)->getResults());
        }

        return $this->relations[$relation] ?? null;
    }

    /**
     * Check if a relationship is loaded.
     *
     * @param string $relation
     * @return bool
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Set the DAO instance for the DTO.
     *
     * @param \Pairity\DAO\BaseDAO $dao
     * @return void
     */
    public function setDao(\Pairity\DAO\BaseDAO $dao): void
    {
        $this->dao = $dao;
    }

    /**
     * Get the DAO instance for the DTO.
     *
     * @return \Pairity\DAO\BaseDAO|null
     */
    public function getDao(): ?\Pairity\DAO\BaseDAO
    {
        return $this->dao;
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->ensureLoaded();
        return $this->attributes;
    }

    /**
     * Set an attribute on the DTO.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;

        if ($this->dao) {
            $this->dao->getDb()->unitOfWork()->registerDirty($this);
        }
    }
}
