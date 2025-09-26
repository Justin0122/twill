<?php

namespace A17\Twill\Services\Blocks;

class BlockImportResult
{
    public array $copied = [];

    public array $skipped = [];

    public array $warnings = [];

    public function __construct(public string $source, public ?BlockManifest $manifest = null)
    {
    }

    public function addCopied(string $path): void
    {
        $this->copied[] = $path;
    }

    public function addSkipped(string $path): void
    {
        $this->skipped[] = $path;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function hasConfigRecommendations(): bool
    {
        return $this->manifest?->hasConfig() ?? false;
    }

    public function configRecommendations(): array
    {
        return $this->manifest?->config() ?? [];
    }
}
