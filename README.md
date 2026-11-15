# php-dep

PHP command-line dependency extractor. Writes the class dependency graph of a project as JSON: inheritance, interfaces, traits, injections, instantiations, static calls, attributes. The files are parsed in parallel.

## Installation

```bash
git clone https://github.com/DeGraciaMathieu/php-dep.git
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
./bin/php-dep analyze /path/to/project/src > graph.json
```

## `analyze` command

```
php-dep analyze [<path>] [options]
```

`<path>` is optional, defaults to `.`

### Options

| Option | Shortcut | Description |
|---|---|---|
| `--exclude=dir` | | Exclude a directory (can be used multiple times) |
| `--include-vendor` | | Include the `vendor/` directory in the analysis |
| `--workers=N` | `-w` | Number of parallel processes (default: the CPU count, `1` to stay sequential) |

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Success |
| `1` | Parsing error(s) or analysis failure |
| `2` | Invalid arguments |

---

## Examples

### JSON pipeline with `jq`

```bash
# Count classes
./bin/php-dep analyze src/ | jq '.meta.class_count'

# List all inheritance relationships
./bin/php-dep analyze src/ | jq '[.edges[] | select(.type == "extends")]'

# Find classes with no dependants (leaves)
./bin/php-dep analyze src/ | jq '[.classes[] | select(.dependants | length == 0) | .fqcn]'

# Find dependencies of a specific class
./bin/php-dep analyze src/ | jq '.classes[] | select(.fqcn == "App\\Service\\UserService") | .dependencies'
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
      "abstract": false,    // true for an abstract class only
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
| `param_type` | Type hint on a parameter of a method, closure or arrow function |
| `return_type` | Return type of a method, closure or arrow function |
| `property_type` | Class property type |
| `constant_type` | Typed class constant (PHP 8.3) |
| `attribute` | `#[Foo]` on a class, method, property, parameter or constant |
| `instantiates` | `new Foo()` |
| `static_call` | `Foo::method()` |
| `static_property` | `Foo::$prop` |
| `const_access` | `Foo::CONST` |
| `instanceof` | `$x instanceof Foo` |
| `catches` | `catch (FooException $e)` |

### Confidence levels

| Value | Meaning |
|---|---|
| `certain` | Structural relationship guaranteed by the AST |
| `medium` | Ambiguous pattern (`instanceof`) |
| `low` | Dynamic pattern |

---

## Warnings

| Type | Trigger |
|---|---|
| `dynamic_instantiation` | `new $variable()` — class unknown at static analysis time |
| `dynamic_call` | Unresolvable dynamic call |
| `parse_error` | Invalid or unreadable PHP file |

The JSON output includes them in `warnings[]`.

---

## How it works

- **Discovery**: `git ls-files` if inside a git repository, otherwise `RecursiveDirectoryIterator`. The `vendor/`, `node_modules/`, and `.git/` directories are excluded by default.
- **Parsing**: [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) v5. The `NameResolver` runs first in the traverser: all downstream names are resolved FQCNs.
- **Parallelism**: with the `pcntl` extension, the files are spread over forked processes; without it, they are parsed one after the other. The output is the same whatever the number of workers.
- **Memory**: each file's AST is freed immediately after extraction. Each process holds one AST at a time.
- **Vendor**: in `boundary` mode (default), vendor classes are `external` nodes (leaves) — their files are not analyzed.

## Requirements

- PHP >= 8.2
- Composer
