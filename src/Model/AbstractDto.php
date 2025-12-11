<?php

namespace Pairity\Model;

use Pairity\Contracts\DtoInterface;

abstract class AbstractDto implements DtoInterface
{
    /** @var array<string,mixed> */
    protected array $attributes = [];

    /** @param array<string,mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        // Apply mutators if defined
        foreach ($attributes as $key => $value) {
            $method = $this->mutatorMethod($key);
            if (method_exists($this, $method)) {
                // set{Name}Attribute($value): mixed
                $value = $this->{$method}($value);
            }
            $this->attributes[$key] = $value;
        }
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function __get(string $name): mixed
    {
        $value = $this->attributes[$name] ?? null;
        $method = $this->accessorMethod($name);
        if (method_exists($this, $method)) {
            // get{Name}Attribute($value): mixed
            return $this->{$method}($value);
        }
        return $value;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Attach a loaded relation or transient attribute to the DTO.
     * Intended for internal ORM use (eager/lazy loading).
     */
    public function setRelation(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @return array<string,mixed> */
    public function toArray(bool $deep = true): array
    {
        if (!$deep) {
            // Apply accessors at top level for scalar attributes
            $out = [];
            foreach ($this->attributes as $key => $value) {
                $method = $this->accessorMethod($key);
                if (method_exists($this, $method)) {
                    $out[$key] = $this->{$method}($value);
                } else {
                    $out[$key] = $value;
                }
            }
            return $out;
        }

        $result = [];
        foreach ($this->attributes as $key => $value) {
            // Apply accessor before deep conversion for scalars/arrays
            $method = $this->accessorMethod($key);
            if (method_exists($this, $method)) {
                $value = $this->{$method}($value);
            }
            if ($value instanceof DtoInterface) {
                $result[$key] = $value->toArray(true);
            } elseif (is_array($value)) {
                // Map arrays, converting any DTO elements to arrays as well
                $result[$key] = array_map(function ($item) {
                    if ($item instanceof DtoInterface) {
                        return $item->toArray(true);
                    }
                    return $item;
                }, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function accessorMethod(string $key): string
    {
        return 'get' . $this->studly($key) . 'Attribute';
    }

    private function mutatorMethod(string $key): string
    {
        return 'set' . $this->studly($key) . 'Attribute';
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}
