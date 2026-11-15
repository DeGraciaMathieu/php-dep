<?php

declare(strict_types=1);

namespace PhpDep\Parser;

use PhpDep\Parser\Visitor\ClassDeclarationVisitor;
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

    /** Parse a PHP file and extract nodes + edges + warnings */
    public function parse(string $file): ParsedFile
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return new ParsedFile(warnings: [
                new AnalysisWarning(
                    WarningType::PARSE_ERROR,
                    $file,
                    null,
                    "Cannot read file: {$file}",
                ),
            ]);
        }

        try {
            $stmts = $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            return new ParsedFile(warnings: [
                new AnalysisWarning(
                    WarningType::PARSE_ERROR,
                    $file,
                    $e->getStartLine() > 0 ? $e->getStartLine() : null,
                    "Parse error: {$e->getRawMessage()}",
                ),
            ]);
        }

        if ($stmts === null) {
            return new ParsedFile();
        }

        $scope               = new ClassScope();
        $classVisitor        = new ClassDeclarationVisitor($file);
        $relationshipVisitor = new RelationshipVisitor($file, $this->typeResolver, $scope);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($scope);
        $traverser->addVisitor($classVisitor);
        $traverser->addVisitor($relationshipVisitor);
        $traverser->traverse($stmts);

        return new ParsedFile(
            nodes:    $classVisitor->getNodes(),
            edges:    $relationshipVisitor->getEdges(),
            warnings: $relationshipVisitor->getWarnings(),
        );
    }
}
