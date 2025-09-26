<?php

namespace A17\Twill\Services\Blocks;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BlockImporter
{
    protected array $defaultViewMappings = [
        'twill/blocks' => [
            'views/twill/blocks',
            'resources/views/twill/blocks',
            'blocks',
        ],
        'twill/blocks/repeaters' => [
            'views/twill/blocks/repeaters',
            'resources/views/twill/blocks/repeaters',
            'repeaters',
        ],
    ];

    protected ?string $temporaryExtraction = null;

    public function __construct(protected Filesystem $files)
    {
    }

    public function import(string $source, bool $force = false): BlockImportResult
    {
        try {
            $root = $this->resolveSource($source);

            $manifest = BlockManifest::fromDirectory($root);
            $directories = $this->discoverDirectories($root, $manifest);

            $result = new BlockImportResult($root, $manifest);

            if ($directories === []) {
                $result->addWarning('No block views were discovered in the provided source.');

                return $result;
            }

            foreach ($directories as $target => $sources) {
                foreach ($sources as $sourceDirectory) {
                    $files = $this->files->allFiles($sourceDirectory);

                    if ($files === []) {
                        $result->addWarning('Directory ' . $sourceDirectory . ' did not contain any files.');

                        continue;
                    }

                    foreach ($files as $file) {
                        $relative = ltrim(Str::replaceFirst($sourceDirectory, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                        $destination = resource_path('views' . DIRECTORY_SEPARATOR . trim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative);
                        $normalized = $this->normalizePath($destination);

                        if ($this->files->exists($destination) && ! $force) {
                            $result->addSkipped($normalized);

                            continue;
                        }

                        $this->files->ensureDirectoryExists(dirname($destination));
                        $this->files->copy($file->getPathname(), $destination);
                        $result->addCopied($normalized);
                    }
                }
            }

            return $result;
        } finally {
            $this->cleanupTemporaryExtraction();
        }
    }

    protected function discoverDirectories(string $root, ?BlockManifest $manifest): array
    {
        $mappings = $manifest?->getViewMappings();

        if ($mappings === []) {
            $mappings = $this->defaultViewMappings;
        }

        $directories = [];

        foreach ($mappings as $target => $sources) {
            $sources = Collection::make($sources)
                ->map(fn ($path) => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR))
                ->filter(fn ($path) => $this->files->isDirectory($path))
                ->values()
                ->all();

            if ($sources !== []) {
                $directories[$target] = $sources;
            }
        }

        return $directories;
    }

    protected function resolveSource(string $source): string
    {
        $path = $this->guessFullPath($source);

        if (! $this->files->exists($path)) {
            throw new RuntimeException('Unable to locate blocks at path: ' . $source);
        }

        if ($this->files->isFile($path)) {
            return $this->extractArchive($path);
        }

        return $this->guessRootDirectory($path);
    }

    protected function guessFullPath(string $source): string
    {
        if ($this->files->exists($source)) {
            return $source;
        }

        $candidate = base_path($source);

        if ($this->files->exists($candidate)) {
            return $candidate;
        }

        return $source;
    }

    protected function extractArchive(string $archive): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Zip extension is not available to extract block archives.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open archive: ' . $archive);
        }

        $temporary = storage_path('app/twill/tmp-block-' . Str::uuid());

        $this->files->ensureDirectoryExists($temporary);

        if (! $zip->extractTo($temporary)) {
            $zip->close();

            throw new RuntimeException('Unable to extract archive: ' . $archive);
        }

        $zip->close();

        $this->temporaryExtraction = $temporary;

        return $this->guessRootDirectory($temporary);
    }

    protected function guessRootDirectory(string $path): string
    {
        $directories = $this->files->directories($path);

        if (count($directories) === 1 && $this->files->isDirectory($directories[0])) {
            return $directories[0];
        }

        return $path;
    }

    protected function normalizePath(string $path): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        if (Str::startsWith($path, $base)) {
            return Str::replaceFirst($base, '', $path);
        }

        return $path;
    }

    protected function cleanupTemporaryExtraction(): void
    {
        if ($this->temporaryExtraction === null) {
            return;
        }

        $this->files->deleteDirectory($this->temporaryExtraction);
        $this->temporaryExtraction = null;
    }
}
