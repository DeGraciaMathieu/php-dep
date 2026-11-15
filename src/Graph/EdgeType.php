<?php

declare(strict_types=1);

namespace PhpDep\Graph;

enum EdgeType: string
{
    // Structural
    case EXTENDS_CLASS   = 'extends';
    case IMPLEMENTS_INTERFACE = 'implements';
    case USES_TRAIT      = 'uses_trait';

    // Type hints (parameters, return types, properties)
    case PARAM_TYPE      = 'param_type';
    case RETURN_TYPE     = 'return_type';
    case PROPERTY_TYPE   = 'property_type';
    case CONSTANT_TYPE   = 'constant_type';
    case ATTRIBUTE       = 'attribute';

    // Instantiation & calls
    case INSTANTIATES    = 'instantiates';
    case STATIC_CALL     = 'static_call';
    case STATIC_PROPERTY = 'static_property';
    case CONST_ACCESS    = 'const_access';

    // Control flow
    case INSTANCEOF_CHECK = 'instanceof';
    case CATCHES          = 'catches';
}
