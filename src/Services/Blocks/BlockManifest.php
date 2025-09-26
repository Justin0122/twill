<?php

namespace A17\Twill\Services\Blocks;

use Illuminate\Support\Arr;

class BlockManifest
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        protected array $views = [],
        protected array $config = [],
        protected array $meta = []
    ) {
    }

    public static function fromDirectory(string $directory): ?self
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'twill-block.json';

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        $meta = Arr::except($decoded, ['name', 'description', 'views', 'config']);

        return new self(
            $decoded['name'] ?? basename($directory),
            $decoded['description'] ?? null,
            $decoded['views'] ?? [],
            $decoded['config'] ?? [],
            $meta
        );
    }

    public function hasViews(): bool
    {
        return ! empty($this->views);
    }

    public function getViewMappings(): array
    {
        if ($this->views === [] || Arr::isAssoc($this->views) === false) {
            $sources = array_values($this->views);

            if ($sources === []) {
                return [];
            }

            return ['twill/blocks' => $sources];
        }

        $mappings = [];

        foreach ($this->views as $target => $sources) {
            $mappings[$target] = is_array($sources) ? $sources : [$sources];
        }

        return $mappings;
    }

    public function hasConfig(): bool
    {
        return ! empty($this->config);
    }

    public function config(): array
    {
        return $this->config;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
