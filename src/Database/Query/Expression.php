<?php

declare(strict_types=1);

namespace Pairity\Database\Query;

/**
 * Class Expression
 *
 * Represents a raw SQL expression that should not be quoted.
 */
class Expression
{
    /**
     * Expression constructor.
     *
     * @param string $value
     */
    public function __construct(
        protected string $value
    ) {
    }

    /**
     * Get the raw expression value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the raw expression value.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getValue();
    }
}
