<?php

declare(strict_types=1);

namespace PhpDep\Tests\Graph;

use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class GraphNodeTest extends TestCase
{
    public function testIsExternalReturnsTrueForExternalType(): void
    {
        $node = new GraphNode('Ext\\Foo', NodeType::EXTERNAL);
        self::assertTrue($node->isExternal());
    }

    public function testIsExternalReturnsFalseForClass(): void
    {
        $node = new GraphNode('App\\Foo', NodeType::CLASS_NODE);
        self::assertFalse($node->isExternal());
    }

    public function testIsExternalReturnsFalseForInterface(): void
    {
        $node = new GraphNode('App\\IFoo', NodeType::INTERFACE_NODE);
        self::assertFalse($node->isExternal());
    }

    public function testIsExternalReturnsFalseForTrait(): void
    {
        $node = new GraphNode('App\\MyTrait', NodeType::TRAIT_NODE);
        self::assertFalse($node->isExternal());
    }

    public function testIsExternalReturnsFalseForEnum(): void
    {
        $node = new GraphNode('App\\MyEnum', NodeType::ENUM_NODE);
        self::assertFalse($node->isExternal());
    }

    public function testToArrayContainsAllFields(): void
    {
        $node = new GraphNode('App\\Foo', NodeType::CLASS_NODE, '/path/to/Foo.php', 5);

        self::assertSame([
            'fqcn' => 'App\\Foo',
            'type' => 'class',
            'file' => '/path/to/Foo.php',
            'line' => 5,
        ], $node->toArray());
    }

    public function testToArrayWithNullFileAndLine(): void
    {
        $node = new GraphNode('App\\Foo', NodeType::TRAIT_NODE);
        $array = $node->toArray();

        self::assertNull($array['file']);
        self::assertNull($array['line']);
    }

    public function testTypeValueInToArray(): void
    {
        self::assertSame('interface', (new GraphNode('A', NodeType::INTERFACE_NODE))->toArray()['type']);
        self::assertSame('trait',     (new GraphNode('A', NodeType::TRAIT_NODE))->toArray()['type']);
        self::assertSame('enum',      (new GraphNode('A', NodeType::ENUM_NODE))->toArray()['type']);
        self::assertSame('external',  (new GraphNode('A', NodeType::EXTERNAL))->toArray()['type']);
    }
}
