<?php

declare(strict_types=1);

namespace PhpDep\Tests\Graph;

use PhpDep\Graph\Confidence;
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
            Confidence::CERTAIN,
            '/path/Foo.php',
            10,
            ['key' => 'value'],
        );

        self::assertSame([
            'source'     => 'App\\Foo',
            'target'     => 'App\\Bar',
            'type'       => 'extends',
            'confidence' => 'certain',
            'file'       => '/path/Foo.php',
            'line'       => 10,
            'metadata'   => ['key' => 'value'],
        ], $edge->toArray());
    }

    public function testToArrayDefaultsForOptionalFields(): void
    {
        $edge = new GraphEdge('App\\A', 'App\\B', EdgeType::INSTANTIATES, Confidence::HIGH);
        $array = $edge->toArray();

        self::assertNull($array['file']);
        self::assertNull($array['line']);
        self::assertSame([], $array['metadata']);
    }

    public function testConfidenceValuesInToArray(): void
    {
        self::assertSame('high',   (new GraphEdge('A', 'B', EdgeType::PARAM_TYPE, Confidence::HIGH))->toArray()['confidence']);
        self::assertSame('medium', (new GraphEdge('A', 'B', EdgeType::INSTANCEOF_CHECK, Confidence::MEDIUM))->toArray()['confidence']);
        self::assertSame('low',    (new GraphEdge('A', 'B', EdgeType::INSTANTIATES, Confidence::LOW))->toArray()['confidence']);
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
            $array = (new GraphEdge('A', 'B', $type, Confidence::HIGH))->toArray();
            self::assertSame($expected, $array['type'], "Expected type value '{$expected}'");
        }
    }
}
