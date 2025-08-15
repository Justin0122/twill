<?php
namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FileFolderController extends Controller
{
    public function index(Request $request)
    {
        $explicit = LibraryFolder::query()->where('library', 'file')->get(['path'])->pluck('path')->all();

        $fromFiles = DB::table('twill_files')
            ->whereNotNull('folder_path')
            ->where('folder_path', '!=', '')
            ->distinct()
            ->pluck('folder_path')
            ->all();

        $paths = collect($explicit)->merge($fromFiles)->unique()->values()->all();

        return response()->json([
            'tree' => $this->buildTree($paths),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'parent' => 'nullable|string',
            'name' => 'required|string|max:255',
        ]);

        $parent = $this->normalize($data['parent'] ?? '');
        $name   = $this->sanitizeName($data['name']);
        $path   = trim($parent . '/' . $name, '/');

        if ($path === '') return response()->json(['message' => 'Invalid folder name'], 422);

        LibraryFolder::firstOrCreate([
            'path' => $path,
        ], [
            'library'   => 'file',
            'name'      => $name,
            'parent_id' => optional(LibraryFolder::where('path', $parent)->first())->id,
        ]);

        return response()->json(['ok' => true], 201);
    }

    // Rename folder
    public function update(Request $request, LibraryFolder $folder)
    {
        abort_unless($folder->library === 'file', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldPath = $folder->path;
        $newPath = trim(($folder->parent?->path ?? '') . '/' . $data['name'], '/');

        // Ensure uniqueness
        if (LibraryFolder::where('library', 'file')->where('path', $newPath)->where('id', '!=', $folder->id)->exists()) {
            return response()->json(['message' => 'A folder with that name already exists here.'], 422);
        }

        DB::transaction(function () use ($folder, $data, $oldPath, $newPath) {
            // 1) Update this folder
            $folder->update(['name' => $data['name'], 'path' => $newPath]);

            // 2) Cascade paths to descendants
            $like = $oldPath === '' ? '%' : $oldPath . '/%';
            $descendants = LibraryFolder::where('library', 'file')
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

    public function move(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'target' => 'nullable|string',
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ]);

        $target = $this->normalize($data['target'] ?? '');

        DB::table('twill_files')->whereIn('id', $data['mediaIds'])
            ->update(['folder_path' => $target]);

        return response()->json(['ok' => true]);
    }

    private function normalize(?string $path): string
    {
        $path = trim($path ?? '', '/');
        // reject unsafe segments
        if (Str::contains($path, ['..', "\0"])) return '';
        // collapse duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        // strip slashes and control chars
        $name = preg_replace('#[\/\0]#', '', $name);
        // optional: restrict to a safe charset
        return $name;
    }

    private function buildTree(array $paths): array
    {
        $root = ['name' => '', 'children' => []];

        foreach ($paths as $p) {
            $segments = array_values(array_filter(explode('/', $p), 'strlen'));
            $node = &$root;
            foreach ($segments as $seg) {
                if (!isset($node['children'])) $node['children'] = [];
                $idx = null;
                foreach ($node['children'] as $k => $child) {
                    if ($child['name'] === $seg) { $idx = $k; break; }
                }
                if ($idx === null) {
                    $node['children'][] = ['name' => $seg, 'children' => []];
                    $idx = array_key_last($node['children']);
                }
                $node = &$node['children'][$idx];
            }
            unset($node);
        }

        return $root;
    }
}
