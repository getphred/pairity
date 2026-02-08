<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Grammars;

use Pairity\Database\Query\Grammar;

/**
 * Class SqliteGrammar
 */
class SqliteGrammar extends Grammar
{
    /**
     * @inheritDoc
     */
    public function compileUpsert(\Pairity\Database\Query\Builder $query, array $values, array $uniqueBy, ?array $update = null): string
    {
        $columns = $this->columnize(array_keys($values[0]));
        $table = $this->wrapTable((string)$query->from);

        $sql = "insert into {$table} ({$columns}) values ";

        $parameters = array_map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        }, $values);

        $sql .= implode(', ', $parameters);

        if (is_null($update)) {
            return $sql . " on conflict do nothing";
        }

        $sql .= " on conflict (" . $this->columnize($uniqueBy) . ") do update set ";

        $columns = [];
        foreach ($update as $key => $value) {
            if (is_numeric($key)) {
                $columns[] = $this->wrap($value) . ' = excluded.' . $this->wrap($value);
            } else {
                $columns[] = $this->wrap($key) . ' = ?';
            }
        }

        return $sql . implode(', ', $columns);
    }
}
