# php-dep

PHP command-line dependency extractor. Writes the class dependency graph of a project as JSON: inheritance, interfaces, traits, injections, instantiations, static calls, attributes. The files are parsed in parallel.

## Installation

Download the PHAR of the latest release, it holds everything it needs:

```bash
wget https://github.com/DeGraciaMathieu/php-dep/releases/latest/download/php-dep.phar
php php-dep.phar --version
```

Or run it from the sources:

```bash
git clone https://github.com/DeGraciaMathieu/php-dep.git
cd php-dep
composer install
bin/php-dep --version
```

## Quick start

```bash
# Analyze the current directory
php php-dep.phar

# Analyze a specific project
php php-dep.phar /path/to/project/src > graph.json
```

## `analyze` command

```
php-dep [analyze] [<path>] [options]
```

`<path>` is optional, defaults to `.`. The `analyze` command name is optional too: it is the only command.

### Options

| Option | Shortcut | Description |
|---|---|---|
| `--exclude=dir` | | Exclude a directory (can be used multiple times) |
| `--include-vendor` | | Include the `vendor/` directory in the analysis |
| `--workers=N` | `-w` | Number of parallel processes (default: the CPU count, `1` to stay sequential) |
| `--help` | `-h` | Display the help |
| `--version` | `-V` | Display the version |

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Success |
| `1` | Analysis failure, nothing written |
| `2` | Invalid arguments |
| `3` | Graph written, but some files could not be parsed: see the `parse_error` warnings |

---

## Examples

### JSON pipeline with `jq`

```bash
# Count classes
php php-dep.phar src/ | jq '.meta.class_count'

# List all inheritance relationships
php php-dep.phar src/ | jq '[.edges[] | select(.type == "extends")]'

# Find classes with no dependants (leaves)
php php-dep.phar src/ | jq '[.classes[] | select(.dependants | length == 0) | .fqcn]'

# Find dependencies of a specific class
php php-dep.phar src/ | jq '.classes[] | select(.fqcn == "App\\Service\\UserService") | .dependencies'
```

### Exclude directories

```bash
php php-dep.phar . --exclude=tests --exclude=fixtures --exclude=migrations
```

### Analysis without vendor

By default, the files of `vendor/` are not analyzed, but the third-party classes your code uses still appear in the graph, as `external` nodes without dependencies of their own. To keep only your classes, filter out the `external` nodes, for instance with `jq '[.edges[] | select(.target | startswith("App\\"))]'`.

To analyze the vendor itself:

```bash
php php-dep.phar . --include-vendor
```

---

## Use as a library

```bash
composer config repositories.php-dep vcs https://github.com/DeGraciaMathieu/php-dep
composer require degraciamathieu/php-dep
```

```php
use PhpDep\Api\PhpDep;

$analysis = PhpDep::analyze('src');           // in parallel, over the CPU count; PhpDep::analyze('src', workers: 1) to stay sequential

$analysis->classes();                         // ['App\Catalog\Product', 'App\Shop\Cart']: the classes declared in the project, sorted
$analysis->dependenciesOf('App\Shop\Cart');   // ['App\Catalog\Product', 'DateTime']: what it uses, once and sorted, project and external classes alike
$analysis->isDeclared('DateTime');            // false: an external class
$analysis->fileOf('App\Shop\Cart');           // '/real/path/to/src/Shop/Cart.php'
$analysis->parseErrors();                     // ['/real/path/to/src/Broken.php' => 'Parse error: …']
```

`PhpDep\Api\PhpDep` and `PhpDep\Api\Analysis` are covered by the version of php-dep, like the JSON output. Every other class is internal and may change in any release.

---

## JSON output structure

```jsonc
{
  "meta": {
    "version": "2.0",
    "generated_at": "2026-02-26T10:00:00+00:00",
    "analyzed_path": "/path/to/project",
    "file_count": 42,
    "class_count": 38,      // internal nodes only
    "node_count": 95,       // internal + external (vendor, built-in)
    "edge_count": 312,
    "warning_count": 2
  },
  "classes": [
    {
      "fqcn": "App\\Service\\UserService",
      "type": "class",      // class | interface | trait | enum
      "file": "/path/to/UserService.php",
      "line": 12,
      "abstract": false,    // true for an abstract class only
      "dependencies": ["App\\Repository\\UserRepository", "Psr\\Log\\LoggerInterface"],
      "dependants":   ["App\\Controller\\UserController"]
    }
  ],
  "edges": [
    {
      "source": "App\\Service\\UserService",
      "target": "App\\Repository\\UserRepository",
      "type":   "param_type",   // see relationship types below
      "file":   "/path/to/UserService.php",
      "line":   23
    }
  ],
  "warnings": [
    {
      "type":    "dynamic_instantiation",
      "file":    "/path/to/Factory.php",
      "line":    45,
      "message": "Dynamic instantiation: new $variable()"
    }
  ]
}
```

---

## Relationship types (`edge.type`)

| Type | Description |
|---|---|
| `extends` | Class or interface inheritance |
| `implements` | Interface implementation |
| `uses_trait` | Trait usage |
| `param_type` | Type hint on a parameter of a method, closure or arrow function |
| `return_type` | Return type of a method, closure or arrow function |
| `property_type` | Class property type |
| `constant_type` | Typed class constant (PHP 8.3) |
| `attribute` | `#[Foo]` on a class, method, property, parameter or constant |
| `instantiates` | `new Foo()` |
| `static_call` | `Foo::method()` |
| `static_property` | `Foo::$prop` |
| `const_access` | `Foo::CONST`, and `Foo::class` |
| `instanceof` | `$x instanceof Foo` |
| `catches` | `catch (FooException $e)` |

An anonymous class is not a node of its own: what it extends, implements and uses is attributed to the class that declares it, and `new class {}` raises no `dynamic_instantiation` warning.

---

## Warnings

| Type | Trigger |
|---|---|
| `dynamic_instantiation` | `new $variable()` — class unknown at static analysis time |
| `parse_error` | Invalid or unreadable PHP file |

The JSON output includes them in `warnings[]`.

---

## How it works

- **Discovery**: `git ls-files` if inside a git repository, so that ignored files are left out, otherwise `RecursiveDirectoryIterator`. The `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `public/build/`, `.git/`, `.svn/`, `.hg/`, `.idea/` and `.vscode/` directories are excluded by default, the same way in both cases.
- **Parsing**: [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) v5, the only dependency: the command line is read without any console library. The `NameResolver` runs first in the traverser: all downstream names are resolved FQCNs.
- **Parallelism**: with the `pcntl` extension, the files are spread over forked processes; without it, they are parsed one after the other. The output is the same whatever the number of workers.
- **Memory**: each file's AST is freed immediately after extraction. Each process holds one AST at a time.
- **Vendor**: vendor classes are `external` nodes (leaves) — their files are not analyzed, unless `--include-vendor` is given.

## Requirements

- PHP >= 8.2
- Composer, only to run it from the sources
- The `pcntl` extension, optional: without it, the files are parsed one after the other
