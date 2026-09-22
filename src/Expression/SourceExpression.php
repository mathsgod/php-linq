<?php

declare(strict_types=1);

namespace PhpLinq\Expression;

final readonly class SourceExpression implements Expression
{
    public function __construct(public string $name)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('A source name cannot be empty.');
        }
    }
}
