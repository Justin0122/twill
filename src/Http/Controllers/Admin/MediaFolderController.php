<?php
namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use A17\Twill\Models\Media;
use A17\Twill\Models\Block;

class MediaFolderController extends Controller
{
    public function index(Request $request)
    {
        // Build tree from library_folders (media)
        $rows = LibraryFolder::where('library', 'media')
            ->orderBy('path')
            ->get(['id', 'name', 'path', 'parent_id']);

        return response()->json(['tree' => $this->buildTreeFromRows($rows)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'   => 'required|in:image,video,file,media',
            'parent' => 'nullable|string',
            'name'   => 'required|string|max:255',
        ]);

        $parentPath = trim((string) ($data['parent'] ?? ''), '/');
        $name       = trim($data['name'], '/');

        $parent = $parentPath === ''
            ? null
            : LibraryFolder::where('library', 'media')->where('path', $parentPath)->first();

        $path = trim(($parent->path ?? '') . '/' . $name, '/');

        $folder = LibraryFolder::firstOrCreate(
            ['library' => 'media', 'path' => $path],
            [
                'name'      => $name,
                'parent_id' => $parent?->id,
            ]
        );

        return response()->json(['ok' => true, 'folder' => $folder], 201);
    }

    // Rename folder
    public function update(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'media', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldPath = $folder->path;
        $newPath = trim(($folder->parent?->path ?? '') . '/' . $data['name'], '/');

        // Ensure uniqueness
        if (LibraryFolder::where('library', 'media')->where('path', $newPath)->where('id', '!=', $folder->id)->exists()) {
            return response()->json(['message' => 'A folder with that name already exists here.'], 422);
        }

        DB::transaction(function () use ($folder, $data, $oldPath, $newPath) {
            // 1) Update this folder
            $folder->update(['name' => $data['name'], 'path' => $newPath]);

            // 2) Cascade paths to descendants
            $like = $oldPath === '' ? '%' : $oldPath . '/%';
            $descendants = LibraryFolder::where('library', 'media')
                ->where('path', 'like', $like)
                ->get();

            foreach ($descendants as $child) {
                $child->update([
                    'path' => preg_replace(
                        '#^'.preg_quote($oldPath, '#').'#',
                        $newPath,
                        $child->path
                    )
                ]);
            }
        });

        return response()->json(['ok' => true, 'folder' => $folder->fresh()]);
    }

    // Move media to a folder by ID (or to root if null)
    public function move(Request $request)
    {
        $data = $request->validate([
            'type'      => 'required|string',
            'targetId'  => 'nullable|integer',
            'mediaIds'  => 'required|array|min:1',
            'mediaIds.*'=> 'integer',
        ]);

        $targetId = $data['targetId'] ?? null;
        if ($targetId !== null) {
            $exists = LibraryFolder::where('id', $targetId)->where('library', 'media')->exists();
            if (!$exists) {
                return response()->json(['message' => 'Target folder not found'], 422);
            }
        }

        DB::table('twill_medias')->whereIn('id', $data['mediaIds'])
            ->update(['folder_id' => $targetId]); // null => root

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'media', 404);

        // Collect this folder + its descendants
        $ids = collect([$folder->id])
            ->merge(
                LibraryFolder::where('library', 'media')
                    ->where('path', 'like', $folder->path . '/%')
                    ->pluck('id')
            )
            ->unique()
            ->values();

        // All media (directly) in these folders
        $mediaIds = DB::table('twill_medias')
            ->whereIn('folder_id', $ids)
            ->pluck('id');

        // Short-circuit if no media at all
        if ($mediaIds->isEmpty()) {
            LibraryFolder::whereIn('id', $ids)->delete();

            return response()->json([
                'ok' => true,
                'deleted' => ['media' => 0, 'folders' => $ids->count()],
            ]);
        }

        // Any usages?
        $usages = DB::table('twill_mediables')
            ->whereIn('media_id', $mediaIds)
            ->select('media_id', 'mediable_type', 'mediable_id', 'role')
            ->orderBy('media_id')
            ->get();

        $usedCount = $usages->count();

        if ($usedCount > 0) {
            // Map media id -> filename for a nicer report
            $mediaMeta = DB::table('twill_medias')
                ->whereIn('id', $mediaIds)
                ->pluck('filename', 'id');

            // Build a detailed usage report that resolves to the owning page/model
            $usedReport = $usages->groupBy('media_id')->map(function ($rows, $mediaId) use ($mediaMeta) {

                $places = $rows->map(function ($row) {
                    [$pageType, $pageId] = $this->resolveUsageToPage($row->mediable_type, (int)$row->mediable_id);

                    $title = null;
                    if ($pageType && class_exists($pageType)) {
                        try {
                            $model = $pageType::find($pageId);
                            if ($model) {
                                // best-effort title
                                $title = $model->title
                                    ?? $model->name
                                    ?? (method_exists($model, 'getTitle') ? $model->getTitle() : null)
                                    ?? (method_exists($model, '__toString') ? (string) $model : null);
                            }
                        } catch (\Throwable $e) {
                            // ignore resolution errors
                        }
                    }

                    return [
                        // what page it's used on:
                        'type'  => $pageType ?: $row->mediable_type,
                        'id'    => $pageId   ?: $row->mediable_id,
                        // keep the role (image field name within that page/block)
                        'role'  => $row->role,
                        'title' => $title,
                        // optional raw location for debugging:
                        'via'   => [
                            'mediable_type' => $row->mediable_type,
                            'mediable_id'   => $row->mediable_id,
                        ],
                    ];
                })
                    // same page might appear multiple times (e.g. same media twice in same page)
                    ->unique(fn($p) => ($p['type'] ?? '').'#'.($p['id'] ?? '').'|'.($p['role'] ?? ''))
                    ->values();

                return [
                    'media_id' => (int) $mediaId,
                    'filename' => (string) ($mediaMeta[$mediaId] ?? ''),
                    'places'   => $places,
                ];
            })->values();

            return response()->json([
                'message' => "This folder (or its subfolders) contains used media. Remove usages first.",
                'used'    => $usedReport,
            ], 422);
        }

        // No usages: delete all (unused) media in these folders (soft-delete ok)
        Media::whereIn('id', $mediaIds)->get()->each->delete();

        // Finally delete subfolders + the folder itself
        LibraryFolder::whereIn('id', $ids)->delete();

        return response()->json([
            'ok' => true,
            'deleted' => [
                'media'   => $mediaIds->count(),
                'folders' => $ids->count(),
            ],
        ]);
    }

    /**
     * Resolve a mediable reference to the ultimate owning "page".
     * - If it's a Block (or "blocks"), walk up the chain until we reach a non-Block blockable.
     * - Otherwise, assume the mediable_type itself is the page model.
     *
     * @return array{0:?string,1:?int} [pageTypeFQCN, pageId]
     */
    private function resolveUsageToPage(?string $mediableType, int $mediableId): array
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
                    if (!$block) break;
                    $blockableType = $block->blockable_type;
                    $blockableId   = (int)$block->blockable_id;
                    $parentId      = $block->parent_id ?? null;
                } else {
                    $blockableType = $block->blockable_type;
                    $blockableId   = (int)$block->blockable_id;
                    $parentId      = $block->parent_id;
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

    private function buildTreeFromRows($rows): array
    {
        // Index by id
        $nodes = [];
        foreach ($rows as $r) {
            $nodes[$r->id] = [
                'id'       => $r->id,
                'name'     => $r->name,
                'path'     => $r->path,
                'children' => [],
            ];
        }

        $root = ['id' => null, 'name' => '', 'path' => '', 'children' => []];

        foreach ($rows as $r) {
            if ($r->parent_id && isset($nodes[$r->parent_id])) {
                $nodes[$r->parent_id]['children'][] = &$nodes[$r->id];
            } else {
                $root['children'][] = &$nodes[$r->id];
            }
        }

        return $root;
    }
}
