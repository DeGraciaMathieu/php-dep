<?php

declare(strict_types=1);

namespace PhpDep\Tests\Behaviour;

use PhpDep\Tests\Support\Analysis;
use PhpDep\Tests\Support\Project;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every way a class can depend on another one: App\Shop\Cart is written with each of them,
 * and must depend on App\Catalog\Product, and on nothing else.
 */
final class DependenciesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function waysToDepend(): array
    {
        return [
            // Types
            'parameter'                  => ['class Cart { public function add(Product $product): void {} }'],
            'return'                     => ['class Cart { public function first(): Product {} }'],
            'nullable type'              => ['class Cart { public function first(): ?Product {} }'],
            'union type'                 => ['class Cart { public function first(): Product|false {} }'],
            'intersection type'          => ['class Cart { public function first(): Product&\Countable {} }', ['App\Catalog\Product', 'Countable']],
            'property'                   => ['class Cart { private Product $product; }'],
            'promoted property'          => ['class Cart { public function __construct(private Product $product) {} }'],
            'typed constant'             => ['class Cart { const Product DEFAULT = null; }'],
            'closure'                    => ['class Cart { public function map(): void { $f = function (Product $p) {}; } }'],
            'arrow function'             => ['class Cart { public function map(): void { $f = fn (): Product => $this->p; } }'],

            // What the code does with a class
            'new'                        => ['class Cart { public function make(): void { new Product(); } }'],
            'new as a default value'     => ['class Cart { public function add($product = new Product()): void {} }'],
            'static call'                => ['class Cart { public function make(): void { Product::make(); } }'],
            'first-class callable'       => ['class Cart { public function make(): \Closure { return Product::make(...); } }', ['App\Catalog\Product', 'Closure']],
            'static property'            => ['class Cart { public function make(): void { Product::$count++; } }'],
            'class constant'             => ['class Cart { public function make(): void { Product::LIMIT; } }'],
            '::class'                    => ['class Cart { public function make(): void { app(Product::class); } }'],
            'instanceof'                 => ['class Cart { public function has($item): bool { return $item instanceof Product; } }'],
            'caught exception'           => ['class Cart { public function make(): void { try {} catch (\LogicException|Product $e) {} } }', ['App\Catalog\Product', 'LogicException']],
            'attribute'                  => ['#[Product] class Cart {}'],
            'attribute on a parameter'   => ['class Cart { public function add(#[Product] $item): void {} }'],

            // What a class is built from
            'extends'                    => ['class Cart extends Product {}'],
            'implements'                 => ['class Cart implements Product {}'],
            'trait'                      => ['class Cart { use Product; }'],
            'anonymous class'            => ['class Cart { public function fake(): object { return new class extends Product {}; } }'],
            'inside an anonymous class'  => ['class Cart { public function fake(): object { return new class { public function p(): Product {} }; } }'],

            // Ways to write the name
            'fully qualified name'       => ['class Cart { public function add(\App\Catalog\Product $p): void {} }', ['App\Catalog\Product'], false],
            'aliased import'             => ['class Cart { public function add(Item $p): void {} }', ['App\Catalog\Product'], false, 'use App\Catalog\Product as Item;'],
            'group import'               => ['class Cart { public function add(Product $p): void {} }', ['App\Catalog\Product'], false, 'use App\Catalog\{Product};'],
            'imported namespace'         => ['class Cart { public function add(Catalog\Product $p): void {} }', ['App\Catalog\Product'], false, 'use App\Catalog;'],
        ];
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('waysToDepend')]
    public function testFindsTheDependency(string $cart, array $expected = ['App\Catalog\Product'], bool $import = true, string $use = ''): void
    {
        $use     = $import ? 'use App\Catalog\Product;' : $use;
        $project = Project::with([
            'Catalog/Product.php' => '<?php namespace App\Catalog; class Product {}',
            'Shop/Cart.php'       => "<?php namespace App\\Shop; {$use} {$cart}",
        ]);

        self::assertSame($expected, Analysis::of($project->root)->dependenciesOf('App\Shop\Cart'));
    }

    public function testEnumsAndInterfacesHaveDependenciesToo(): void
    {
        $project = Project::with([
            'Catalog/Product.php' => '<?php namespace App\Catalog; interface Product {}',
            'Shop/Status.php'     => '<?php namespace App\Shop; use App\Catalog\Product; enum Status implements Product { case Open; }',
            'Shop/Basket.php'     => '<?php namespace App\Shop; use App\Catalog\Product; interface Basket extends Product {}',
        ]);
        $analysis = Analysis::of($project->root);

        self::assertSame(['App\Catalog\Product'], $analysis->dependenciesOf('App\Shop\Status'));
        self::assertSame(['App\Catalog\Product'], $analysis->dependenciesOf('App\Shop\Basket'));
    }

    public function testIgnoresANameTheCodeDoesNotUse(): void
    {
        $project = Project::with([
            'Catalog/Product.php' => '<?php namespace App\Catalog; class Product {}',
            'Shop/Cart.php'       => <<<'PHP'
                <?php
                namespace App\Shop;
                use App\Catalog\Product;
                /** @param Product $product a docblock is not code */
                class Cart
                {
                    public function label(): string { return 'Product'; }
                    public function count(): int { return count([]); }
                    public function same(self $cart): static { return new static(); }
                }
                PHP,
        ]);

        self::assertSame([], Analysis::of($project->root)->dependenciesOf('App\Shop\Cart'));
    }
}
