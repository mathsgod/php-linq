<?php

declare(strict_types=1);

namespace PhpLinq;

use IteratorAggregate;
use PhpLinq\Expression\Expression;
use PhpLinq\Expression\ValueExpression;
use Traversable;

/** @template T @extends IteratorAggregate<int, T> */
interface IQueryable extends IteratorAggregate
{
    public function expression(): Expression;

    public function provider(): QueryProvider;

    public function where(ValueExpression $predicate): self;

    public function select(ValueExpression $selector): self;

    public function orderBy(ValueExpression $keySelector): self;

    public function orderByDescending(ValueExpression $keySelector): self;

    public function skip(int $count): self;

    public function take(int $count): self;

    public function count(): int;

    public function any(?ValueExpression $predicate = null): bool;

    public function first(?ValueExpression $predicate = null): mixed;

    /** @return list<T> */
    public function toArray(): array;

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable;
}
