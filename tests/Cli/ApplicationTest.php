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

    public function testWritesTheGraphInItsDocumentedShape(): void
    {
        file_put_contents($this->tmpDir . '/Cart.php', "<?php namespace App;\nuse Acme\\Money;\nabstract class Cart extends Basket\n{\n    public function total(): Money {}\n}");
        file_put_contents($this->tmpDir . '/Basket.php', '<?php namespace App; class Basket {}');
        file_put_contents($this->tmpDir . '/Factory.php', '<?php namespace App; class Factory { public function make($c) { return new $c(); } }');

        $this->analyze($this->tmpDir, '-w1');
        $json = json_decode($this->last['stdout'], true);

        self::assertSame('2.0', $json['meta']['version']);
        self::assertSame(realpath($this->tmpDir), $json['meta']['analyzed_path']);
        self::assertSame(
            ['file_count' => 3, 'class_count' => 3, 'node_count' => 4, 'edge_count' => 2, 'warning_count' => 1],
            array_intersect_key($json['meta'], array_flip(['file_count', 'class_count', 'node_count', 'edge_count', 'warning_count'])),
        );
        self::assertSame(['App\Basket', 'App\Cart', 'App\Factory'], array_column($json['classes'], 'fqcn'));
        self::assertSame([
            'fqcn'         => 'App\Cart',
            'type'         => 'class',
            'file'         => realpath($this->tmpDir) . '/Cart.php',
            'line'         => 3,
            'abstract'     => true,
            'dependencies' => ['App\Basket', 'Acme\Money'],
            'dependants'   => [],
        ], $json['classes'][1]);
        self::assertSame(['App\Cart'], $json['classes'][0]['dependants']);
        self::assertSame([
            ['source' => 'App\Cart', 'target' => 'Acme\Money', 'type' => 'return_type', 'file' => realpath($this->tmpDir) . '/Cart.php', 'line' => 5],
            ['source' => 'App\Cart', 'target' => 'App\Basket', 'type' => 'extends', 'file' => realpath($this->tmpDir) . '/Cart.php', 'line' => 3],
        ], $json['edges']);
        self::assertSame('dynamic_instantiation', $json['warnings'][0]['type']);
    }

    public function testWritesAnEmptyGraphForAProjectWithoutClasses(): void
    {
        self::assertSame(0, $this->analyze($this->tmpDir));
        self::assertSame(0, json_decode($this->last['stdout'], true)['meta']['class_count']);
    }

    public function testExcludesEveryDirectoryGiven(): void
    {
        foreach (['tests', 'fixtures', 'src'] as $dir) {
            mkdir($this->tmpDir . '/' . $dir);
            file_put_contents("{$this->tmpDir}/{$dir}/C.php", "<?php namespace App\\{$dir}; class C {}");
        }

        $this->analyze($this->tmpDir, '--exclude=tests', '--exclude', 'fixtures', '-w1');

        self::assertSame(['App\src\C'], array_column(json_decode($this->last['stdout'], true)['classes'], 'fqcn'));

        foreach (['tests', 'fixtures', 'src'] as $dir) {
            unlink("{$this->tmpDir}/{$dir}/C.php");
            rmdir("{$this->tmpDir}/{$dir}");
        }
    }

    public function testReadsAPathAfterTheEndOfOptions(): void
    {
        self::assertSame(2, $this->analyze('--', '-missing'));
        self::assertStringContainsString('Path not found: -missing', $this->last['stderr']);
    }

    public function testRejectsTwoPaths(): void
    {
        self::assertSame(2, $this->analyze($this->tmpDir, $this->tmpDir));
        self::assertStringContainsString('Only one path', $this->last['stderr']);
    }

    public function testRejectsAnOptionWithoutItsValue(): void
    {
        self::assertSame(2, $this->analyze($this->tmpDir, '--exclude'));
        self::assertStringContainsString('The --exclude option needs a value', $this->last['stderr']);
    }
}
