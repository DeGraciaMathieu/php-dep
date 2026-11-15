<?php

declare(strict_types=1);

namespace PhpDep\Tests\RealProjects;

use PhpDep\Tests\Support\Analysis;
use PhpDep\Tests\Support\LaravelIo;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * laravel.io as its maintainers wrote it. The figures were checked once against the code of the commit,
 * and against what-moved-core on the same code, then frozen here.
 */
#[Group('real')]
final class LaravelIoTest extends TestCase
{
    // The pull request #1375, "Add GitHub account connection and disconnection functionality"
    private const COMMIT = 'd2478354';

    private const CLASS_COUNT               = 200;
    private const INTERNAL_DEPENDENCY_COUNT = 436;

    public function testMapsEveryClassOfTheApplication(): void
    {
        $analysis = Analysis::of(LaravelIo::at(self::COMMIT) . '/app', workers: 4);

        self::assertCount(0, $analysis->warnings);
        self::assertCount(self::CLASS_COUNT, $analysis->classes());
        self::assertSame(self::INTERNAL_DEPENDENCY_COUNT, $analysis->internalDependencyCount());
        self::assertSame(
            ['App\Jobs\UpdateUserIdenticonStatus', 'App\Models\User'],
            $analysis->internalDependenciesOf('App\Actions\ConnectGitHubAccount'),
        );
    }
}
