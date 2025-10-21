<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\Block;
use A17\Twill\Services\Previews\PreviewRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Application;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use A17\Twill\Helpers\BlockRenderer;

class BlockPreviewController extends Controller
{
    public function show(
        Request $request,
        Application $app,
        ViewFactory $viewFactory,
    ) {
        $rawType = (string) $request->query('type');
        if ($rawType === '') {
            return response('Missing type', 400);
        }

        // Normalize: a17-block-foo -> foo
        $type = preg_replace('/^a17-block-/', '', $rawType) ?: $rawType;

        $previewBlock = (new PreviewRegistry())->forType($type);
        if (!$previewBlock) {
            return response('No preview source found for this block type', 404);
        }

        if ($request->has('activeLanguage')) {
            $app->setLocale($request->get('activeLanguage'));
        }
        $activeLanguage = $request->get('activeLanguage', app()->getLocale());

        $previewBlock->loadMissing([
            'medias',
            'children', 'children.medias',
            'children.children', 'children.children.medias',
        ]);

        $data = $this->toCmsArray($previewBlock);
        $data['editor_name'] ??= 'default';
        $data['activeLanguage'] = $activeLanguage;

        $mapping = config('twill.block_editor.block_views_mappings');

        if ($viewFactory->exists(config('twill.block_editor.block_single_layout'))) {
            $viewFactory->inject(
                'content',
                BlockRenderer::fromCmsArray(Arr::except($data, ['activeLanguage']))
                    ->render($mapping, [])
            );

            $result = view(config('twill.block_editor.block_single_layout'));
        } else {
            $result = view(
                'twill::errors.block_layout',
                ['view' => config('twill.block_editor.block_single_layout')]
            );
        }

        return response(
            html_entity_decode($result->render()),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    protected function toCmsArray(Block $block): array
    {
        $type = (string) $block->type;

        // Twill’s preview expects:
        // - dynamic repeaters: type stays "dynamic-repeater-*", is_repeater=true
        // - all other blocks: type is "a17-block-{$type}", is_repeater=false
        $isRepeater = Str::startsWith($type, 'dynamic-repeater-');
        $previewType = $isRepeater ? $type : ('a17-block-' . $type);

        // Content is already cast to array by the model
        $content = is_array($block->content) ? $block->content : (json_decode((string) $block->content, true) ?: []);

        $medias = [];
        $browsers = [];

        // Children must be grouped under their child_key, each as an array of blocks
        $grouped = [];
        foreach ($block->children ?? [] as $child) {
            $key = (string) ($child->child_key ?: 'children');
            $grouped[$key] ??= [];
            $grouped[$key][] = $this->toCmsArray($child);
        }

        return [
            'id'           => (int) $block->id,
            'type'         => $previewType,
            'is_repeater'  => $isRepeater,
            'editor_name'  => (string) ($block->editor_name ?? 'default'),
            'content'      => $content,
            'medias'       => $medias,
            'browsers'     => $browsers,
            'blocks'       => $grouped,
        ];
    }
}
