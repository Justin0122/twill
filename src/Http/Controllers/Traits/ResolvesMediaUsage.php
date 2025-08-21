<?php

namespace A17\Twill\Http\Controllers\Traits;

use A17\Twill\Models\Block;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ResolvesMediaUsage
{
    protected function resolveUsageToPage(string $mediableType, int $mediableId): array
    {
        // normalized checks for Block
        $isBlockType = function ($type) {
            return in_array($type, ['blocks', Block::class, 'A17\\Twill\\Models\\Block'], true);
        };

        if ($isBlockType($mediableType)) {
            // climb from block -> (maybe parent block) -> page
            $visited = [];
            $currentId = $mediableId;

            while ($currentId && !in_array($currentId, $visited, true)) {
                $visited[] = $currentId;
                // use model if available to honor table names; fall back to DB if needed
                $block = Block::query()->find($currentId);
                if (!$block) {
                    $block = DB::table('twill_blocks')->where('id', $currentId)->first();
                    if (!$block) {
                        break;
                    }
                    $blockableType = $block->blockable_type;
                    $blockableId = (int)$block->blockable_id;
                    $parentId = $block->parent_id ?? null;
                } else {
                    $blockableType = $block->blockable_type;
                    $blockableId = (int)$block->blockable_id;
                    $parentId = $block->parent_id;
                }

                // nested block? keep climbing
                if ($isBlockType($blockableType)) {
                    $currentId = $blockableId; // parent block id
                    continue;
                }

                // reached a page-like model
                return [$blockableType, $blockableId];
            }

            // fallback: could not resolve, return the original block ref
            return [$mediableType, $mediableId];
        }

        // direct usage on a model (e.g., App\Models\Page)
        return [$mediableType, $mediableId];
    }
    protected function adminEditUrlFor(?string $modelClass, ?int $id): ?string
    {
        if (!$modelClass || !$id || !class_exists($modelClass)) {
            return null;
        }

        // Prefer the Eloquent table name for the module segment
        try {
            $model = app($modelClass); // handles IoC/constructor defaults
            if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                $module = $model->getTable(); // e.g. "partners", "news", "pages"
            } else {
                $module = Str::snake(Str::pluralStudly(class_basename($modelClass)));
            }
        } catch (\Throwable $e) {
            // Fallback if instantiation fails
            $module = Str::snake(Str::pluralStudly(class_basename($modelClass)));
        }

        // Optional: check the conventional slug table exists (partner_slugs, news_slugs, page_slugs, …)
        // Not strictly needed for the admin URL, but proves the module is a Twill module.
        $slugTable = Str::singular($module) . '_slugs';
        $hasSlugTable = Schema::hasTable($slugTable); // true for Twill modules; you can use this if you want guards

        // Prefer the named Twill route if it exists (TwillRoute::module('partners') registers admin.partners.edit)
        $routeName = "admin.$module.edit";
        if (app('router')->has($routeName)) {
            try {
                return route($routeName, $id);
            } catch (\Throwable $e) {
                // fall through to URL fallback
            }
        }

        // Fallback: conventional admin URL
        return url("/admin/{$module}/{$id}/edit");
    }
}
