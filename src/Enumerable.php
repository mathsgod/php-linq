<?php

declare(strict_types=1);

namespace PhpLinq;

use Closure;
use Traversable;

/** @template T @implements IEnumerable<T> */
class Enumerable implements IEnumerable
{
    /** @var Closure(): iterable<T> */
    private readonly Closure $factory;

    /** @param callable(): iterable<T> $factory */
    protected function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    /** @template TSource @param iterable<TSource> $source @return IEnumerable<TSource> */
    public static function from(iterable $source): IEnumerable
    {
        if ($source instanceof IEnumerable) {
            return $source;
        }
        return new self(static fn (): iterable => $source);
    }

    /** @template TSource @param callable(): iterable<TSource> $factory @return IEnumerable<TSource> */
    public static function defer(callable $factory): IEnumerable
    {
        return new self($factory);
    }

    public function getIterator(): Traversable
    {
        yield from ($this->factory)();
    }

    public function select(callable $selector): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $selector): iterable {
            foreach ($source as $item) {
                yield $selector($item);
            }
        });
    }

    public function where(callable $predicate): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $predicate): iterable {
            foreach ($source as $item) {
                if ($predicate($item)) {
                    yield $item;
                }
            }
        });
    }

    public function selectMany(callable $selector): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $selector): iterable {
            foreach ($source as $item) {
                yield from $selector($item);
            }
        });
    }

    public function orderBy(callable $keySelector): IOrderedEnumerable
    {
        return new OrderedEnumerable($this, [[$keySelector, false]]);
    }

    public function orderByDescending(callable $keySelector): IOrderedEnumerable
    {
        return new OrderedEnumerable($this, [[$keySelector, true]]);
    }

    public function skip(int $count): IEnumerable
    {
        self::assertNonNegative($count);
        $source = $this;
        return new self(static function () use ($source, $count): iterable {
            $index = 0;
            foreach ($source as $item) {
                if ($index++ >= $count) {
                    yield $item;
                }
            }
        });
    }

    public function take(int $count): IEnumerable
    {
        self::assertNonNegative($count);
        $source = $this;
        return new self(static function () use ($source, $count): iterable {
            if ($count === 0) {
                return;
            }
            $taken = 0;
            foreach ($source as $item) {
                yield $item;
                if (++$taken === $count) {
                    return;
                }
            }
        });
    }

    public function skipWhile(callable $predicate): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $predicate): iterable {
            $yielding = false;
            foreach ($source as $item) {
                $yielding = $yielding || !$predicate($item);
                if ($yielding) {
                    yield $item;
                }
            }
        });
    }

    public function takeWhile(callable $predicate): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $predicate): iterable {
            foreach ($source as $item) {
                if (!$predicate($item)) {
                    return;
                }
                yield $item;
            }
        });
    }

    public function takeLast(int $count): IEnumerable
    {
        self::assertNonNegative($count);
        $source = $this;
        return new self(static function () use ($source, $count): iterable {
            if ($count === 0) {
                return;
            }
            $buffer = [];
            foreach ($source as $item) {
                $buffer[] = $item;
                if (count($buffer) > $count) {
                    array_shift($buffer);
                }
            }
            yield from $buffer;
        });
    }

    public function distinct(?callable $keySelector = null): IEnumerable
    {
        $source = $this;
        $keySelector ??= static fn (mixed $item): mixed => $item;
        return new self(static function () use ($source, $keySelector): iterable {
            $seen = [];
            foreach ($source as $item) {
                $key = $keySelector($item);
                foreach ($seen as $existing) {
                    if ($existing === $key) {
                        continue 2;
                    }
                }
                $seen[] = $key;
                yield $item;
            }
        });
    }

    public function append(mixed $value): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $value): iterable {
            yield from $source;
            yield $value;
        });
    }

    public function prepend(mixed $value): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $value): iterable {
            yield $value;
            yield from $source;
        });
    }

    public function concat(iterable $other): IEnumerable
    {
        $source = $this;
        return new self(static function () use ($source, $other): iterable {
            yield from $source;
            yield from $other;
        });
    }

    public function reverse(): IEnumerable
    {
        $source = $this;
        return new self(static fn (): iterable => array_reverse($source->toArray()));
    }

    public function chunk(int $size): IEnumerable
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be greater than zero.');
        }
        $source = $this;
        return new self(static function () use ($source, $size): iterable {
            $chunk = [];
            foreach ($source as $item) {
                $chunk[] = $item;
                if (count($chunk) === $size) {
                    yield $chunk;
                    $chunk = [];
                }
            }
            if ($chunk !== []) {
                yield $chunk;
            }
        });
    }

    public function count(?callable $predicate = null): int
    {
        $count = 0;
        foreach ($this as $item) {
            if ($predicate === null || $predicate($item)) {
                ++$count;
            }
        }
        return $count;
    }

    public function any(?callable $predicate = null): bool
    {
        foreach ($this as $item) {
            if ($predicate === null || $predicate($item)) {
                return true;
            }
        }
        return false;
    }

    public function all(callable $predicate): bool
    {
        foreach ($this as $item) {
            if (!$predicate($item)) {
                return false;
            }
        }
        return true;
    }

    public function contains(mixed $value): bool
    {
        return $this->any(static fn (mixed $item): bool => $item === $value);
    }

    public function first(?callable $predicate = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->firstOrDefault($predicate, $sentinel);
        if ($value === $sentinel) {
            throw new \UnderflowException('The sequence contains no matching element.');
        }
        return $value;
    }

    public function firstOrDefault(?callable $predicate = null, mixed $default = null): mixed
    {
        foreach ($this as $item) {
            if ($predicate === null || $predicate($item)) {
                return $item;
            }
        }
        return $default;
    }

    public function last(?callable $predicate = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->lastOrDefault($predicate, $sentinel);
        if ($value === $sentinel) {
            throw new \UnderflowException('The sequence contains no matching element.');
        }
        return $value;
    }

    public function lastOrDefault(?callable $predicate = null, mixed $default = null): mixed
    {
        $found = false;
        $result = $default;
        foreach ($this as $item) {
            if ($predicate === null || $predicate($item)) {
                $found = true;
                $result = $item;
            }
        }
        return $found ? $result : $default;
    }

    public function single(?callable $predicate = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->singleOrDefault($predicate, $sentinel);
        if ($value === $sentinel) {
            throw new \UnderflowException('The sequence contains no matching element.');
        }
        return $value;
    }

    public function singleOrDefault(?callable $predicate = null, mixed $default = null): mixed
    {
        $found = false;
        $result = $default;
        foreach ($this as $item) {
            if ($predicate !== null && !$predicate($item)) {
                continue;
            }
            if ($found) {
                throw new \UnexpectedValueException('The sequence contains more than one matching element.');
            }
            $found = true;
            $result = $item;
        }
        return $found ? $result : $default;
    }

    public function sum(?callable $selector = null): int|float
    {
        $selector ??= static fn (mixed $item): mixed => $item;
        $sum = 0;
        foreach ($this as $item) {
            $sum += $selector($item);
        }
        return $sum;
    }

    public function average(?callable $selector = null): int|float
    {
        $selector ??= static fn (mixed $item): mixed => $item;
        $sum = 0;
        $count = 0;
        foreach ($this as $item) {
            $sum += $selector($item);
            ++$count;
        }
        if ($count === 0) {
            throw new \UnderflowException('Cannot average an empty sequence.');
        }
        return $sum / $count;
    }

    public function aggregate(callable $accumulator, mixed $seed = null): mixed
    {
        $hasSeed = func_num_args() >= 2;
        $result = $seed;
        foreach ($this as $item) {
            if (!$hasSeed) {
                $result = $item;
                $hasSeed = true;
                continue;
            }
            $result = $accumulator($result, $item);
        }
        if (!$hasSeed) {
            throw new \UnderflowException('Cannot aggregate an empty sequence without a seed.');
        }
        return $result;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    private static function assertNonNegative(int $count): void
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('Count cannot be negative.');
        }
    }
}
