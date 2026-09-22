<?php

declare(strict_types=1);

namespace PhpLinq\Sql;

use PhpLinq\Expression\FieldExpression;
use PhpLinq\Expression\ValueExpression;

final class QueryPlan
{
    /** @var list<ValueExpression> */
    public array $predicates = [];

    /** @var list<array{expression: ValueExpression, descending: bool}> */
    public array $orderings = [];

    public ?FieldExpression $selection = null;
    public ?int $offset = null;
    public ?int $limit = null;
    public string $terminal = 'sequence';

    public function __construct(public readonly string $table)
    {
    }
}
