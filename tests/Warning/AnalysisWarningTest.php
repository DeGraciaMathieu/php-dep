<?php

declare(strict_types=1);

namespace PhpDep\Tests\Warning;

use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;
use PHPUnit\Framework\TestCase;

final class AnalysisWarningTest extends TestCase
{
    public function testToArrayContainsAllFields(): void
    {
        $warning = new AnalysisWarning(
            WarningType::PARSE_ERROR,
            '/path/to/file.php',
            42,
            'Unexpected token',
        );

        self::assertSame([
            'type'    => 'parse_error',
            'file'    => '/path/to/file.php',
            'line'    => 42,
            'message' => 'Unexpected token',
        ], $warning->toArray());
    }

    public function testToArrayWithNullLine(): void
    {
        $warning = new AnalysisWarning(
            WarningType::DYNAMIC_INSTANTIATION,
            '/path/to/file.php',
            null,
            'Dynamic new $var()',
        );

        self::assertNull($warning->toArray()['line']);
    }

    public function testWarningTypeValues(): void
    {
        self::assertSame('dynamic_instantiation', WarningType::DYNAMIC_INSTANTIATION->value);
        self::assertSame('dynamic_call',          WarningType::DYNAMIC_CALL->value);
        self::assertSame('parse_error',           WarningType::PARSE_ERROR->value);
    }

    public function testAllWarningTypesProduceCorrectToArray(): void
    {
        foreach (WarningType::cases() as $type) {
            $warning = new AnalysisWarning($type, 'file.php', null, 'msg');
            self::assertSame($type->value, $warning->toArray()['type']);
        }
    }
}
