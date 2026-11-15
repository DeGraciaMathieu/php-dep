<?php

declare(strict_types=1);

namespace PhpDep\Tests\Cli;

use PhpDep\Cli\Arguments;
use PHPUnit\Framework\TestCase;

final class ArgumentsTest extends TestCase
{
    public function testDefaults(): void
    {
        $arguments = Arguments::parse([]);

        self::assertSame('.', $arguments->path);
        self::assertSame([], $arguments->excludeDirs);
        self::assertFalse($arguments->includeVendor);
        self::assertNull($arguments->workers);
    }

    public function testReadsEveryOption(): void
    {
        $arguments = Arguments::parse(['analyze', 'src', '--exclude=tests', '--exclude', 'fixtures', '--include-vendor', '--workers', '4']);

        self::assertSame('src', $arguments->path);
        self::assertSame(['tests', 'fixtures'], $arguments->excludeDirs);
        self::assertTrue($arguments->includeVendor);
        self::assertSame(4, $arguments->workers);
    }

    public function testReadsAPathAfterTheEndOfOptions(): void
    {
        self::assertSame('-odd-name', Arguments::parse(['--', '-odd-name'])->path);
    }

    public function testRejectsTwoPaths(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Arguments::parse(['src', 'tests']);
    }

    public function testRejectsAnOptionWithoutItsValue(): void
    {
        $this->expectExceptionMessage('The --exclude option needs a value');

        Arguments::parse(['--exclude']);
    }
}
