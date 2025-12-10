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
        $this->attributes = $attributes;
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
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
            return $this->attributes;
        }

        $result = [];
        foreach ($this->attributes as $key => $value) {
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
}
