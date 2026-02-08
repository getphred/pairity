<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Relations;

/**
 * Class HasOne
 *
 * Represents a one-to-one relationship.
 */
class HasOne extends HasOneOrMany
{
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
        return $this->matchOneOrMany($models, $results, $relation, 'one');
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
}
