<?php

declare(strict_types=1);

namespace PhpLinq\Collections\Generic;

/** @template TKey @template TValue */
final readonly class KeyValuePair
{
    /** @param TKey $key @param TValue $value */
    public function __construct(
        public mixed $key,
        public mixed $value,
    ) {
    }
}
