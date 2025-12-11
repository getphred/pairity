<?php

namespace Pairity\Model\Casting;

interface CasterInterface
{
    /** Convert a raw storage value (from DB/driver) to a PHP value for the DTO. */
    public function fromStorage(mixed $value): mixed;

    /** Convert a PHP value to a storage value suitable for persistence. */
    public function toStorage(mixed $value): mixed;
}
