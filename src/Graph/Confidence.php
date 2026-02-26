<?php

declare(strict_types=1);

namespace PhpDep\Graph;

enum Confidence: string
{
    case CERTAIN = 'certain'; // structural: extends, implements, use trait
    case HIGH    = 'high';    // type hints, docblocks
    case MEDIUM  = 'medium';  // instanceof, catch
    case LOW     = 'low';     // dynamic patterns
}
