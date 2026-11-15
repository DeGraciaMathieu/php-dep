<?php

declare(strict_types=1);

namespace PhpDep\Tests\Behaviour;

use PhpDep\Api\PhpDep;
use PhpDep\Tests\Support\Project;
use PHPUnit\Framework\TestCase;

/**
 * php-dep used as a library, the way what-moved-core uses it: only through PhpDep and Analysis.
 */
final class ApiTest extends TestCase
{
    public function testGivesTheDeclaredClassesAndWhatEachOneUses(): void
    {
        $project = Project::with([
            'Shop/Cart.php'       => '<?php namespace App\Shop; use App\Catalog\Product; class Cart { public function add(Product $p): \DateTime { new \app\catalog\product(); } }',
            'Catalog/Product.php' => '<?php namespace App\Catalog; interface Product {}',
        ]);

        $analysis = PhpDep::analyze($project->root);

        self::assertSame(['App\Catalog\Product', 'App\Shop\Cart'], $analysis->classes());
        self::assertSame(['App\Catalog\Product', 'DateTime'], $analysis->dependenciesOf('App\Shop\Cart'));
        self::assertTrue($analysis->isDeclared('App\Catalog\Product'));
        self::assertFalse($analysis->isDeclared('DateTime'));
        self::assertSame(realpath($project->root) . '/Shop/Cart.php', $analysis->fileOf('App\Shop\Cart'));
        self::assertNull($analysis->fileOf('DateTime'));
        self::assertSame([], $analysis->dependenciesOf('DateTime'));
    }

    public function testListsTheFilesItCouldNotParse(): void
    {
        $project = Project::with([
            'Shop/Cart.php'   => '<?php namespace App\Shop; class Cart {}',
            'Shop/Broken.php' => '<?php namespace App\Shop; class Broken {',
        ]);

        $analysis = PhpDep::analyze($project->root, workers: 2);

        self::assertSame(['App\Shop\Cart'], $analysis->classes());
        self::assertSame([realpath($project->root) . '/Shop/Broken.php'], array_keys($analysis->parseErrors()));
    }

    public function testRejectsAPathThatDoesNotExist(): void
    {
        $this->expectExceptionMessage('Path not found: /no/such/project');

        PhpDep::analyze('/no/such/project');
    }
}
