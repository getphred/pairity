<?php

declare(strict_types=1);

namespace Pairity\Database\Factories;

use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\DTO\BaseDTO;
use Pairity\DAO\BaseDAO;

/**
 * Class Factory
 *
 * Base class for all model factories.
 */
abstract class Factory
{
    /**
     * @var int
     */
    protected int $count = 1;

    /**
     * @var array
     */
    protected array $states = [];

    /**
     * Factory constructor.
     *
     * @param DatabaseManagerInterface $db
     */
    public function __construct(
        protected DatabaseManagerInterface $db
    ) {
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    /**
     * Get the model class name.
     *
     * @return string
     */
    abstract public function model(): string;

    /**
     * Get the DAO class name.
     *
     * @return string
     */
    abstract public function dao(): string;

    /**
     * Set the number of models to create.
     *
     * @param int $count
     * @return $this
     */
    public function count(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Create models and save them to the database.
     *
     * @param array<string, mixed> $attributes
     * @return mixed
     */
    public function create(array $attributes = []): mixed
    {
        $results = [];

        for ($i = 0; $i < $this->count; $i++) {
            $data = array_merge($this->definition(), $this->applyStates(), $attributes);
            $dtoClass = $this->model();
            $dto = new $dtoClass($data);
            
            $daoClass = $this->dao();
            /** @var BaseDAO $dao */
            $dao = $this->db->getContainer()->get($daoClass);
            $dto->setDao($dao);
            
            $dao->save($dto);
            $results[] = $dto;
        }

        return $this->count === 1 ? $results[0] : $results;
    }

    /**
     * Make models without saving them to the database.
     *
     * @param array<string, mixed> $attributes
     * @return mixed
     */
    public function make(array $attributes = []): mixed
    {
        $results = [];

        for ($i = 0; $i < $this->count; $i++) {
            $data = array_merge($this->definition(), $this->applyStates(), $attributes);
            $dtoClass = $this->model();
            $results[] = new $dtoClass($data);
        }

        return $this->count === 1 ? $results[0] : $results;
    }

    /**
     * Apply the registered states.
     *
     * @return array<string, mixed>
     */
    protected function applyStates(): array
    {
        $data = [];
        foreach ($this->states as $state) {
            if (is_callable($state)) {
                $data = array_merge($data, $state($this->definition()));
            } else {
                $data = array_merge($data, $state);
            }
        }
        return $data;
    }

    /**
     * Register a new state.
     *
     * @param array|callable $state
     * @return $this
     */
    public function state(array|callable $state): self
    {
        $this->states[] = $state;
        return $this;
    }
}
