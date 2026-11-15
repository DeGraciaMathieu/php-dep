<?php

declare(strict_types=1);

namespace PhpDep\Tests\Behaviour;

use PhpDep\Tests\Support\Analysis;
use PhpDep\Tests\Support\Project;
use PHPUnit\Framework\TestCase;

/**
 * The source folder of a real project holds more than tidy classes: helpers, several classes in a file,
 * vendor classes, code the parser cannot read.
 */
final class ProjectTest extends TestCase
{
    public function testFindsEveryKindOfClass(): void
    {
        $project = Project::with([
            'Shop/Cart.php'     => '<?php namespace App\Shop; class Cart {}',
            'Shop/Basket.php'   => '<?php namespace App\Shop; abstract class Basket {}',
            'Shop/Item.php'     => '<?php namespace App\Shop; interface Item {}',
            'Shop/Priced.php'   => '<?php namespace App\Shop; trait Priced {}',
            'Shop/Status.php'   => '<?php namespace App\Shop; enum Status { case Open; }',
        ]);
        $graph = Analysis::of($project->root)->graph;

        self::assertSame('class', $graph->getNode('App\Shop\Cart')->type->value);
        self::assertTrue($graph->getNode('App\Shop\Basket')->abstract);
        self::assertFalse($graph->getNode('App\Shop\Cart')->abstract);
        self::assertSame('interface', $graph->getNode('App\Shop\Item')->type->value);
        self::assertSame('trait', $graph->getNode('App\Shop\Priced')->type->value);
        self::assertSame('enum', $graph->getNode('App\Shop\Status')->type->value);
    }

    public function testReadsAFileWithoutAnyClass(): void
    {
        $project = Project::with([
            'helpers.php'  => "<?php\nif (!function_exists('money')) { function money(\\App\\Shop\\Cart \$cart): string { return ''; } }",
            'Shop/Cart.php' => '<?php namespace App\Shop; class Cart {}',
        ]);
        $analysis = Analysis::of($project->root);

        self::assertSame(['App\Shop\Cart'], $analysis->classes());
        self::assertSame([], $analysis->warnings);
    }

    public function testReadsEveryClassOfAFileThatDeclaresSeveral(): void
    {
        $project = Project::with([
            'Shop/Events.php' => <<<'PHP'
                <?php
                namespace App\Shop;
                final class CartOpened {}
                final class CartClosed { public function __construct(public CartOpened $opened) {} }
                PHP,
        ]);
        $analysis = Analysis::of($project->root);

        self::assertSame(['App\Shop\CartClosed', 'App\Shop\CartOpened'], $analysis->classes());
        self::assertSame(['App\Shop\CartOpened'], $analysis->dependenciesOf('App\Shop\CartClosed'));
    }

    public function testDoesNotCountAClassAsDependingOnItself(): void
    {
        $project = Project::with([
            'Shop/Cart.php' => '<?php namespace App\Shop; class Cart { public static function make(): Cart { return new Cart(); } }',
        ]);

        self::assertSame([], Analysis::of($project->root)->dependenciesOf('App\Shop\Cart'));
    }

    public function testKeepsVendorClassesAsExternalLeaves(): void
    {
        $project = Project::with([
            'src/Shop/Cart.php'                   => '<?php namespace App\Shop; use Acme\Money; class Cart { public function total(): Money {} }',
            'vendor/acme/money/src/Money.php'     => '<?php namespace Acme; class Money { public function add(Currency $c) {} }',
        ]);
        $analysis = Analysis::of($project->root);

        self::assertSame(['App\Shop\Cart'], $analysis->classes());
        self::assertSame(['Acme\Money'], $analysis->dependenciesOf('App\Shop\Cart'));
        self::assertTrue($analysis->graph->getNode('Acme\Money')->isExternal());
        self::assertSame([], $analysis->dependenciesOf('Acme\Money'));
    }

    public function testKeepsTheRestOfTheProjectWhenAFileCannotBeParsed(): void
    {
        $project = Project::with([
            'Shop/Cart.php'   => '<?php namespace App\Shop; class Cart extends Basket {}',
            'Shop/Broken.php' => '<?php namespace App\Shop; class Broken {',
        ]);
        $analysis = Analysis::of($project->root);

        self::assertSame(['App\Shop\Cart'], $analysis->classes());
        self::assertSame(['App\Shop\Basket'], $analysis->dependenciesOf('App\Shop\Cart'));
        self::assertSame(['parse_error'], $analysis->warningTypes());
    }

    public function testWarnsAboutAClassItCannotName(): void
    {
        $project = Project::with([
            'Shop/Factory.php' => '<?php namespace App\Shop; class Factory { public function make(string $class): object { return new $class(); } }',
        ]);

        self::assertSame(['dynamic_instantiation'], Analysis::of($project->root)->warningTypes());
    }

    public function testGivesTheSameGraphWhateverTheNumberOfWorkers(): void
    {
        $files = [];
        for ($i = 0; $i < 20; $i++) {
            $next = $i + 1;
            $files["Shop/C{$i}.php"] = "<?php namespace App\\Shop; class C{$i} { public function next(): C{$next} {} }";
        }
        $files['Shop/Broken.php'] = '<?php class {';
        $project = Project::with($files);

        $sequential = Analysis::of($project->root, workers: 1);
        $parallel   = Analysis::of($project->root, workers: 4);

        self::assertEquals($sequential->graph->getAllNodes(), $parallel->graph->getAllNodes());
        self::assertEquals($sequential->graph->getAllEdges(), $parallel->graph->getAllEdges());
        self::assertEquals($sequential->warnings, $parallel->warnings);
    }
}
