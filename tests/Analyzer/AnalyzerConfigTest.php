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

        self::assertFalse($config->skipDocblocks);
        self::assertTrue($config->excludeVendor);
        self::assertSame('boundary', $config->vendorMode);
        self::assertSame([], $config->excludeDirs);
        self::assertSame([], $config->includePatterns);
    }

    public function testCustomValues(): void
    {
        $config = new AnalyzerConfig(
            skipDocblocks:   true,
            excludeVendor:   false,
            vendorMode:      'full',
            excludeDirs:     ['cache', 'tmp'],
            includePatterns: ['**/*.php'],
        );

        self::assertTrue($config->skipDocblocks);
        self::assertFalse($config->excludeVendor);
        self::assertSame('full', $config->vendorMode);
        self::assertSame(['cache', 'tmp'], $config->excludeDirs);
        self::assertSame(['**/*.php'], $config->includePatterns);
    }

    public function testPartialOverride(): void
    {
        $config = new AnalyzerConfig(skipDocblocks: true);

        self::assertTrue($config->skipDocblocks);
        // Remaining fields keep their defaults
        self::assertTrue($config->excludeVendor);
        self::assertSame('boundary', $config->vendorMode);
    }
}
