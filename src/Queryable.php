<?php

declare(strict_types=1);

namespace PhpLinq;

use PhpLinq\Expression\Expression;
use PhpLinq\Expression\MethodCallExpression;
use PhpLinq\Expression\SourceExpression;
use PhpLinq\Expression\ValueExpression;
use Traversable;

/** @template T @implements IQueryable<T> */
final readonly class Queryable implements IQueryable
{
    public function __construct(
        private QueryProvider $queryProvider,
        private Expression $queryExpression,
    ) {
    }

    public static function from(QueryProvider $provider, string $source): self
    {
        return new self($provider, new SourceExpression($source));
    }

    public function expression(): Expression
    {
        return $this->queryExpression;
    }

    public function provider(): QueryProvider
    {
        return $this->queryProvider;
    }

    public function where(ValueExpression $predicate): self
    {
        return $this->call('where', ['predicate' => $predicate]);
    }

    public function select(ValueExpression $selector): self
    {
        return $this->call('select', ['selector' => $selector]);
    }

    public function orderBy(ValueExpression $keySelector): self
    {
        return $this->call('orderBy', ['keySelector' => $keySelector, 'descending' => false]);
    }

    public function orderByDescending(ValueExpression $keySelector): self
    {
        return $this->call('orderBy', ['keySelector' => $keySelector, 'descending' => true]);
    }

    public function skip(int $count): self
    {
        return $this->nonNegativeCall('skip', $count);
    }

    public function take(int $count): self
    {
        return $this->nonNegativeCall('take', $count);
    }

    public function count(): int
    {
        return $this->queryProvider->execute($this->terminal('count'));
    }

    public function any(?ValueExpression $predicate = null): bool
    {
        $query = $predicate === null ? $this : $this->where($predicate);
        return $this->queryProvider->execute($query->terminal('any'));
    }

    public function first(?ValueExpression $predicate = null): mixed
    {
        $query = $predicate === null ? $this : $this->where($predicate);
        return $this->queryProvider->execute($query->terminal('first'));
    }

    public function asEnumerable(): IEnumerable
    {
        return Enumerable::from($this);
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    public function getIterator(): Traversable
    {
        $result = $this->queryProvider->execute($this->queryExpression);
        if (!is_iterable($result)) {
            throw new \LogicException('The provider did not return an iterable query result.');
        }
        yield from $result;
    }

    private function call(string $method, array $arguments): self
    {
        return new self(
            $this->queryProvider,
            new MethodCallExpression($this->queryExpression, $method, $arguments),
        );
    }

    private function nonNegativeCall(string $method, int $count): self
    {
        if ($count < 0) {
            throw new \InvalidArgumentException("{$method} count cannot be negative.");
        }
        return $this->call($method, ['count' => $count]);
    }

    private function terminal(string $method): MethodCallExpression
    {
        return new MethodCallExpression($this->queryExpression, $method);
    }
}
