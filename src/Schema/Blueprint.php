<?php

namespace Pairity\Schema;

class Blueprint
{
    public string $table;
    public bool $creating = false;
    public bool $altering = false;
    /** @var array<int,ColumnDefinition> */
    public array $columns = [];
    /** @var array<int,string> */
    public array $primary = [];
    /** @var array<int,array{columns:array<int,string>,name:?string}> */
    public array $uniques = [];
    /** @var array<int,array{columns:array<int,string>,name:?string}> */
    public array $indexes = [];

    // Alter support (MVP)
    /** @var array<int,string> */
    public array $dropColumns = [];
    /** @var array<int,array{from:string,to:string}> */
    public array $renameColumns = [];
    public ?string $renameTo = null;
    /** @var array<int,string> */
    public array $dropUniqueNames = [];
    /** @var array<int,string> */
    public array $dropIndexNames = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function create(): void { $this->creating = true; }
    public function alter(): void { $this->altering = true; }

    // Column helpers
    public function increments(string $name = 'id'): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'increments');
        $col->autoIncrement(true);
        $this->columns[] = $col;
        $this->primary([$name]);
        return $col;
    }

    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'bigincrements');
        $col->autoIncrement(true);
        $this->columns[] = $col;
        $this->primary([$name]);
        return $col;
    }

    public function integer(string $name, bool $unsigned = false): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'integer');
        $col->unsigned($unsigned);
        $this->columns[] = $col;
        return $col;
    }

    public function bigInteger(string $name, bool $unsigned = false): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'biginteger');
        $col->unsigned($unsigned);
        $this->columns[] = $col;
        return $col;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'string');
        $col->length($length);
        $this->columns[] = $col;
        return $col;
    }

    public function text(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'text');
        $this->columns[] = $col;
        return $col;
    }

    public function boolean(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'boolean');
        $this->columns[] = $col;
        return $col;
    }

    public function json(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'json');
        $this->columns[] = $col;
        return $col;
    }

    public function datetime(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'datetime');
        $this->columns[] = $col;
        return $col;
    }

    public function decimal(string $name, int $precision, int $scale = 0): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'decimal');
        $col->precision($precision, $scale);
        $this->columns[] = $col;
        return $col;
    }

    public function timestamps(string $created = 'created_at', string $updated = 'updated_at'): void
    {
        $this->datetime($created)->nullable();
        $this->datetime($updated)->nullable();
    }

    // Index helpers
    /** @param array<int,string> $columns */
    public function primary(array $columns): void
    {
        $this->primary = $columns;
    }

    /** @param array<int,string> $columns */
    public function unique(array $columns, ?string $name = null): void
    {
        $this->uniques[] = ['columns' => $columns, 'name' => $name];
    }

    /** @param array<int,string> $columns */
    public function index(array $columns, ?string $name = null): void
    {
        $this->indexes[] = ['columns' => $columns, 'name' => $name];
    }

    // Alter helpers (MVP)
    /** @param array<int,string> $names */
    public function dropColumn(string ...$names): void
    {
        foreach ($names as $n) {
            if ($n !== '') $this->dropColumns[] = $n;
        }
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renameColumns[] = ['from' => $from, 'to' => $to];
    }

    public function rename(string $newName): void
    {
        $this->renameTo = $newName;
    }

    public function dropUnique(string $name): void
    {
        $this->dropUniqueNames[] = $name;
    }

    public function dropIndex(string $name): void
    {
        $this->dropIndexNames[] = $name;
    }
}
