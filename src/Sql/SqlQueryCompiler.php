<?php

declare(strict_types=1);

namespace PhpLinq\Sql;

use PhpLinq\Expression\Expression;
use PhpLinq\Expression\MethodCallExpression;
use PhpLinq\Expression\SourceExpression;

final readonly class SqlQueryCompiler
{
    public function __construct(private SqlDialect $dialect)
    {
    }

    public function compile(Expression $expression): CompiledQuery
    {
        $operations = [];
        while ($expression instanceof MethodCallExpression) {
            array_unshift($operations, $expression);
            $expression = $expression->source;
        }
        if (!$expression instanceof SourceExpression) {
            throw new \LogicException('A SQL query must start with a named source.');
        }

        $plan = new QueryPlan($expression->name);
        $paginationStarted = false;
        foreach ($operations as $operation) {
            if ($paginationStarted && in_array($operation->method, ['where', 'orderBy'], true)) {
                throw new \LogicException('where/orderBy after skip/take requires subquery support.');
            }

            match ($operation->method) {
                'where' => $plan->predicates[] = $operation->arguments['predicate'],
                'select' => $plan->selection = $operation->arguments['selector'],
                'orderBy' => $plan->orderings[] = [
                    'expression' => $operation->arguments['keySelector'],
                    'descending' => $operation->arguments['descending'],
                ],
                'skip' => $plan->offset = ($plan->offset ?? 0) + $operation->arguments['count'],
                'take' => $plan->limit = min($plan->limit ?? PHP_INT_MAX, $operation->arguments['count']),
                'count', 'any', 'first' => $plan->terminal = $operation->method,
                default => throw new \LogicException("Unsupported SQL query method: {$operation->method}"),
            };
            $paginationStarted = $paginationStarted || in_array($operation->method, ['skip', 'take'], true);
        }

        return $this->dialect->compile($plan);
    }
}
