<?php
namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use A17\Twill\Models\Media;

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
        // Only media library folders
        abort_unless($folder->library === 'media', 404);

        // Do not allow deleting the virtual root
        if ($folder->parent_id === null) {
            return response()->json(['message' => 'Root folder cannot be deleted.'], 422);
        }

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

            // Build a detailed usage report and try to resolve a human title
            $usedReport = $usages->groupBy('media_id')->map(function ($rows, $mediaId) use ($mediaMeta) {
                $places = $rows->map(function ($row) {
                    $title = null;
                    try {
                        if (class_exists($row->mediable_type)) {
                            $model = $row->mediable_type::find($row->mediable_id);
                            if ($model) {
                                // best-effort title
                                $title = $model->title ?? $model->name ?? (method_exists($model, '__toString') ? (string) $model : null);
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore resolution errors
                    }

                    return [
                        'type'  => $row->mediable_type,
                        'id'    => $row->mediable_id,
                        'role'  => $row->role,
                        'title' => $title,
                    ];
                })->values();

                return [
                    'media_id' => (int) $mediaId,
                    'filename' => (string) ($mediaMeta[$mediaId] ?? ''),
                    'places'   => $places,
                ];
            })->values();

            return response()->json([
                'message' => "This folder (or its subfolders) contains {$usedCount} used media. Remove usages first.",
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
