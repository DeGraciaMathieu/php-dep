<?php

declare(strict_types=1);

namespace PhpDep\Warning;

enum WarningType: string
{
    case DYNAMIC_INSTANTIATION = 'dynamic_instantiation';
    case DYNAMIC_CALL          = 'dynamic_call';
    case PARSE_ERROR           = 'parse_error';
}
