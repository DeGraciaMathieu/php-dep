<?php

declare(strict_types=1);

namespace PhpDep\Tests\Support;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\GraphNode;
use PhpDep\Parser\PhpFileParser;
use PhpDep\Warning\AnalysisWarning;

/**
 * What php-dep found in a project, read the way a tool using its graph reads it.
 */
final class Analysis
{
    /**
     * @param AnalysisWarning[] $warnings
     */
    private function __construct(
        public readonly DependencyGraph $graph,
        public readonly array $warnings,
    ) {}

    public static function of(string $path, int $workers = 1): self
    {
        $result = (new ProjectAnalyzer(new PhpFileParser()))->analyze($path, new AnalyzerConfig(workers: $workers));

        return new self($result['graph'], $result['warnings']);
    }

    /** @return string[] the classes declared in the project, sorted */
    public function classes(): array
    {
        $classes = array_map(fn(GraphNode $node) => $node->fqcn, $this->graph->getInternalNodes());
        sort($classes);

        return $classes;
    }

    /** @return string[] what the class depends on, sorted, project classes and external ones alike */
    public function dependenciesOf(string $class): array
    {
        $dependencies = $this->graph->dependenciesOf($class);
        sort($dependencies);

        return $dependencies;
    }

    /** @return string[] the project classes the class depends on, sorted */
    public function internalDependenciesOf(string $class): array
    {
        return array_values(array_filter(
            $this->dependenciesOf($class),
            fn(string $dependency) => $this->graph->getNode($dependency)?->isExternal() === false,
        ));
    }

    /** Number of distinct dependencies between two classes of the project */
    public function internalDependencyCount(): int
    {
        $count = 0;
        foreach ($this->classes() as $class) {
            $count += count($this->internalDependenciesOf($class));
        }

        return $count;
    }

    /** @return string[] the type of each warning */
    public function warningTypes(): array
    {
        return array_map(fn(AnalysisWarning $warning) => $warning->type->value, $this->warnings);
    }
}
