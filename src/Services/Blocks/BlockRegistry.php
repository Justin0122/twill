<?php

namespace A17\Twill\Services\Blocks;

use A17\Twill\Services\Forms\InlineRepeater;
use A17\Twill\View\Components\Blocks\TwillBlockComponent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlockRegistry
{
    protected BlockCollection $collection;

    protected array $pendingBlockDirectories = [];

    protected array $pendingRepeaterDirectories = [];

    protected array $pendingComponentNamespaces = [];

    protected array $pendingManualBlocks = [];

    protected bool $hasHydrated = false;

    /**
     * @var array<string, InlineRepeater>
     */
    protected array $dynamicRepeaters = [];

    protected array $loadedDynamicRepeaters = [];

    protected ?array $cropConfigs = null;

    public function __construct(?BlockCollection $collection = null)
    {
        $this->collection = $collection ?? new BlockCollection();
    }

    public function registerBlockDirectory(string $path, string $source, ?string $renderNamespace = null): void
    {
        if (isset($this->pendingBlockDirectories[$path])) {
            return;
        }

        if ($this->collectionIsHydrated()) {
            $this->appendBlocks($this->readBlocksFromDirectory($path, $source, Block::TYPE_BLOCK, $renderNamespace));

            return;
        }

        $this->pendingBlockDirectories[$path] = [
            'source' => $source,
            'renderNamespace' => $renderNamespace,
        ];
    }

    public function registerRepeaterDirectory(string $path, string $source, ?string $renderNamespace = null): void
    {
        if (isset($this->pendingRepeaterDirectories[$path])) {
            return;
        }

        if ($this->collectionIsHydrated()) {
            $this->appendBlocks($this->readBlocksFromDirectory($path, $source, Block::TYPE_REPEATER, $renderNamespace));

            return;
        }

        $this->pendingRepeaterDirectories[$path] = [
            'source' => $source,
            'renderNamespace' => $renderNamespace,
        ];
    }

    public function registerComponentNamespace(string $namespace, string $path): void
    {
        if (! Str::startsWith($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        $this->pendingComponentNamespaces[$namespace] = $path;

        if ($this->collectionIsHydrated()) {
            $this->consumeComponentNamespaces();
        }
    }

    public function registerManualBlock(string $blockClass): void
    {
        $this->pendingManualBlocks[$blockClass] = $blockClass;

        if ($this->collectionIsHydrated()) {
            $this->consumeManualBlocks();
        }
    }

    public function registerDynamicRepeater(string $name, InlineRepeater $repeater): void
    {
        $this->dynamicRepeaters[$name] = $repeater;

        if ($this->collectionIsHydrated()) {
            $this->appendDynamicRepeaters();
        }
    }

    public function getAvailableRepeaters(): string
    {
        return $this->getCollection()->getRepeaters()->mapWithKeys(function (Block $repeater) {
            return [$repeater->name => $repeater->toList()];
        })->toJson();
    }

    public function findByName(string $name): ?Block
    {
        return $this->getAll()->first(function (Block $block) use ($name) {
            return $block->name === $name;
        });
    }

    public function findRepeaterByName(string $name): ?Block
    {
        $repeater = $this->getRepeaters()->first(function (Block $block) use ($name) {
            return $block->name === $name;
        });

        if ($repeater === null) {
            $repeater = $this->getRepeaters()->first(function (Block $block) use ($name) {
                return $block->name === 'dynamic-repeater-' . $name;
            });
        }

        return $repeater;
    }

    public function getCollection(): BlockCollection
    {
        $this->hydrateCollection();

        return $this->collection;
    }

    public function getBlocks(bool $withSettingsBlocks = false): Collection
    {
        $blocks = $this->getCollection()->getBlockList();

        if ($withSettingsBlocks) {
            return $blocks->merge($this->getSettingsBlocks());
        }

        return $blocks;
    }

    public function getSettingsBlocks(): Collection
    {
        return $this->getCollection()->getSettingsList();
    }

    public function getRepeaters(): Collection
    {
        return $this->getCollection()->getRepeaters();
    }

    public function getAll(): Collection
    {
        return $this->getBlocks()->merge($this->getRepeaters())->merge($this->getSettingsBlocks());
    }

    public function getAllCropConfigs($prefixKey = false): array
    {
        if ($this->cropConfigs === null) {
            $configs = config()->get('twill.block_editor.crops', []);
            $configs = $configs + (config()->get('twill.repeaters.crops') ?? []);

            foreach ($this->getCollection() as $block) {
                if (! $block->componentClass) {
                    continue;
                }

                $crops = $block->componentClass::getCrops();

                if ($crops !== []) {
                    $configs = array_merge($configs, $crops);
                }
            }

            $this->cropConfigs = $configs;
        }

        if ($prefixKey) {
            return Arr::prependKeysWith($this->cropConfigs, 'block_');
        }

        return $this->cropConfigs;
    }

    protected function hydrateCollection(): void
    {
        $this->consumeRepeaterDirectories();
        $this->consumeBlockDirectories();
        $this->consumeComponentNamespaces();
        $this->consumeManualBlocks();
        $this->appendDynamicRepeaters();
        $this->deduplicateTwillBlocks();

        $this->hasHydrated = true;
    }

    protected function consumeBlockDirectories(): void
    {
        foreach ($this->pendingBlockDirectories as $path => $data) {
            $this->appendBlocks($this->readBlocksFromDirectory(
                $path,
                $data['source'],
                Block::TYPE_BLOCK,
                $data['renderNamespace'] ?? null
            ));

            unset($this->pendingBlockDirectories[$path]);
        }
    }

    protected function consumeRepeaterDirectories(): void
    {
        foreach ($this->pendingRepeaterDirectories as $path => $data) {
            $this->appendBlocks($this->readBlocksFromDirectory(
                $path,
                $data['source'],
                Block::TYPE_REPEATER,
                $data['renderNamespace'] ?? null
            ));

            unset($this->pendingRepeaterDirectories[$path]);
        }
    }

    protected function consumeComponentNamespaces(): void
    {
        foreach ($this->pendingComponentNamespaces as $namespace => $path) {
            if (! file_exists($path)) {
                unset($this->pendingComponentNamespaces[$namespace]);

                continue;
            }

            $disk = Storage::build([
                'driver' => 'local',
                'root' => $path,
            ]);

            foreach ($disk->allFiles() as $file) {
                $class = $namespace . '\\' . Str::replace('/', '\\', Str::before($file, '.'));
                if (is_subclass_of($class, TwillBlockComponent::class)) {
                    $this->collection->add(Block::forComponent($class));
                }
            }

            unset($this->pendingComponentNamespaces[$namespace]);
        }
    }

    protected function consumeManualBlocks(): void
    {
        foreach ($this->pendingManualBlocks as $class) {
            $this->collection->add(Block::forComponent($class));

            unset($this->pendingManualBlocks[$class]);
        }
    }

    protected function appendBlocks(Collection $blocks): void
    {
        $blocks->each(function (Block $block) {
            $this->collection->add($block);
        });
    }

    protected function appendDynamicRepeaters(): void
    {
        $this->discoverDynamicRepeaters($this->collection);

        foreach ($this->dynamicRepeaters as $name => $dynamicRepeater) {
            if (isset($this->loadedDynamicRepeaters[$name])) {
                continue;
            }

            $this->collection->add($dynamicRepeater->asBlock());
            $this->loadedDynamicRepeaters[$name] = true;
        }
    }

    protected function discoverDynamicRepeaters(Collection $collection): void
    {
        foreach ($collection as $item) {
            if (! $item instanceof Block || ! $item->componentClass) {
                continue;
            }

            $component = new $item->componentClass();
            $component->getForm()->registerDynamicRepeaters();
        }
    }

    protected function deduplicateTwillBlocks(): void
    {
        $appBlocks = $this->collection->where('source', '!=', Block::SOURCE_TWILL);

        $this->collection = $this->collection->filter(function ($item) use ($appBlocks) {
            return ! $appBlocks->contains(function ($block) use ($item) {
                return $item->source === Block::SOURCE_TWILL && $item->name === $block->name;
            });
        });
    }

    protected function readBlocksFromDirectory(
        string $directory,
        string $source,
        string $type,
        ?string $renderNamespace = null
    ): Collection {
        if (! File::exists($directory)) {
            return new Collection();
        }

        return collect(File::files($directory))->map(function ($file) use ($source, $type, $renderNamespace) {
            return Block::make($file, $type, $source, null, $renderNamespace);
        });
    }

    protected function collectionIsHydrated(): bool
    {
        return $this->hasHydrated;
    }
}
