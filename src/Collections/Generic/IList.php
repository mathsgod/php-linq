<?php

declare(strict_types=1);

namespace PhpLinq\Collections\Generic;

use ArrayAccess;

/** @template T @extends ICollection<T> @extends ArrayAccess<int, T> */
interface IList extends ICollection, ArrayAccess
{
    /** @return T */
    public function get(int $index): mixed;

    /** @param T $item */
    public function set(int $index, mixed $item): void;

    /** @param T $item */
    public function insert(int $index, mixed $item): void;

    public function removeAt(int $index): void;

    /** @param T $item */
    public function indexOf(mixed $item): int;
}
