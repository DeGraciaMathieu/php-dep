<?php

declare(strict_types=1);

namespace PhpDep\Tests\Cli;

use PhpDep\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

final class AnalyzeCommandTest extends TestCase
{
    private string $tmpDir;

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

    private function analyze(array $input): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'analyze', ...$input]);

        return $tester;
    }

    public function testWritesTheGraphAndSucceeds(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A extends B {}');

        $tester = $this->analyze(['path' => $this->tmpDir, '--workers' => '1']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('App\\B', json_decode($tester->getDisplay(), true)['edges'][0]['target']);
    }

    public function testWritesThePartialGraphWhenAFileCannotBeParsed(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A extends B {}');
        file_put_contents($this->tmpDir . '/bad.php', '<?php this is not valid ??? !!!');

        $tester = $this->analyze(['path' => $this->tmpDir, '--workers' => '1']);

        self::assertSame(3, $tester->getStatusCode());
        self::assertCount(1, json_decode($tester->getDisplay(), true)['edges']);
    }

    public function testRejectsAnInvalidNumberOfWorkers(): void
    {
        $tester = $this->analyze(['path' => $this->tmpDir, '--workers' => '0']);

        self::assertSame(2, $tester->getStatusCode());
    }

    public function testRejectsAMissingPath(): void
    {
        $tester = $this->analyze(['path' => $this->tmpDir . '/missing']);

        self::assertSame(2, $tester->getStatusCode());
    }
}
