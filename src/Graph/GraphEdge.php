<?php

declare(strict_types=1);

namespace PhpDep\Graph;

final readonly class GraphEdge
{
    public function __construct(
        public string     $source,
        public string     $target,
        public EdgeType   $type,
        public Confidence $confidence,
        public ?string    $file     = null,
        public ?int       $line     = null,
        public array      $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'source'     => $this->source,
            'target'     => $this->target,
            'type'       => $this->type->value,
            'confidence' => $this->confidence->value,
            'file'       => $this->file,
            'line'       => $this->line,
            'metadata'   => $this->metadata,
        ];
    }
}
