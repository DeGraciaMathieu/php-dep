<?php

declare(strict_types=1);

namespace PhpDep\Parser;

use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

final class TypeResolver
{
    /** PHP built-in scalar/special types to skip */
    private const SCALARS = [
        'int', 'float', 'string', 'bool', 'boolean', 'array', 'void',
        'never', 'mixed', 'null', 'false', 'true', 'object', 'iterable',
        'callable', 'self', 'static', 'parent', 'resource',
    ];

    /**
     * Unwrap any type node into a list of fully-qualified class names.
     * Returns empty array for scalar/special types.
     *
     * @param Name|Identifier|NullableType|UnionType|IntersectionType|null $typeNode
     * @return string[]
     */
    public function resolve(mixed $typeNode): array
    {
        if ($typeNode === null) {
            return [];
        }

        if ($typeNode instanceof NullableType) {
            return $this->resolve($typeNode->type);
        }

        if ($typeNode instanceof UnionType || $typeNode instanceof IntersectionType) {
            $fqcns = [];
            foreach ($typeNode->types as $t) {
                array_push($fqcns, ...$this->resolve($t));
            }
            return $fqcns;
        }

        if ($typeNode instanceof Identifier) {
            // built-in type
            return [];
        }

        if ($typeNode instanceof Name) {
            $fqcn = (string) $typeNode;
            if ($this->isScalar($fqcn)) {
                return [];
            }
            return [$fqcn];
        }

        return [];
    }

    private function isScalar(string $name): bool
    {
        return in_array(strtolower($name), self::SCALARS, true);
    }
}
