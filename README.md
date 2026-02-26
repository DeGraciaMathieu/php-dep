# php-dep

Analyseur de dépendances PHP en ligne de commande. Extrait et visualise toutes les relations entre classes d'un projet : héritage, interfaces, traits, injections, instanciations, appels statiques, docblocks.

## Installation

```bash
git clone <repo>
cd php-dep
composer install
chmod +x bin/php-dep
```

Pour un accès global :

```bash
ln -s /chemin/absolu/vers/bin/php-dep /usr/local/bin/php-dep
```

## Usage rapide

```bash
# Analyser le répertoire courant
./bin/php-dep analyze

# Analyser un projet spécifique
./bin/php-dep analyze /path/to/project/src

# Sortie JSON (pour scripts, CI, jq…)
./bin/php-dep analyze src/ --format=json -q

# Zoomer sur une classe
./bin/php-dep analyze src/ --class='App\Service\UserService'
```

## Commande `analyze`

```
php-dep analyze [<path>] [options]
```

`<path>` est optionnel, défaut : `.`

### Options

| Option | Raccourci | Description |
|---|---|---|
| `--format=text\|json` | `-f` | Format de sortie (défaut : `text`) |
| `--class=FQCN` | `-c` | Focaliser sur une classe (FQCN complet) |
| `--sort=alpha\|deps\|fanin` | `-s` | Tri du tableau : alphabétique, nb de dépendances sortantes, nb de dépendances entrantes (défaut : `alpha`) |
| `--limit=N` | `-l` | Limiter l'affichage à N classes |
| `--exclude=dir` | | Exclure un répertoire (cumulable) |
| `--include-vendor` | | Inclure le dossier `vendor/` dans l'analyse |
| `--skip-docblocks` | | Ignorer les annotations `@param`/`@return`/`@throws` |
| `--quiet` | `-q` | Supprime la barre de progression et les warnings (stdout préservé) |
| `--verbose` | `-v` | Affiche le détail des warnings |

### Codes de sortie

| Code | Signification |
|---|---|
| `0` | Succès |
| `1` | Erreur(s) de parsing |
| `2` | Erreur interne |
| `3` | Arguments invalides |

---

## Exemples

### Vue d'ensemble d'un projet

```bash
./bin/php-dep analyze src/
```

Affiche un tableau avec, pour chaque classe : type, nombre de dépendances sortantes (ce qu'elle utilise), entrantes (ce qui l'utilise), et fichier source.

### Trouver les classes les plus couplées

```bash
# Les plus grosses consommatrices de dépendances
./bin/php-dep analyze src/ --sort=deps --limit=10

# Les plus utilisées par les autres (fan-in élevé)
./bin/php-dep analyze src/ --sort=fanin --limit=10
```

### Inspecter une classe

```bash
./bin/php-dep analyze src/ --class='PhpDep\Parser\PhpFileParser' -v
```

Affiche deux tableaux : ce que la classe utilise (dépendances sortantes) et qui l'utilise (dépendances entrantes), avec le type de relation et la ligne source.

### Pipeline JSON avec `jq`

```bash
# Compter les classes
./bin/php-dep analyze src/ -f json -q | jq '.meta.class_count'

# Lister toutes les relations d'héritage
./bin/php-dep analyze src/ -f json -q | jq '[.edges[] | select(.type == "extends")]'

# Trouver les classes sans dépendants (feuilles)
./bin/php-dep analyze src/ -f json -q | jq '[.classes[] | select(.dependants | length == 0) | .fqcn]'

# Trouver les dépendances d'une classe précise
./bin/php-dep analyze src/ -f json -q | jq '.classes[] | select(.fqcn == "App\\Service\\UserService") | .dependencies'
```

### Exclure des répertoires

```bash
./bin/php-dep analyze . --exclude=tests --exclude=fixtures --exclude=migrations
```

### Analyse sans le vendor

Par défaut, `vendor/` est exclu de l'analyse mais les classes tierces apparaissent comme nœuds `external` dans le graphe. Pour les ignorer complètement, aucune option supplémentaire n'est nécessaire.

Pour analyser le vendor lui-même :

```bash
./bin/php-dep analyze . --include-vendor
```

---

## Structure de la sortie JSON

```jsonc
{
  "meta": {
    "version": "1.0",
    "generated_at": "2026-02-26T10:00:00+00:00",
    "analyzed_path": "/path/to/project",
    "file_count": 42,
    "class_count": 38,      // nœuds internes uniquement
    "node_count": 95,       // internes + externes (vendor, built-in)
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
      "type":       "param_type",   // voir types de relations ci-dessous
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

## Types de relations (`edge.type`)

### Tier 1 — Certitude maximale (AST)

| Type | Description |
|---|---|
| `extends` | Héritage de classe ou d'interface |
| `implements` | Implémentation d'interface |
| `uses_trait` | Utilisation d'un trait |
| `param_type` | Type hint sur un paramètre de méthode |
| `return_type` | Type de retour d'une méthode |
| `property_type` | Type d'une propriété de classe |
| `instantiates` | `new Foo()` |
| `static_call` | `Foo::method()` |
| `static_property` | `Foo::$prop` |
| `const_access` | `Foo::CONST` |
| `instanceof` | `$x instanceof Foo` |
| `catches` | `catch (FooException $e)` |

### Tier 2 — Haute confiance (docblocks)

Extraits depuis `@param`, `@return`, `@var`, `@throws`. Confidence : `high`.

| Type | Source |
|---|---|
| `docblock_param` | `@param FooType $x` |
| `docblock_return` | `@return FooType` |
| `docblock_var` | `@var FooType` |
| `docblock_throws` | `@throws FooException` |

### Niveaux de confiance

| Valeur | Signification |
|---|---|
| `certain` | Relation structurelle garantie par l'AST |
| `high` | Docblock, fortement probable |
| `medium` | Pattern ambigu (`instanceof`) |
| `low` | Pattern dynamique |

---

## Warnings

| Type | Déclencheur |
|---|---|
| `dynamic_instantiation` | `new $variable()` — classe inconnue à l'analyse statique |
| `dynamic_call` | Appel dynamique non résolvable |
| `parse_error` | Fichier PHP invalide ou illisible |

Les warnings sont affichés dans `stderr`. La sortie JSON les inclut dans `warnings[]`. Avec `-v`, le texte les liste en fin de rapport.

---

## Fonctionnement interne

- **Découverte** : `git ls-files` si dans un dépôt git, sinon `RecursiveDirectoryIterator`. Les dossiers `vendor/`, `node_modules/`, `.git/` sont exclus par défaut.
- **Parsing** : [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) v5. Le `NameResolver` tourne en premier dans le traverser : tous les noms en aval sont des FQCN résolus.
- **Docblocks** : [phpstan/phpdoc-parser](https://github.com/phpstan/phpdoc-parser) pour `@param`, `@return`, `@var`, `@throws`.
- **Mémoire** : l'AST de chaque fichier est libéré immédiatement après extraction (streaming). Un seul AST en mémoire à la fois.
- **Vendor** : en mode `boundary` (défaut), les classes vendor sont des nœuds `external` (feuilles) — leurs fichiers ne sont pas analysés.

## Prérequis

- PHP >= 8.2
- Composer
