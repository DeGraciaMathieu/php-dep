<?php

declare(strict_types=1);

namespace PhpDep\Parser;

use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Parser\Visitor\ClassDeclarationVisitor;
use PhpDep\Parser\Visitor\DocblockVisitor;
use PhpDep\Parser\Visitor\RelationshipVisitor;
use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class PhpFileParser
{
    private \PhpParser\Parser $parser;
    private TypeResolver $typeResolver;

    public function __construct()
    {
        $this->parser       = (new ParserFactory())->createForNewestSupportedVersion();
        $this->typeResolver = new TypeResolver();
    }

    /**
     * Parse a PHP file and extract nodes + edges + warnings.
     *
     * @return array{nodes: GraphNode[], edges: GraphEdge[], warnings: AnalysisWarning[]}
     */
    public function parse(string $file, bool $skipDocblocks = false): array
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return [
                'nodes'    => [],
                'edges'    => [],
                'warnings' => [
                    new AnalysisWarning(
                        WarningType::PARSE_ERROR,
                        $file,
                        null,
                        "Cannot read file: {$file}",
                    ),
                ],
            ];
        }

        try {
            $stmts = $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            return [
                'nodes'    => [],
                'edges'    => [],
                'warnings' => [
                    new AnalysisWarning(
                        WarningType::PARSE_ERROR,
                        $file,
                        $e->getStartLine() > 0 ? $e->getStartLine() : null,
                        "Parse error: {$e->getRawMessage()}",
                    ),
                ],
            ];
        }

        if ($stmts === null) {
            return ['nodes' => [], 'edges' => [], 'warnings' => []];
        }

        $classVisitor        = new ClassDeclarationVisitor($file);
        $relationshipVisitor = new RelationshipVisitor($file, $this->typeResolver);
        $docblockVisitor     = $skipDocblocks ? null : new DocblockVisitor($file);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($classVisitor);
        $traverser->addVisitor($relationshipVisitor);

        if ($docblockVisitor !== null) {
            $traverser->addVisitor($docblockVisitor);
        }

        $traverser->traverse($stmts);

        $edges = array_merge(
            $relationshipVisitor->getEdges(),
            $docblockVisitor?->getEdges() ?? [],
        );

        return [
            'nodes'    => $classVisitor->getNodes(),
            'edges'    => $edges,
            'warnings' => $relationshipVisitor->getWarnings(),
        ];
    }
}
