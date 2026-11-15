<?php

declare(strict_types=1);

namespace PhpDep\Warning;

enum WarningType: string
{
    case DYNAMIC_INSTANTIATION = 'dynamic_instantiation';
    case PARSE_ERROR           = 'parse_error';
}
