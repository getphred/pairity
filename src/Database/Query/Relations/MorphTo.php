<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Relations;

use Pairity\DTO\BaseDTO;
use Pairity\DAO\BaseDAO;
use Pairity\Database\Query\Builder;

/**
 * Class MorphTo
 *
 * Represents a polymorphic many-to-one relationship.
 */
class MorphTo extends Relation
{
    /**
     * The type column on the relationship.
     */
    protected string $morphType;

    /**
     * MorphTo constructor.
     *
     * @param Builder $query
     * @param BaseDTO $parent
     * @param BaseDAO $related
     * @param string $foreignKey
     * @param string $localKey
     * @param string $morphType
     */
    public function __construct(Builder $query, BaseDTO $parent, BaseDAO $related, string $foreignKey, string $localKey, string $morphType)
    {
        $this->morphType = $morphType;
        parent::__construct($query, $parent, $related, $foreignKey, $localKey);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $this->where($this->localKey, '=', $this->parent->{$this->foreignKey})
             ->where($this->morphType, '=', $this->getMorphTypeForParent());
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array<BaseDTO> $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(fn($model) => $model->{$this->foreignKey}, $models);
        $this->whereIn($this->localKey, array_filter(array_unique($keys)));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param array $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$this->localKey}] = $result;
        }

        foreach ($models as $model) {
            $key = $model->{$this->foreignKey};
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults(): mixed
    {
        return $this->first();
    }

    /**
     * Get the morph type for the parent model.
     *
     * @return string
     */
    protected function getMorphTypeForParent(): string
    {
        return $this->parent->getDao()->getOption('morph') ?? get_class($this->parent);
    }
}
