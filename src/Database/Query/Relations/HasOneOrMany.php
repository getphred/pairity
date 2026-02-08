<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Relations;

use Pairity\DTO\BaseDTO;

/**
 * Class HasOneOrMany
 *
 * Base class for HasOne and HasMany relationships.
 */
abstract class HasOneOrMany extends Relation
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $this->where($this->foreignKey, '=', $this->parent->{$this->localKey});
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array<BaseDTO> $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(fn($model) => $model->{$this->localKey}, $models);
        $this->whereIn($this->foreignKey, array_unique($keys));
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array<BaseDTO> $models
     * @param array<BaseDTO> $results
     * @param string $relation
     * @param string $type 'one' or 'many'
     * @return array<BaseDTO>
     */
    protected function matchOneOrMany(array $models, array $results, string $relation, string $type): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->localKey};

            if (isset($dictionary[$key])) {
                $value = $type === 'one' ? reset($dictionary[$key]) : $dictionary[$key];
                $model->setRelation($relation, $value);
            } else {
                $model->setRelation($relation, $type === 'one' ? null : []);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by foreign key.
     *
     * @param array<BaseDTO> $results
     * @return array<mixed, array<BaseDTO>>
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->foreignKey}][] = $result;
        }

        return $dictionary;
    }
}
