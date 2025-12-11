<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;
use Pairity\Model\Casting\CasterInterface;

final class CastersAndAccessorsSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testCustomCasterAndDtoAccessorsMutators(): void
    {
        $conn = $this->conn();
        // simple schema
        $conn->execute('CREATE TABLE widgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            price_cents INTEGER,
            meta TEXT
        )');

        // Custom caster for money cents <-> Money object (array for simplicity)
        $moneyCasterClass = new class implements CasterInterface {
            public function fromStorage(mixed $value): mixed { return ['cents' => (int)$value]; }
            public function toStorage(mixed $value): mixed {
                if (is_array($value) && isset($value['cents'])) { return (int)$value['cents']; }
                return (int)$value;
            }
        };
        $moneyCasterFqcn = get_class($moneyCasterClass);

        // DTO with accessor/mutator for name (capitalize on get, trim on set)
        $Dto = new class([]) extends AbstractDto {
            protected function getNameAttribute($value): mixed { return is_string($value) ? strtoupper($value) : $value; }
            protected function setNameAttribute($value): mixed { return is_string($value) ? trim($value) : $value; }
        };
        $dtoClass = get_class($Dto);

        $Dao = new class($conn, $dtoClass, $moneyCasterFqcn) extends AbstractDao {
            private string $dto; private string $caster;
            public function __construct($c, string $dto, string $caster) { parent::__construct($c); $this->dto = $dto; $this->caster = $caster; }
            public function getTable(): string { return 'widgets'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array
            {
                return [
                    'primaryKey' => 'id',
                    'columns' => [
                        'id' => ['cast' => 'int'],
                        'name' => ['cast' => 'string'],
                        'price_cents' => ['cast' => $this->caster], // custom caster
                        'meta' => ['cast' => 'json'],
                    ],
                ];
            }
        };

        $dao = new $Dao($conn, $dtoClass, $moneyCasterFqcn);

        // Insert with mutator (name will be trimmed) and caster (price array -> storage int)
        $created = $dao->insert([
            'name' => '  gizmo  ',
            'price_cents' => ['cents' => 1234],
            'meta' => ['color' => 'red']
        ]);
        $arr = $created->toArray(false);
        $this->assertSame('GIZMO', $arr['name']); // accessor uppercases
        $this->assertIsArray($arr['price_cents']);
        $this->assertSame(1234, $arr['price_cents']['cents']); // fromStorage via caster
        $this->assertSame('red', $arr['meta']['color']);

        $id = $arr['id'];

        // Update with caster value
        $updated = $dao->update($id, ['price_cents' => ['cents' => 1999]]);
        $this->assertSame(1999, $updated->toArray(false)['price_cents']['cents']);

        // Verify raw storage is int (select directly)
        $raw = $conn->query('SELECT price_cents, meta, name FROM widgets WHERE id = :id', ['id' => $id])[0] ?? [];
        $this->assertSame(1999, (int)$raw['price_cents']);
        $this->assertIsString($raw['meta']);
        // Raw storage may preserve whitespace; DTO mutator trims on set for DTO, not necessarily at storage layer
        $this->assertSame('gizmo', strtolower(trim((string)$raw['name'])));
    }
}
