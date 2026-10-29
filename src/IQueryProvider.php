<?php

declare(strict_types=1);

namespace PhpLinq;

use PhpLinq\Expression\Expression;

interface IQueryProvider
{
    public function execute(Expression $expression): mixed;
}
