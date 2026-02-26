<?php

declare(strict_types=1);

namespace PhpDep\Tests\Parser;

use PhpDep\Parser\TypeResolver;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\TestCase;

final class TypeResolverTest extends TestCase
{
    private TypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TypeResolver();
    }

    public function testNullReturnsEmptyArray(): void
    {
        self::assertSame([], $this->resolver->resolve(null));
    }

    public function testIdentifierReturnsEmpty(): void
    {
        self::assertSame([], $this->resolver->resolve(new Identifier('void')));
        self::assertSame([], $this->resolver->resolve(new Identifier('mixed')));
    }

    public function testScalarNamesReturnEmpty(): void
    {
        $scalars = ['int', 'float', 'string', 'bool', 'boolean', 'array', 'void',
                    'never', 'mixed', 'null', 'false', 'true', 'object', 'iterable',
                    'callable', 'self', 'static', 'parent', 'resource'];

        foreach ($scalars as $scalar) {
            self::assertSame(
                [],
                $this->resolver->resolve(new Name($scalar)),
                "Expected empty array for scalar type: {$scalar}",
            );
        }
    }

    public function testScalarCheckIsCaseInsensitive(): void
    {
        self::assertSame([], $this->resolver->resolve(new Name('String')));
        self::assertSame([], $this->resolver->resolve(new Name('INT')));
        self::assertSame([], $this->resolver->resolve(new Name('Bool')));
    }

    public function testSimpleClassNameReturnsIt(): void
    {
        self::assertSame(['MyClass'], $this->resolver->resolve(new Name('MyClass')));
    }

    public function testFullyQualifiedClassNameReturnsIt(): void
    {
        $name = new Name\FullyQualified(['App', 'Service', 'Foo']);
        self::assertSame(['App\\Service\\Foo'], $this->resolver->resolve($name));
    }

    public function testNullableClassTypeExtractsInnerName(): void
    {
        $type = new NullableType(new Name\FullyQualified(['App', 'Foo']));
        self::assertSame(['App\\Foo'], $this->resolver->resolve($type));
    }

    public function testNullableScalarReturnsEmpty(): void
    {
        $type = new NullableType(new Identifier('int'));
        self::assertSame([], $this->resolver->resolve($type));
    }

    public function testUnionTypeExtractsOnlyClassNames(): void
    {
        $type = new UnionType([
            new Name\FullyQualified(['App', 'Foo']),
            new Identifier('int'),
            new Name\FullyQualified(['App', 'Bar']),
        ]);

        self::assertSame(['App\\Foo', 'App\\Bar'], $this->resolver->resolve($type));
    }

    public function testUnionTypeWithAllScalarsReturnsEmpty(): void
    {
        $type = new UnionType([new Identifier('string'), new Identifier('null')]);
        self::assertSame([], $this->resolver->resolve($type));
    }

    public function testIntersectionTypeExtractsAllClassNames(): void
    {
        $type = new IntersectionType([
            new Name\FullyQualified(['App', 'InterfaceA']),
            new Name\FullyQualified(['App', 'InterfaceB']),
        ]);

        self::assertSame(['App\\InterfaceA', 'App\\InterfaceB'], $this->resolver->resolve($type));
    }

    public function testUnknownNodeTypeReturnsEmpty(): void
    {
        // Any object that is not a recognised node type
        $unknown = new \stdClass();
        self::assertSame([], $this->resolver->resolve($unknown));
    }
}
