<?php

declare(strict_types=1);

namespace PhpDep\Api;

use PhpDep\Graph\DependencyGraph;
use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;

/**
 * What php-dep found in a project: the classes it declares, and what each of them depends on.
 */
final class Analysis
{
    /**
     * @param array<string, string>   $files        declared class => absolute real path of its file
     * @param array<string, string[]> $dependencies declared class => the classes it depends on
     * @param array<string, string>   $parseErrors  absolute real path of the file => why it could not be parsed
     */
    private function __construct(
        private readonly array $files,
        private readonly array $dependencies,
        private readonly array $parseErrors,
    ) {}

    /**
     * @internal built by PhpDep::analyze()
     * @param AnalysisWarning[] $warnings
     */
    public static function of(DependencyGraph $graph, array $warnings): self
    {
        $files        = [];
        $dependencies = [];

        foreach ($graph->getInternalNodes() as $node) {
            $files[$node->fqcn] = $node->file;

            $dependencies[$node->fqcn] = $graph->dependenciesOf($node->fqcn);
            sort($dependencies[$node->fqcn]);
        }

        ksort($files);

        $parseErrors = [];
        foreach ($warnings as $warning) {
            if ($warning->type === WarningType::PARSE_ERROR) {
                $parseErrors[$warning->file] = $warning->message;
            }
        }

        return new self($files, $dependencies, $parseErrors);
    }

    /** @return string[] the classes, interfaces, traits and enums declared in the project, sorted */
    public function classes(): array
    {
        return array_keys($this->files);
    }

    /** Whether the class is declared in the project, written as classes() and dependenciesOf() give it */
    public function isDeclared(string $class): bool
    {
        return isset($this->files[$class]);
    }

    /** The absolute real path of the file that declares the class, null when the project does not declare it */
    public function fileOf(string $class): ?string
    {
        return $this->files[$class] ?? null;
    }

    /**
     * Each class the given one uses in its code, once and sorted: classes of the project and external ones alike,
     * written as they are declared whatever the case the code uses.
     *
     * @return string[]
     */
    public function dependenciesOf(string $class): array
    {
        return $this->dependencies[$class] ?? [];
    }

    /** @return array<string, string> the files that could not be parsed, absolute real path => reason */
    public function parseErrors(): array
    {
        return $this->parseErrors;
    }
}
