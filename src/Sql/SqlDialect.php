<?php

declare(strict_types=1);

namespace PhpLinq\Sql;

interface SqlDialect
{
    public function compile(QueryPlan $query): CompiledQuery;

    public function quoteIdentifier(string $identifier): string;
}
