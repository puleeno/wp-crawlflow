<?php

namespace CrawlFlow\Flow;

/**
 * Flow Configuration
 * Represents a complete flow configuration with nodes and edges
 */
class FlowConfig
{
    /**
     * @var array Project settings
     */
    private array $projectSettings;

    /**
     * @var array Nodes in the flow
     */
    private array $nodes;

    /**
     * @var array Edges connecting nodes
     */
    private array $edges;

    /**
     * Constructor
     */
    public function __construct(array $projectSettings = [], array $nodes = [], array $edges = [])
    {
        $this->projectSettings = $projectSettings;
        $this->nodes = $nodes;
        $this->edges = $edges;
    }

    /**
     * Create from array (typically from JSON)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['projectSettings'] ?? [],
            $data['nodes'] ?? [],
            $data['edges'] ?? []
        );
    }

    /**
     * Convert to array (for JSON encoding)
     */
    public function toArray(): array
    {
        return [
            'projectSettings' => $this->projectSettings,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }

    /**
     * Get project settings
     */
    public function getProjectSettings(): array
    {
        return $this->projectSettings;
    }

    /**
     * Get all nodes
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Get node by ID
     */
    public function getNode(string $nodeId): ?array
    {
        foreach ($this->nodes as $node) {
            if (($node['id'] ?? '') === $nodeId) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Get nodes by type
     */
    public function getNodesByType(string $type): array
    {
        return array_filter($this->nodes, function ($node) use ($type) {
            return ($node['type'] ?? '') === $type;
        });
    }

    /**
     * Get all edges
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Get edges from a node
     */
    public function getEdgesFrom(string $nodeId): array
    {
        return array_filter($this->edges, function ($edge) use ($nodeId) {
            return ($edge['source'] ?? '') === $nodeId;
        });
    }

    /**
     * Get edges to a node
     */
    public function getEdgesTo(string $nodeId): array
    {
        return array_filter($this->edges, function ($edge) use ($nodeId) {
            return ($edge['target'] ?? '') === $nodeId;
        });
    }

    /**
     * Get child nodes of a node
     */
    public function getChildNodes(string $nodeId): array
    {
        $childIds = array_map(function ($edge) {
            return $edge['target'] ?? null;
        }, $this->getEdgesFrom($nodeId));

        return array_filter($this->nodes, function ($node) use ($childIds) {
            return in_array($node['id'] ?? '', $childIds);
        });
    }

    /**
     * Get parent nodes of a node
     */
    public function getParentNodes(string $nodeId): array
    {
        $parentIds = array_map(function ($edge) {
            return $edge['source'] ?? null;
        }, $this->getEdgesTo($nodeId));

        return array_filter($this->nodes, function ($node) use ($parentIds) {
            return in_array($node['id'] ?? '', $parentIds);
        });
    }

    /**
     * Validate flow configuration
     */
    public function validate(): array
    {
        $errors = [];

        // Check for at least one start node
        $startNodes = $this->getNodesByType('start');
        if (empty($startNodes)) {
            $errors[] = 'Flow must have at least one start node';
        }

        // Check for repository node
        $repositoryNodes = $this->getNodesByType('repository');
        if (empty($repositoryNodes)) {
            $errors[] = 'Flow must have a repository node';
        }

        // Validate edges reference existing nodes
        $nodeIds = array_map(function ($node) {
            return $node['id'] ?? '';
        }, $this->nodes);

        foreach ($this->edges as $edge) {
            $source = $edge['source'] ?? '';
            $target = $edge['target'] ?? '';

            if (!in_array($source, $nodeIds)) {
                $errors[] = "Edge references non-existent source node: {$source}";
            }

            if (!in_array($target, $nodeIds)) {
                $errors[] = "Edge references non-existent target node: {$target}";
            }
        }

        return $errors;
    }
}

