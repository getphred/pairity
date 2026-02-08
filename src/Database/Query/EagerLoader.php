<?php

declare(strict_types=1);

namespace Pairity\Database\Query;

use Pairity\DTO\BaseDTO;
use Pairity\Database\Query\Relations\Relation;
use Pairity\Exceptions\DatabaseException;

/**
 * Class EagerLoader
 *
 * Handles batch loading of relationships to solve N+1 query problem.
 */
class EagerLoader
{
    /**
     * Eager load the relationships on a set of models.
     *
     * @param array<BaseDTO> $models
     * @param array $eagerLoad
     * @return array<BaseDTO>
     */
    public function load(array $models, array $eagerLoad): array
    {
        if (empty($models) || empty($eagerLoad)) {
            return $models;
        }

        foreach ($eagerLoad as $name => $constraints) {
            // Check for nested relations
            if (str_contains($name, '.')) {
                $models = $this->loadNestedRelation($models, $name, $constraints);
                continue;
            }

            $models = $this->loadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Load a single relationship on a set of models.
     *
     * @param array<BaseDTO> $models
     * @param string $name
     * @param \Closure $constraints
     * @return array<BaseDTO>
     * @throws DatabaseException
     */
    protected function loadRelation(array $models, string $name, \Closure $constraints): array
    {
        $relation = $this->getRelation($models, $name);

        // Reset wheres because the relation constructor adds wheres for the single parent
        $relation->wheres = [];
        $relation->setBindings([], 'where');

        // Apply constraints
        $constraints($relation);

        // Add eager constraints (usually WHERE IN [ids])
        $relation->addEagerConstraints($models);

        // Initialize the relation on all models (e.g. set to null or empty array)
        $models = $relation->initRelation($models, $name);

        // Get the results
        $results = $relation->get();

        // Match results back to parents
        return $relation->match($models, $results, $name);
    }

    /**
     * Load a nested relationship.
     *
     * @param array<BaseDTO> $models
     * @param string $name
     * @param \Closure $constraints
     * @return array<BaseDTO>
     */
    protected function loadNestedRelation(array $models, string $name, \Closure $constraints): array
    {
        $parts = explode('.', $name);
        $first = array_shift($parts);
        $rest = implode('.', $parts);

        // Load the first level if not already loaded
        $models = $this->loadRelation($models, $first, function () {});

        // Gather all the related models from the first level
        $results = [];
        foreach ($models as $model) {
            $relationValue = $model->getRelation($first);
            if (is_array($relationValue)) {
                $results = array_merge($results, $relationValue);
            } elseif ($relationValue instanceof BaseDTO) {
                $results[] = $relationValue;
            }
        }

        // Recursively load the rest of the path on the gathered results
        if (!empty($results)) {
            $this->load($results, [$rest => $constraints]);
        }

        return $models;
    }

    /**
     * Get the relation instance for the given models and name.
     *
     * @param array<BaseDTO> $models
     * @param string $name
     * @return Relation
     * @throws DatabaseException
     */
    protected function getRelation(array $models, string $name): Relation
    {
        $model = reset($models);
        
        $dao = $model->getDao(); 
        
        if (!method_exists($dao, $name)) {
            $translator = $dao->getDb()->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
            throw new DatabaseException(
                $translator->trans('error.relation_not_found', ['relation' => $name]),
                0,
                null,
                ['relation' => $name, 'dao' => get_class($dao)]
            );
        }

        return $dao->{$name}($model);
    }
}
