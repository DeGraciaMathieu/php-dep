<?php

declare(strict_types=1);

namespace PhpDep\Tests\Graph;

use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PHPUnit\Framework\TestCase;

final class GraphEdgeTest extends TestCase
{
    public function testToArrayContainsAllFields(): void
    {
        $edge = new GraphEdge(
            'App\\Foo',
            'App\\Bar',
            EdgeType::EXTENDS_CLASS,
            '/path/Foo.php',
            10,
        );

        self::assertSame([
            'source' => 'App\\Foo',
            'target' => 'App\\Bar',
            'type'   => 'extends',
            'file'   => '/path/Foo.php',
            'line'   => 10,
        ], $edge->toArray());
    }

    public function testToArrayDefaultsForOptionalFields(): void
    {
        $edge = new GraphEdge('App\\A', 'App\\B', EdgeType::INSTANTIATES);
        $array = $edge->toArray();

        self::assertNull($array['file']);
        self::assertNull($array['line']);
    }

    public function testEdgeTypeValuesInToArray(): void
    {
        // Enum cases cannot be used as array keys, so we use a list of pairs
        $cases = [
            [EdgeType::EXTENDS_CLASS,        'extends'],
            [EdgeType::IMPLEMENTS_INTERFACE, 'implements'],
            [EdgeType::USES_TRAIT,           'uses_trait'],
            [EdgeType::INSTANTIATES,         'instantiates'],
            [EdgeType::PARAM_TYPE,           'param_type'],
            [EdgeType::RETURN_TYPE,          'return_type'],
            [EdgeType::STATIC_CALL,          'static_call'],
            [EdgeType::INSTANCEOF_CHECK,     'instanceof'],
            [EdgeType::CATCHES,              'catches'],
        ];

        foreach ($cases as [$type, $expected]) {
            $array = (new GraphEdge('A', 'B', $type))->toArray();
            self::assertSame($expected, $array['type'], "Expected type value '{$expected}'");
        }
    }
}
