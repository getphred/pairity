<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Relations;

use Pairity\Database\Query\Builder;
use Pairity\DTO\BaseDTO;
use Pairity\DAO\BaseDAO;

/**
 * Class Relation
 *
 * Base class for all relationship types.
 */
abstract class Relation extends Builder
{
    /**
     * The parent DTO instance.
     */
    protected BaseDTO $parent;

    /**
     * The related DAO instance.
     */
    protected BaseDAO $related;

    /**
     * The foreign key on the relationship.
     */
    protected string $foreignKey;

    /**
     * The local key on the relationship.
     */
    protected string $localKey;

    /**
     * Relation constructor.
     *
     * @param Builder $query
     * @param BaseDTO $parent
     * @param BaseDAO $related
     * @param string $foreignKey
     * @param string $localKey
     */
    public function __construct(Builder $query, BaseDTO $parent, BaseDAO $related, string $foreignKey, string $localKey)
    {
        parent::__construct($query->getDb(), $query->getConnection(), $query->getGrammar());

        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->from($related->getTable());
        
        if ($dtoClass = $related->getDtoClass()) {
            $this->setModel($dtoClass, $related);
        }

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array<BaseDTO> $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     *
     * @param array<BaseDTO> $models
     * @param string $relation
     * @return array<BaseDTO>
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array<BaseDTO> $models
     * @param array<BaseDTO> $results
     * @param string $relation
     * @return array<BaseDTO>
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults(): mixed;

    /**
     * Get the parent model of the relation.
     *
     * @return BaseDTO
     */
    public function getParent(): BaseDTO
    {
        return $this->parent;
    }

    /**
     * Get the related model of the relation.
     *
     * @return BaseDAO
     */
    public function getRelated(): BaseDAO
    {
        return $this->related;
    }
}
