<?php

namespace Pairity\Schema;

class ColumnDefinition
{
    public string $name;
    public string $type;
    public ?int $length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $unsigned = false;
    public bool $nullable = false;
    public mixed $default = null;
    public bool $autoIncrement = false;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function length(int $length): static { $this->length = $length; return $this; }
    public function precision(int $precision, int $scale = 0): static { $this->precision = $precision; $this->scale = $scale; return $this; }
    public function unsigned(bool $flag = true): static { $this->unsigned = $flag; return $this; }
    public function nullable(bool $flag = true): static { $this->nullable = $flag; return $this; }
    public function default(mixed $value): static { $this->default = $value; return $this; }
    public function autoIncrement(bool $flag = true): static { $this->autoIncrement = $flag; return $this; }
}
