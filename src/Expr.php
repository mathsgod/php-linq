<?php

declare(strict_types=1);

namespace PhpLinq;

use PhpLinq\Expression\BinaryExpression;
use PhpLinq\Expression\ConstantExpression;
use PhpLinq\Expression\FieldExpression;
use PhpLinq\Expression\LogicalExpression;
use PhpLinq\Expression\ValueExpression;

final class Expr
{
    public static function field(string $path): FieldExpression
    {
        return new FieldExpression($path);
    }

    public static function value(mixed $value): ConstantExpression
    {
        return new ConstantExpression($value);
    }

    public static function eq(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '=', $right);
    }

    public static function neq(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '!=', $right);
    }

    public static function gt(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '>', $right);
    }

    public static function gte(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '>=', $right);
    }

    public static function lt(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '<', $right);
    }

    public static function lte(ValueExpression $left, mixed $right): BinaryExpression
    {
        return self::compare($left, '<=', $right);
    }

    public static function both(ValueExpression $left, ValueExpression $right): LogicalExpression
    {
        return new LogicalExpression($left, 'and', $right);
    }

    public static function either(ValueExpression $left, ValueExpression $right): LogicalExpression
    {
        return new LogicalExpression($left, 'or', $right);
    }

    private static function compare(ValueExpression $left, string $operator, mixed $right): BinaryExpression
    {
        return new BinaryExpression(
            $left,
            $operator,
            $right instanceof ValueExpression ? $right : self::value($right),
        );
    }
}
