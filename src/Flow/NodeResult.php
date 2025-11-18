<?php

namespace CrawlFlow\Flow;

/**
 * Node Execution Result
 */
class NodeResult
{
    /**
     * @var bool Success status
     */
    private bool $success;

    /**
     * @var array Output data/resources
     */
    private array $output = [];

    /**
     * @var string|null Error message
     */
    private ?string $error = null;

    /**
     * @var array Metadata
     */
    private array $metadata = [];

    /**
     * Constructor
     */
    public function __construct(bool $success = true, array $output = [], ?string $error = null, array $metadata = [])
    {
        $this->success = $success;
        $this->output = $output;
        $this->error = $error;
        $this->metadata = $metadata;
    }

    /**
     * Create success result
     */
    public static function success(array $output = [], array $metadata = []): self
    {
        return new self(true, $output, null, $metadata);
    }

    /**
     * Create error result
     */
    public static function error(string $error, array $metadata = []): self
    {
        return new self(false, [], $error, $metadata);
    }

    /**
     * Check if successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get output
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Get error
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

