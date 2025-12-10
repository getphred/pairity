<?php

namespace Pairity\Contracts;

interface DtoInterface
{
    /**
     * Convert DTO to array.
     * When $deep is true (default), convert any nested DTO relations to arrays as well.
     *
     * @param bool $deep
     * @return array<string,mixed>
     */
    public function toArray(bool $deep = true): array;
}
