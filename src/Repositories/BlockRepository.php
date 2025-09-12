<?php

namespace A17\Twill\Repositories;

use A17\Twill\Facades\TwillBlocks;
use A17\Twill\Helpers\BlockRenderer;
use A17\Twill\Models\Block;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Models\RelatedItem;
use A17\Twill\Repositories\Behaviors\HandleFiles;
use A17\Twill\Repositories\Behaviors\HandleMedias;
use A17\Twill\Services\Blocks\Block as BlockConfig;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BlockRepository extends ModuleRepository
{
    use HandleMedias;
    use HandleFiles;

    protected Config $config;

    public function __construct(Config $config)
    {
        $blockModel = twillModel('block');
        $this->model = new $blockModel();
        $this->config = $config;
    }

    public function getCrops(string $role): array
    {
        $cacheKey = 'crops_' . $role;
        return Cache::remember($cacheKey, 3600, function () use ($role) {
            return TwillBlocks::getAllCropConfigs()[$role];
        });
    }

    public function hydrate(TwillModelContract $model, array $fields): TwillModelContract
    {
        $relatedItems = collect($fields['browsers'])
            ->flatMap(fn($items, $browserName) => collect($items)
                ->map(fn($item, $position) => new RelatedItem([
                    'subject_id' => $model->getKey(),
                    'subject_type' => $model->getMorphClass(),
                    'related_id' => $item['id'],
                    'related_type' => $item['endpointType'],
                    'browser_name' => $browserName,
                    'position' => $position,
                ])));

        $model->setRelation('relatedItems', $relatedItems);
        $model->loadMissing('relatedItems.related');

        return parent::hydrate($model, $fields);
    }

    public function getBlock(string $modelType, int $pageId, int $blockId)
    {
        $cacheKey = "block_{$modelType}_{$pageId}_{$blockId}";
        return Cache::remember($cacheKey, 3600, function () use ($blockId) {
            return $this->model->find($blockId);
        });
    }

    /** @param Block $model */
    public function afterSave(TwillModelContract $model, array $fields): void
    {
        $pageId = $model->subject_id ?? $model->getKey();
        $modelType = $model->getMorphClass();

        Cache::forget("block_{$modelType}_{$pageId}_{$model->getKey()}");
        Cache::forget("blocks_structure_{$modelType}_{$pageId}");
        Cache::forget("block_renderer_{$modelType}_{$pageId}_default");
        BlockRenderer::forgetBlocksStructureCache($model, 'default');


        if (!empty($fields['browsers'])) {
            $browserNames = collect($fields['browsers'])->each(function ($items, $browserName) use ($model) {
                // This will create items or delete them if they are missing
                $model->saveRelated($items, $browserName);
            })->keys();

            // Delete all the related items that were emptied
            RelatedItem::query()->whereMorphedTo('subject', $model)->whereNotIn('browser_name', $browserNames)->delete();
        } else {
            $model->clearAllRelated();
        }

        parent::afterSave($model, $fields);
    }

    /** @param Block $object */
    public function afterDelete(TwillModelContract $object): void
    {
        $modelType = $object->getMorphClass();
        $pageId = $object->subject_id ?? $object->getKey();
        $modelType = $object->getMorphClass();

        Cache::forget("block_{$modelType}_{$pageId}_{$object->getKey()}");
        BlockRenderer::forgetBlocksStructureCache($object, 'default');
        Cache::forget("block_renderer_{$modelType}_{$pageId}_default");

        $object->medias()->detach();
        $object->files()->detach();

        $object->clearAllRelated();
    }

    public function buildFromCmsArray(array $block, bool $repeater = false): array
    {
        $blockInstance = BlockConfig::getForComponent($block['type'], $repeater);

        $block['type'] = $blockInstance->name;

        $block['instance'] = $blockInstance;

        $block['content'] = empty($block['content']) ? new \stdClass() : (object)$block['content'];

        if ($block['browsers'] ?? null) {
            $browsers = Collection::make($block['browsers'])->map(function ($items) {
                return Collection::make($items)->pluck('id');
            })->toArray();

            $block['content']->browsers = $browsers;
        }

        return $block;
    }

    public function bulkDelete(array $ids): bool
    {
        foreach ($ids as $id) {
            $object = $this->model->find($id);
            if ($object) {
                $modelType = $object->getMorphClass();
                $pageId = $object->blockable_id ?? $object->id;
                $editorName = $object->editor_name ?? 'default';
                \Illuminate\Support\Facades\Cache::forget("block_renderer_{$modelType}_{$pageId}_{$editorName}");
            }
        }
        BlockRenderer::forgetBlocksStructureCache($model, $editorName ?? 'default');

        return parent::bulkDelete($ids);
    }
}
