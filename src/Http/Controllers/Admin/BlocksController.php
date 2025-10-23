<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Helpers\BlockRenderer;
use A17\Twill\Models\Block;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\Factory as ViewFactory;

class BlocksController extends Controller
{
    /**
     * Render an HTML preview of a single block.
     * This is used by the full screen content editor.
     */
    public function preview(
        Application $app,
        ViewFactory $viewFactory,
        Request $request,
    ): string {
        if ($request->has('activeLanguage')) {
            $app->setLocale($request->get('activeLanguage'));
        }

        $data = $request->except('activeLanguage');

        $mapping = config('twill.block_editor.block_views_mappings');

        if ($viewFactory->exists(config('twill.block_editor.block_single_layout'))) {
            $viewFactory->inject(
                'content',
                BlockRenderer::fromCmsArray($data)->render($mapping, [])
            );
            $result = view(config('twill.block_editor.block_single_layout'));
        } else {
            $result = view(
                'twill::errors.block_layout',
                ['view' => config('twill.block_editor.block_single_layout')]
            );
        }

        return html_entity_decode($result->render());
    }

    /**
     * Create a new block (and children) from full clipboard JSON payload.
     * Expects:
     *  - module      (e.g. "pages")
     *  - item_id     (numeric)
     *  - editor_name (e.g. "default")
     *  - payload     (the full block json)
     */
    public function paste(Request $request)
    {
        $request->validate([
            'module'      => 'required|string',
            'item_id'     => 'required|integer',
            'editor_name' => 'required|string',
            'payload'     => 'required|array',
        ]);

        $module     = $request->string('module')->toString();
        $itemId     = $request->integer('item_id');
        $editorName = $request->string('editor_name')->toString();
        $payload    = $request->input('payload');

        // Resolve the blockable class from module (e.g. "pages" => App\Models\Page)
        $blockableClass = $this->resolveBlockableClass($module);
        if (!class_exists($blockableClass)) {
            return response("Unknown module '{$module}' ({$blockableClass} not found)", 422);
        }

        // Optional: ensure the item exists
        $blockable = $blockableClass::query()->find($itemId);
        if (!$blockable) {
            return response("{$blockableClass} id {$itemId} not found", 404);
        }

        $createdRoot = DB::transaction(function () use ($blockableClass, $itemId, $editorName, $payload) {
            // Compute next position at root for this editor_name
            $lastPos = Block::query()
                ->where('blockable_type', $blockableClass)
                ->where('blockable_id', $itemId)
                ->where('editor_name', $editorName)
                ->whereNull('parent_id')
                ->max('position');

            $nextPos = is_null($lastPos) ? 0 : ($lastPos + 1);

            // Persist the root block (and its children recursively)
            return $this->persistBlockPayload(
                payload: $payload,
                blockableType: $blockableClass,
                blockableId: $itemId,
                editorName: $editorName,
                position: $nextPos,
                parentId: null,
                childKey: null
            );
        });

        return response()->json([
            'ok'       => true,
            'id'       => $createdRoot->id,
            'position' => $createdRoot->position,
            'type'     => $createdRoot->type,
        ]);
    }

    /**
     * Create a Block record from a clipboard payload.
     * Recurses into nested "blocks" if provided.
     */
    protected function persistBlockPayload(
        array $payload,
        string $blockableType,
        int $blockableId,
        string $editorName,
        int $position,
        ?int $parentId = null,
        ?string $childKey = null
    ): Block {
        // Normalize incoming JSON
        // - Strip "a17-block-" prefix from type if present
        $incomingType = (string) Arr::get($payload, 'type', '');
        $type = preg_replace('/^a17-block-/', '', $incomingType) ?: $incomingType;

        // Some payloads contain a display-only "editor_name" / "name" field.
        // We trust the request's editorName instead.
        $content  = Arr::get($payload, 'content', []);
        $medias   = Arr::get($payload, 'medias', []);
        $browsers = Arr::get($payload, 'browsers', []);
        $isRepeater = (bool) Arr::get($payload, 'is_repeater', false);

        $block = Block::query()->create([
            'blockable_type' => $blockableType,
            'blockable_id'   => $blockableId,
            'type'           => $type,
            'position'       => $position,
            'content'        => $content,
            'medias'         => $medias,
            'browsers'       => $browsers,
            'editor_name'    => $editorName,
            'parent_id'      => $parentId,
            'child_key'      => $childKey,
            'is_repeater'    => $isRepeater,
        ]);

        // Children (if any): Twill’s serialized shape is usually
        // { "blocks": { "<ChildGroupKey>": [ {childBlock}, ... ], ... } }
        $children = Arr::get($payload, 'blocks', []);
        if (is_array($children)) {
            foreach ($children as $key => $list) {
                if (!is_array($list)) continue;
                $pos = 0;
                foreach ($list as $childPayload) {
                    $this->persistBlockPayload(
                        payload: $childPayload,
                        blockableType: $blockableType,
                        blockableId: $blockableId,
                        editorName: $editorName,
                        position: $pos++,
                        parentId: $block->id,
                        childKey: is_string($key) ? $key : null
                    );
                }
            }
        }

        return $block;
    }

    /**
     * Map the "module" to a blockable model class.
     * - pages  -> App\Models\Page
     * - news   -> App\Models\News
     * - blog_posts -> App\Models\BlogPost
     * Adjust namespace if your app uses a different path.
     */
    protected function resolveBlockableClass(string $module): string
    {
        // If you keep a custom map, prefer that:
        // return config("twill.modules_map.$module") ?? ...

        // Sensible default: singular Studly under App\Models\
        $studly = Str::studly(Str::singular($module));
        return "App\\Models\\{$studly}";
    }
}
