<?php

declare(strict_types=1);

namespace PhpLinq\Sql;

use PhpLinq\Expression\BinaryExpression;
use PhpLinq\Expression\ConstantExpression;
use PhpLinq\Expression\FieldExpression;
use PhpLinq\Expression\LogicalExpression;
use PhpLinq\Expression\ValueExpression;

abstract class AbstractSqlDialect implements SqlDialect
{
    /** @var array<string, mixed> */
    private array $parameters = [];
    private int $parameterIndex = 0;

    final public function compile(QueryPlan $query): CompiledQuery
    {
        $this->parameters = [];
        $this->parameterIndex = 0;

        $terminal = $query->terminal;
        $resultMode = match ($terminal) {
            'count' => 'count',
            'any' => 'any',
            'first' => $query->selection === null ? 'first-row' : 'first-scalar',
            default => $query->selection === null ? 'rows' : 'scalar-list',
        };

        if ($terminal === 'first') {
            $query = clone $query;
            $query->limit = min($query->limit ?? 1, 1);
            $query->terminal = 'sequence';
        } elseif (($terminal === 'count' || $terminal === 'any')
            && $query->limit === null
            && $query->offset === null
        ) {
            // Ordering cannot affect an unpaged aggregate and is illegal in a
            // SQL Server derived table unless TOP/OFFSET is also present.
            $query = clone $query;
            $query->orderings = [];
        }

        $sql = $this->compileSequence($query);
        if ($terminal === 'count' || $terminal === 'any') {
            $alias = $this->quoteIdentifier('__linq_source');
            $aggregate = $this->quoteIdentifier('__linq_count');
            $sql = "SELECT COUNT(*) AS {$aggregate} FROM ({$sql}) AS {$alias}";
        }

        return new CompiledQuery($sql, $this->parameters, $resultMode);
    }

    final public function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as &$part) {
            if ($part === '' || $part === '*') {
                throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
            }
            $part = $this->quoteIdentifierPart($part);
        }
        return implode('.', $parts);
    }

    abstract protected function quoteIdentifierPart(string $identifier): string;

    /** @return array{prefix: string, suffix: string} */
    abstract protected function pagination(?int $limit, ?int $offset, bool $hasOrderBy): array;

    private function compileSequence(QueryPlan $query): string
    {
        $pagination = $this->pagination($query->limit, $query->offset, $query->orderings !== []);
        $selection = $query->selection === null
            ? '*'
            : $this->quoteIdentifier($query->selection->path).' AS '.$this->quoteIdentifier('__linq_value');

        $sql = 'SELECT '.$pagination['prefix'].$selection
            .' FROM '.$this->quoteIdentifier($query->table);

        if ($query->predicates !== []) {
            $predicates = array_map(fn (ValueExpression $item): string => $this->value($item), $query->predicates);
            $sql .= ' WHERE '.implode(' AND ', $predicates);
        }

        if ($query->orderings !== []) {
            $parts = [];
            foreach ($query->orderings as $ordering) {
                $parts[] = $this->value($ordering['expression']).($ordering['descending'] ? ' DESC' : ' ASC');
            }
            $sql .= ' ORDER BY '.implode(', ', $parts);
        } elseif ($query->offset !== null && $this instanceof SqlServerDialect) {
            $sql .= ' ORDER BY (SELECT 0)';
        }

        return $sql.$pagination['suffix'];
    }

    private function value(ValueExpression $expression): string
    {
        if ($expression instanceof BinaryExpression
            && in_array($expression->operator, ['=', '!='], true)
            && ($expression->left instanceof ConstantExpression && $expression->left->value === null
                || $expression->right instanceof ConstantExpression && $expression->right->value === null)
        ) {
            $operand = $expression->left instanceof ConstantExpression
                ? $expression->right
                : $expression->left;
            return '('.$this->value($operand)
                .($expression->operator === '=' ? ' IS NULL)' : ' IS NOT NULL)');
        }

        return match (true) {
            $expression instanceof FieldExpression => $this->quoteIdentifier($expression->path),
            $expression instanceof ConstantExpression => $this->parameter($expression->value),
            $expression instanceof BinaryExpression => '('.$this->value($expression->left).' '
                .$expression->operator.' '.$this->value($expression->right).')',
            $expression instanceof LogicalExpression => '('.$this->value($expression->left).' '
                .strtoupper($expression->operator).' '.$this->value($expression->right).')',
            default => throw new \LogicException('Unsupported SQL value expression: '.$expression::class),
        };
    }

    private function parameter(mixed $value): string
    {
        $name = 'p'.$this->parameterIndex++;
        $this->parameters[$name] = $value;
        return ':'.$name;
    }
}
