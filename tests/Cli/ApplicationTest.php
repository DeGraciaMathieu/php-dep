<?php

declare(strict_types=1);

namespace PhpDep\Tests\Cli;

use PhpDep\Cli\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $tmpDir;

    /** @var array{stdout: string, stderr: string} */
    private array $last;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/php_dep_cli_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*.php'));
        rmdir($this->tmpDir);
    }

    private function analyze(string ...$args): int
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $code = (new Application())->run($args, $stdout, $stderr);

        rewind($stdout);
        rewind($stderr);
        $this->last = ['stdout' => stream_get_contents($stdout), 'stderr' => stream_get_contents($stderr)];

        return $code;
    }

    public function testWritesTheGraphAndSucceeds(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A extends B {}');

        self::assertSame(0, $this->analyze('analyze', $this->tmpDir, '--workers=1'));
        self::assertSame('App\\B', json_decode($this->last['stdout'], true)['edges'][0]['target']);
    }

    public function testTheAnalyzeCommandNameIsOptional(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A extends B {}');

        self::assertSame(0, $this->analyze($this->tmpDir, '-w', '1'));
        self::assertCount(1, json_decode($this->last['stdout'], true)['edges']);
    }

    public function testWritesThePartialGraphWhenAFileCannotBeParsed(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A extends B {}');
        file_put_contents($this->tmpDir . '/bad.php', '<?php this is not valid ??? !!!');

        self::assertSame(3, $this->analyze($this->tmpDir, '-w1'));
        self::assertCount(1, json_decode($this->last['stdout'], true)['edges']);
    }

    public function testRejectsAnInvalidNumberOfWorkers(): void
    {
        self::assertSame(2, $this->analyze($this->tmpDir, '--workers=0'));
        self::assertStringContainsString('Invalid workers', $this->last['stderr']);
        self::assertSame('', $this->last['stdout']);
    }

    public function testRejectsAnUnknownOption(): void
    {
        self::assertSame(2, $this->analyze($this->tmpDir, '--format=json'));
        self::assertStringContainsString('Unknown option: --format', $this->last['stderr']);
    }

    public function testRejectsAMissingPath(): void
    {
        self::assertSame(2, $this->analyze($this->tmpDir . '/missing'));
        self::assertStringContainsString('Path not found', $this->last['stderr']);
    }

    public function testShowsTheHelp(): void
    {
        self::assertSame(0, $this->analyze('--help'));
        self::assertStringContainsString('--workers=N', $this->last['stdout']);
    }

    public function testShowsTheVersion(): void
    {
        self::assertSame(0, $this->analyze('-V'));
        self::assertMatchesRegularExpression('/^php-dep \d+\.\d+\.\d+$/', trim($this->last['stdout']));
    }
}
