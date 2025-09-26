<?php

namespace A17\Twill;

use A17\Twill\Services\Blocks\Block;
use A17\Twill\Services\Blocks\BlockCollection;
use A17\Twill\Services\Blocks\BlockRegistry;
use A17\Twill\Services\Forms\InlineRepeater;
use Illuminate\Support\Collection;

class TwillBlocks
{
    public function __construct(protected BlockRegistry $registry)
    {
    }

    public function registerPackageBlocksDirectory(string $path, ?string $renderNamespace = null): void
    {
        $this->registry->registerBlockDirectory($path, Block::SOURCE_VENDOR, $renderNamespace);
    }

    public function registerPackageRepeatersDirectory(string $path, ?string $renderNamespace = null): void
    {
        $this->registry->registerRepeaterDirectory($path, Block::SOURCE_VENDOR, $renderNamespace);
    }

    public function registerSourceDirectory(string $path, string $type, string $source, ?string $renderNamespace = null): void
    {
        if ($type === Block::TYPE_BLOCK) {
            $this->registry->registerBlockDirectory($path, $source, $renderNamespace);
        } elseif ($type === Block::TYPE_REPEATER) {
            $this->registry->registerRepeaterDirectory($path, $source, $renderNamespace);
        }
    }

    public function registerComponentBlocks(string $namespace, string $path): void
    {
        $this->registry->registerComponentNamespace($namespace, $path);
    }

    public function registerDynamicRepeater(string $name, InlineRepeater $repeater): void
    {
        $this->registry->registerDynamicRepeater($name, $repeater);
    }

    public function registerManualBlock(string $blockClass): void
    {
        $this->registry->registerManualBlock($blockClass);
    }

    public function getBlockCollection(): BlockCollection
    {
        return $this->registry->getCollection();
    }

    public function getAvailableRepeaters(): string
    {
        return $this->registry->getAvailableRepeaters();
    }

    public function findByName(string $name): ?Block
    {
        return $this->registry->findByName($name);
    }

    public function findRepeaterByName(string $name): ?Block
    {
        return $this->registry->findRepeaterByName($name);
    }

    /**
     * @return Collection|Block[]
     */
    public function getBlocks(bool $withSettingsBlocks = false): Collection
    {
        return $this->registry->getBlocks($withSettingsBlocks);
    }

    public function getSettingsBlocks(): Collection
    {
        return $this->registry->getSettingsBlocks();
    }

    /**
     * @return Collection|Block[]
     */
    public function getRepeaters(): Collection
    {
        return $this->registry->getRepeaters();
    }

    /**
     * @return Collection|Block[]
     */
    public function getAll(): Collection
    {
        return $this->registry->getAll();
    }

    public function getAllCropConfigs($prefixKey = false): array
    {
        return $this->registry->getAllCropConfigs($prefixKey);
    }
}
