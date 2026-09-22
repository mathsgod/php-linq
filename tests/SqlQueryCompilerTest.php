<?php

declare(strict_types=1);

namespace PhpLinq\Tests;

use PhpLinq\Expr;
use PhpLinq\Expression\MethodCallExpression;
use PhpLinq\InMemoryQueryProvider;
use PhpLinq\Queryable;
use PhpLinq\Sql\MySqlDialect;
use PhpLinq\Sql\PostgreSqlDialect;
use PhpLinq\Sql\SqlDialect;
use PhpLinq\Sql\SqlQueryCompiler;
use PhpLinq\Sql\SqliteDialect;
use PhpLinq\Sql\SqlServerDialect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlQueryCompilerTest extends TestCase
{
    /** @return iterable<string, array{SqlDialect, string}> */
    public static function dialects(): iterable
    {
        yield 'MySQL' => [
            new MySqlDialect(),
            'SELECT `name` AS `__linq_value` FROM `users` WHERE (`active` = :p0) '
                .'ORDER BY `age` DESC LIMIT 10 OFFSET 20',
        ];
        yield 'SQL Server' => [
            new SqlServerDialect(),
            'SELECT [name] AS [__linq_value] FROM [users] WHERE ([active] = :p0) '
                .'ORDER BY [age] DESC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY',
        ];
        yield 'PostgreSQL' => [
            new PostgreSqlDialect(),
            'SELECT "name" AS "__linq_value" FROM "users" WHERE ("active" = :p0) '
                .'ORDER BY "age" DESC LIMIT 10 OFFSET 20',
        ];
        yield 'SQLite' => [
            new SqliteDialect(),
            'SELECT "name" AS "__linq_value" FROM "users" WHERE ("active" = :p0) '
                .'ORDER BY "age" DESC LIMIT 10 OFFSET 20',
        ];
    }

    #[DataProvider('dialects')]
    public function testCompilesQueryForEveryDialect(SqlDialect $dialect, string $expected): void
    {
        $query = $this->query()
            ->where(Expr::eq(Expr::field('active'), true))
            ->orderByDescending(Expr::field('age'))
            ->skip(20)
            ->take(10)
            ->select(Expr::field('name'));

        $compiled = (new SqlQueryCompiler($dialect))->compile($query->expression());

        self::assertSame($expected, $compiled->sql);
        self::assertSame(['p0' => true], $compiled->parameters);
        self::assertSame('scalar-list', $compiled->resultMode);
    }

    public function testSqlServerUsesTopWhenThereIsNoOffset(): void
    {
        $query = $this->query()->take(5);

        $compiled = (new SqlQueryCompiler(new SqlServerDialect()))
            ->compile($query->expression());

        self::assertSame('SELECT TOP (5) * FROM [users]', $compiled->sql);
    }

    public function testSqlServerAddsOrderByForOffset(): void
    {
        $query = $this->query()->skip(5)->take(10);

        $compiled = (new SqlQueryCompiler(new SqlServerDialect()))
            ->compile($query->expression());

        self::assertSame(
            'SELECT * FROM [users] ORDER BY (SELECT 0) OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $compiled->sql,
        );
    }

    public function testMySqlAndSqliteHaveValidOffsetOnlySyntax(): void
    {
        $expression = $this->query()->skip(5)->expression();

        $mysql = (new SqlQueryCompiler(new MySqlDialect()))->compile($expression);
        $sqlite = (new SqlQueryCompiler(new SqliteDialect()))->compile($expression);

        self::assertSame(
            'SELECT * FROM `users` LIMIT 18446744073709551615 OFFSET 5',
            $mysql->sql,
        );
        self::assertSame('SELECT * FROM "users" LIMIT -1 OFFSET 5', $sqlite->sql);
    }

    public function testCountWrapsPagedQueryInSubquery(): void
    {
        $query = $this->query()
            ->where(Expr::gte(Expr::field('age'), 18))
            ->take(10);
        $expression = new MethodCallExpression($query->expression(), 'count');

        $compiled = (new SqlQueryCompiler(new PostgreSqlDialect()))->compile($expression);

        self::assertSame(
            'SELECT COUNT(*) AS "__linq_count" FROM '
                .'(SELECT * FROM "users" WHERE ("age" >= :p0) LIMIT 10) AS "__linq_source"',
            $compiled->sql,
        );
        self::assertSame(['p0' => 18], $compiled->parameters);
        self::assertSame('count', $compiled->resultMode);
    }

    public function testNullComparisonUsesIsNullWithoutParameter(): void
    {
        $query = $this->query()->where(Expr::eq(Expr::field('deleted_at'), null));

        $compiled = (new SqlQueryCompiler(new MySqlDialect()))->compile($query->expression());

        self::assertSame(
            'SELECT * FROM `users` WHERE (`deleted_at` IS NULL)',
            $compiled->sql,
        );
        self::assertSame([], $compiled->parameters);
    }

    public function testSqlServerCountDropsUnneededOrdering(): void
    {
        $query = $this->query()->orderBy(Expr::field('name'));
        $expression = new MethodCallExpression($query->expression(), 'count');

        $compiled = (new SqlQueryCompiler(new SqlServerDialect()))->compile($expression);

        self::assertSame(
            'SELECT COUNT(*) AS [__linq_count] FROM (SELECT * FROM [users]) AS [__linq_source]',
            $compiled->sql,
        );
    }

    private function query(): Queryable
    {
        return Queryable::from(new InMemoryQueryProvider(['users' => []]), 'users');
    }
}
