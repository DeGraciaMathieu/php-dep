# php-dep

PHP command-line dependency analyzer. Extracts and visualizes all class relationships in a project: inheritance, interfaces, traits, injections, instantiations, static calls, docblocks.

## Installation

```bash
git clone <repo>
cd php-dep
composer install
chmod +x bin/php-dep
```

For global access:

```bash
ln -s /absolute/path/to/bin/php-dep /usr/local/bin/php-dep
```

## Quick start

```bash
# Analyze the current directory
./bin/php-dep analyze

# Analyze a specific project
./bin/php-dep analyze /path/to/project/src

# JSON output (for scripts, CI, jq…)
./bin/php-dep analyze src/ --format=json -q

# Zoom in on a class
./bin/php-dep analyze src/ --class='App\Service\UserService'
```

## `analyze` command

```
php-dep analyze [<path>] [options]
```

`<path>` is optional, defaults to `.`

### Options

| Option | Shortcut | Description |
|---|---|---|
| `--format=text\|json` | `-f` | Output format (default: `text`) |
| `--class=FQCN` | `-c` | Focus on a class (full FQCN) |
| `--sort=alpha\|deps\|fanin` | `-s` | Table sort: alphabetical, number of outgoing dependencies, number of incoming dependencies (default: `alpha`) |
| `--limit=N` | `-l` | Limit output to N classes |
| `--exclude=dir` | | Exclude a directory (can be used multiple times) |
| `--include-vendor` | | Include the `vendor/` directory in the analysis |
| `--skip-docblocks` | | Ignore `@param`/`@return`/`@throws` annotations |
| `--quiet` | `-q` | Suppresses the progress bar and warnings (stdout preserved) |
| `--verbose` | `-v` | Show warning details |

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Success |
| `1` | Parsing error(s) |
| `2` | Internal error |
| `3` | Invalid arguments |

---

## Examples

### Project overview

```bash
./bin/php-dep analyze src/
```

Displays a table with, for each class: type, number of outgoing dependencies (what it uses), incoming dependencies (what uses it), and source file.

### Find the most coupled classes

```bash
# Highest dependency consumers
./bin/php-dep analyze src/ --sort=deps --limit=10

# Most used by others (high fan-in)
./bin/php-dep analyze src/ --sort=fanin --limit=10
```

### Inspect a class

```bash
./bin/php-dep analyze src/ --class='PhpDep\Parser\PhpFileParser' -v
```

Displays two tables: what the class uses (outgoing dependencies) and who uses it (incoming dependencies), with the relationship type and source line.

### JSON pipeline with `jq`

```bash
# Count classes
./bin/php-dep analyze src/ -f json -q | jq '.meta.class_count'

# List all inheritance relationships
./bin/php-dep analyze src/ -f json -q | jq '[.edges[] | select(.type == "extends")]'

# Find classes with no dependants (leaves)
./bin/php-dep analyze src/ -f json -q | jq '[.classes[] | select(.dependants | length == 0) | .fqcn]'

# Find dependencies of a specific class
./bin/php-dep analyze src/ -f json -q | jq '.classes[] | select(.fqcn == "App\\Service\\UserService") | .dependencies'
```

### Exclude directories

```bash
./bin/php-dep analyze . --exclude=tests --exclude=fixtures --exclude=migrations
```

### Analysis without vendor

By default, `vendor/` is excluded from the analysis but third-party classes appear as `external` nodes in the graph. No additional option is needed to ignore them entirely.

To analyze the vendor itself:

```bash
./bin/php-dep analyze . --include-vendor
```

---

## JSON output structure

```jsonc
{
  "meta": {
    "version": "1.0",
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
      "dependencies": ["App\\Repository\\UserRepository", "Psr\\Log\\LoggerInterface"],
      "dependants":   ["App\\Controller\\UserController"]
    }
  ],
  "edges": [
    {
      "source":     "App\\Service\\UserService",
      "target":     "App\\Repository\\UserRepository",
      "type":       "param_type",   // see relationship types below
      "confidence": "certain",      // certain | high | medium | low
      "file":       "/path/to/UserService.php",
      "line":       23,
      "metadata":   {}
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

### Tier 1 — Maximum certainty (AST)

| Type | Description |
|---|---|
| `extends` | Class or interface inheritance |
| `implements` | Interface implementation |
| `uses_trait` | Trait usage |
| `param_type` | Type hint on a method parameter |
| `return_type` | Method return type |
| `property_type` | Class property type |
| `instantiates` | `new Foo()` |
| `static_call` | `Foo::method()` |
| `static_property` | `Foo::$prop` |
| `const_access` | `Foo::CONST` |
| `instanceof` | `$x instanceof Foo` |
| `catches` | `catch (FooException $e)` |

### Tier 2 — High confidence (docblocks)

Extracted from `@param`, `@return`, `@var`, `@throws`. Confidence: `high`.

| Type | Source |
|---|---|
| `docblock_param` | `@param FooType $x` |
| `docblock_return` | `@return FooType` |
| `docblock_var` | `@var FooType` |
| `docblock_throws` | `@throws FooException` |

### Confidence levels

| Value | Meaning |
|---|---|
| `certain` | Structural relationship guaranteed by the AST |
| `high` | Docblock, highly probable |
| `medium` | Ambiguous pattern (`instanceof`) |
| `low` | Dynamic pattern |

---

## Warnings

| Type | Trigger |
|---|---|
| `dynamic_instantiation` | `new $variable()` — class unknown at static analysis time |
| `dynamic_call` | Unresolvable dynamic call |
| `parse_error` | Invalid or unreadable PHP file |

Warnings are printed to `stderr`. The JSON output includes them in `warnings[]`. With `-v`, the text output lists them at the end of the report.

---

## How it works

- **Discovery**: `git ls-files` if inside a git repository, otherwise `RecursiveDirectoryIterator`. The `vendor/`, `node_modules/`, and `.git/` directories are excluded by default.
- **Parsing**: [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) v5. The `NameResolver` runs first in the traverser: all downstream names are resolved FQCNs.
- **Docblocks**: [phpstan/phpdoc-parser](https://github.com/phpstan/phpdoc-parser) for `@param`, `@return`, `@var`, `@throws`.
- **Memory**: each file's AST is freed immediately after extraction (streaming). Only one AST in memory at a time.
- **Vendor**: in `boundary` mode (default), vendor classes are `external` nodes (leaves) — their files are not analyzed.

## Requirements

- PHP >= 8.2
- Composer
