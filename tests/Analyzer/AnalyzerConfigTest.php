<?php

declare(strict_types=1);

namespace PhpDep\Tests\Analyzer;

use PhpDep\Analyzer\AnalyzerConfig;
use PHPUnit\Framework\TestCase;

final class AnalyzerConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new AnalyzerConfig();

        self::assertTrue($config->excludeVendor);
        self::assertSame([], $config->excludeDirs);
        self::assertSame(1, $config->workers);
    }

    public function testCustomValues(): void
    {
        $config = new AnalyzerConfig(
            excludeVendor: false,
            excludeDirs:   ['cache', 'tmp'],
            workers:       4,
        );

        self::assertFalse($config->excludeVendor);
        self::assertSame(['cache', 'tmp'], $config->excludeDirs);
        self::assertSame(4, $config->workers);
    }

    public function testPartialOverride(): void
    {
        $config = new AnalyzerConfig(workers: 4);

        self::assertSame(4, $config->workers);
        // Remaining fields keep their defaults
        self::assertTrue($config->excludeVendor);
    }
}
