<?php

declare(strict_types=1);

namespace PhpLinq\Tests;

use PhpLinq\Expr;
use PhpLinq\InMemoryQueryProvider;
use PhpLinq\Queryable;
use PHPUnit\Framework\TestCase;

final class QueryableTest extends TestCase
{
    private Queryable $users;

    protected function setUp(): void
    {
        $provider = new InMemoryQueryProvider([
            'users' => [
                ['id' => 1, 'name' => 'Ada', 'active' => true, 'age' => 36],
                ['id' => 2, 'name' => 'Bob', 'active' => false, 'age' => 22],
                ['id' => 3, 'name' => 'Cara', 'active' => true, 'age' => 29],
                ['id' => 4, 'name' => 'Dan', 'active' => true, 'age' => 41],
            ],
        ]);

        $this->users = Queryable::from($provider, 'users');
    }

    public function testPipelineQuery(): void
    {
        $names = $this->users
            ->where(Expr::both(
                Expr::eq(Expr::field('active'), true),
                Expr::gte(Expr::field('age'), 30),
            ))
            ->orderByDescending(Expr::field('age'))
            ->select(Expr::field('name'))
            ->take(2);

        self::assertSame(['Dan', 'Ada'], $names->toArray());
    }

    public function testCount(): void
    {
        self::assertSame(
            3,
            $this->users->where(Expr::eq(Expr::field('active'), true))->count(),
        );
    }

    public function testAnyWithPredicate(): void
    {
        self::assertTrue($this->users->any(Expr::lt(Expr::field('age'), 25)));
    }

    public function testFirstWithPredicate(): void
    {
        $user = $this->users->first(Expr::eq(Expr::field('id'), 3));

        self::assertSame('Cara', $user['name']);
    }

    public function testSkipAndTake(): void
    {
        self::assertSame(
            [2, 3],
            $this->users
                ->select(Expr::field('id'))
                ->skip(1)
                ->take(2)
                ->toArray(),
        );
    }
}
