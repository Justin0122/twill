<?php

namespace App\Repositories;

use A17\Twill\Models\Block;
use A17\Twill\Repositories\Behaviors\HandleBlocks;
use A17\Twill\Repositories\Behaviors\HandleTranslations;
use A17\Twill\Repositories\Behaviors\HandleSlugs;
use A17\Twill\Repositories\Behaviors\HandleMedias;
use A17\Twill\Repositories\Behaviors\HandleRevisions;
use A17\Twill\Repositories\ModuleRepository;
use App\Models\Page;

class PageRepository extends ModuleRepository
{
    use HandleBlocks, HandleTranslations, HandleSlugs, HandleMedias, HandleRevisions;

    public function __construct(Page $model)
    {
        $this->model = $model;
    }

    public function afterSave($object, $request): void
    {
        $layouts = $request->input('blocks_layout', []); // array: editorName => json/string

        foreach ($layouts as $editorName => $layout) {
            if (is_string($layout)) {
                $layout = json_decode($layout, true) ?: [];
            }
            if (! is_array($layout)) {
                continue;
            }

            $byId = collect($layout)->keyBy('id');

            Block::where('blockable_type', $object->getMorphClass())
                ->where('blockable_id', $object->id)
                ->where('editor_name', $editorName)
                ->get()
                ->each(function (Block $block) use ($byId) {
                    if (! $byId->has((array) $block->id)) {
                        return;
                    }
                    $content = $block->content ?? [];
                    $content['grid'] = $byId[$block->id]['grid'] ?? null; // ['x','y','w','h']
                    $block->content = $content;
                    $block->save();
                });
        }
    }
}
