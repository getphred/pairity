<?php

declare(strict_types=1);

namespace Pairity\Database\Query\Grammars;

use Pairity\Database\Query\Grammar;

/**
 * Class SqlServerGrammar
 */
class SqlServerGrammar extends Grammar
{
    /**
     * Wrap a value in keyword identifiers.
     *
     * @param mixed $value
     * @return string
     */
    public function wrap(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if ($value === '*') {
            return $value;
        }

        return '[' . str_replace(']', ']]', (string)$value) . ']';
    }
}
