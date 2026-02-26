<?php

declare(strict_types=1);

namespace PhpDep\Graph;

enum NodeType: string
{
    case CLASS_NODE     = 'class';
    case INTERFACE_NODE = 'interface';
    case TRAIT_NODE     = 'trait';
    case ENUM_NODE      = 'enum';
    case EXTERNAL       = 'external';
}
