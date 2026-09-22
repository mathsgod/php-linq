<?php

declare(strict_types=1);

namespace PhpLinq\Xml;

use DOMNode;

abstract class XNode
{
    protected function __construct(protected readonly DOMNode $node)
    {
    }
}
