<?php

declare(strict_types=1);

namespace Pairity\Container;

use Pairity\Contracts\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Class Container
 *
 * A simple, lightweight dependency injection container.
 * Supports singleton and closure bindings, and basic auto-wiring via reflection.
 *
 * @package Pairity\Container
 */
class Container implements ContainerInterface
{
    /**
     * The registered bindings.
     *
     * @var array<string, array{concrete: mixed, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * The resolved singleton instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (RuntimeException $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    /**
     * @inheritDoc
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * @inheritDoc
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * @inheritDoc
     */
    public function make(string $abstract): mixed
    {
        // If the instance is already resolved and shared, return it.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // If the concrete is a closure or the same as abstract (needs instantiation)
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }

        // If it's a shared binding, store the instance.
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given concrete is buildable.
     *
     * @param mixed $concrete
     * @param string $abstract
     * @return bool
     */
    protected function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof \Closure;
    }

    /**
     * Determine if a given abstract is shared.
     *
     * @param string $abstract
     * @return bool
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param mixed $concrete
     * @return mixed
     * @throws RuntimeException
     */
    protected function build(mixed $concrete): mixed
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new RuntimeException($translator->trans('error.container_class_not_found', ['class' => $concrete]), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new RuntimeException($translator->trans('error.container_not_instantiable', ['class' => $concrete]));
        }

        $constructor = $reflector->getConstructor();

        // If there is no constructor, we can just instantiate the class.
        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param array $dependencies
     * @return array
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $results[] = $parameter->getDefaultValue();
                    continue;
                }

                $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
                throw new RuntimeException($translator->trans('error.container_unresolvable', [
                    'parameter' => $parameter->getName(),
                    'class' => $parameter->getDeclaringClass()->getName()
                ]));
            }

            $results[] = $this->make($type->getName());
        }

        return $results;
    }
}
