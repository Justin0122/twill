<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Http\Requests\Admin\FileRequest;
use A17\Twill\Services\Listings\Filters\BasicFilter;
use A17\Twill\Services\Listings\Filters\TableFilters;
use A17\Twill\Services\Uploader\SignAzureUpload;
use A17\Twill\Services\Uploader\SignS3Upload;
use A17\Twill\Services\Uploader\SignUploadListener;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\UrlGenerator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FileLibraryController extends ModuleController implements SignUploadListener
{
    /**
     * @var string
     */
    protected $moduleName = 'files';

    /**
     * @var string
     */
    protected $namespace = 'A17\Twill';

    /**
     * @var array
     */
    protected $defaultOrders = [
        'id' => 'desc',
    ];

    /**
     * @var int
     */
    protected $perPage = 40;

    /**
     * @var string
     */
    protected $endpointType;

    /**
     * @var Illuminate\Routing\UrlGenerator
     */
    protected $urlGenerator;

    /**
     * @var Illuminate\Routing\ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var Illuminate\Config\Repository
     */
    protected $config;

    // ⭐ NEW: column used to store folder path
    protected string $folderColumn = 'folder_path';

    public function __construct(
        Application $app,
        Request $request,
        UrlGenerator $urlGenerator,
        ResponseFactory $responseFactory,
        Config $config
    ) {
        parent::__construct($app, $request);
        $this->urlGenerator = $urlGenerator;
        $this->responseFactory = $responseFactory;
        $this->config = $config;

        $this->middleware('can:access-media-library', ['only' => ['index', 'foldersIndex']]);
        $this->middleware(
            'can:edit-media-library',
            ['only' => ['signS3Upload', 'signAzureUpload', 'tags', 'store', 'singleUpdate', 'bulkUpdate', 'foldersStore', 'foldersMove']]
        );
        $this->endpointType = $this->config->get('twill.file_library.endpoint_type');
    }

    public function setUpController(): void
    {
        $this->setSearchColumns(['filename']);
    }

    public function filters(): TableFilters
    {
        return TableFilters::make([
            BasicFilter::make()->queryString('tag')->apply(function (Builder $builder, ?int $value) {
                if ($value) {
                    $builder->whereHas('tags', function (Builder $builder) use ($value) {
                        $builder->where('tag_id', $value);
                    });
                }
                return $builder;
            }),
            BasicFilter::make()->queryString('unused')->apply(function (Builder $builder, ?bool $value) {
                if ($value) {
                    return $builder->unused();
                }
                return $builder;
            }),
            // ⭐ NEW: folder filter
            BasicFilter::make()->queryString('folder')->apply(function (Builder $builder, ?string $value) {
                $col = $this->folderColumn;
                if (!Schema::hasColumn('twill_files', $col)) {
                    // If the column does not exist, ignore filter gracefully
                    return $builder;
                }
                $folder = trim((string)$value, '/');
                if ($folder === '') {
                    // root: null or empty
                    return $builder->where(function ($q) use ($col) {
                        $q->whereNull($col)->orWhere($col, '');
                    });
                }
                return $builder->where($col, $folder);
            }),
        ]);
    }

    public function index(?int $parentModuleId = null): mixed
    {
        if ($this->request->has('except')) {
            $prependScope['exceptIds'] = $this->request->get('except');
        }

        return $this->getIndexData($prependScope ?? []);
    }

    protected function getIndexData(array $prependScope = []): array
    {
        $items = $this->getIndexItems($prependScope);

        return [
            'items' => $items->map(function ($item) {
                return $this->buildFile($item);
            })->toArray(),
            'maxPage' => $items->lastPage(),
            'total' => $items->total(),
            'tags' => $this->repository->getTagsList(),
        ];
    }

    /**
     * @param \A17\Twill\Models\File $item
     * @return array
     */
    private function buildFile($item)
    {
        return $item->toCmsArray() + [
                'tags' => $item->tags->map(function ($tag) {
                    return $tag->name;
                }),
                'deleteUrl' => $item->canDeleteSafely() ? moduleRoute(
                    $this->moduleName,
                    $this->routePrefix,
                    'destroy',
                    $item->id
                ) : null,
                'updateUrl' => $this->urlGenerator->route(config('twill.admin_route_name_prefix') . 'file-library.files.single-update'),
                'updateBulkUrl' => $this->urlGenerator->route(config('twill.admin_route_name_prefix') . 'file-library.files.bulk-update'),
                'deleteBulkUrl' => $this->urlGenerator->route(config('twill.admin_route_name_prefix') . 'file-library.files.bulk-delete'),
            ];
    }

    protected function getRequestFilters(): array
    {
        if ($this->request->has('search')) {
            $requestFilters['search'] = $this->request->get('search');
        }

        if ($this->request->has('tag')) {
            $requestFilters['tag'] = $this->request->get('tag');
        }

        if ($this->request->has('unused') && (int)$this->request->unused === 1) {
            $requestFilters['unused'] = $this->request->get('unused');
        }

        // (optional) expose folder in request filters list for consistency
        if ($this->request->has('folder')) {
            $requestFilters['folder'] = $this->request->get('folder');
        }

        return $requestFilters ?? [];
    }

    /**
     * @param int|null $parentModuleId
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function store($parentModuleId = null)
    {
        $request = $this->app->make(FileRequest::class);

        if ($this->endpointType === 'local') {
            $file = $this->storeFile($request);
        } else {
            $file = $this->storeReference($request);
        }

        return $this->responseFactory->json(['media' => $this->buildFile($file), 'success' => true], 200);
    }

    /**
     * @param Request $request
     * @return \A17\Twill\Models\File
     */
    public function storeFile($request)
    {
        $filename = $request->input('qqfilename');

        $cleanFilename = preg_replace("/\s+/i", "-", $filename);

        $fileDirectory = $request->input('unique_folder_name');

        $uuid = $request->input('unique_folder_name') . '/' . $cleanFilename;

        if ($this->config->get('twill.file_library.prefix_uuid_with_local_path', false)) {
            $prefix = trim($this->config->get('twill.file_library.local_path'), '/ ') . '/';
            $fileDirectory = $prefix . $fileDirectory;
            $uuid = $prefix . $uuid;
        }

        $disk = $this->config->get('twill.file_library.disk');

        $request->file('qqfile')->storeAs($fileDirectory, $cleanFilename, $disk);

        $fields = [
            'uuid' => $uuid,
            'filename' => $cleanFilename,
            'size' => $request->input('qqtotalfilesize'),
        ];

        // ⭐ NEW: persist folder if column exists
        $this->maybeAttachFolder($fields, $request);

        if ($this->shouldReplaceFile($id = $request->input('media_to_replace_id'))) {
            $file = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($file);
            $file->update($fields);
            return $file->fresh();
        }

        return $this->repository->create($fields);
    }

    /**
     * @param Request $request
     * @return \A17\Twill\Models\File
     */
    public function storeReference($request)
    {
        $fields = [
            'uuid' => $request->input('key') ?? $request->input('blob'),
            'filename' => $request->input('name'),
        ];

        // ⭐ NEW: persist folder if column exists
        $this->maybeAttachFolder($fields, $request);

        if ($this->shouldReplaceFile($id = $request->input('media_to_replace_id'))) {
            $file = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($file);
            $file->update($fields);
            return $file->fresh();
        }

        return $this->repository->create($fields);
    }

    /**
     * @return JsonResponse
     */
    public function singleUpdate()
    {
        $this->repository->update(
            $this->request->input('id'),
            $this->request->only('tags')
        );

        return $this->responseFactory->json([], 200);
    }

    /**
     * @return JsonResponse
     */
    public function bulkUpdate()
    {
        $ids = explode(',', $this->request->input('ids'));

        $previousCommonTags = $this->repository->getTags(null, $ids);
        $newTags = array_filter(explode(',', $this->request->input('tags')));

        foreach ($ids as $id) {
            $this->repository->update($id, ['bulk_tags' => $newTags, 'previous_common_tags' => $previousCommonTags]);
        }

        $items = $this->getIndexItems(['id' => $ids]);

        return $this->responseFactory->json([
            'items' => $items->map(function ($item) {
                return $this->buildFile($item);
            })->toArray(),
            'tags' => $this->repository->getTagsList(),
        ], 200);
    }

    public function signS3Upload(Request $request, SignS3Upload $signS3Upload)
    {
        return $signS3Upload->fromPolicy($request->getContent(), $this, $this->config->get('twill.file_library.disk'));
    }

    public function signAzureUpload(Request $request, SignAzureUpload $signAzureUpload)
    {
        return $signAzureUpload->getSasUrl($request, $this, $this->config->get('twill.file_library.disk'));
    }

    public function uploadIsSigned($signature, $isJsonResponse = true)
    {
        return $isJsonResponse
            ? $this->responseFactory->json($signature, 200)
            : $this->responseFactory->make($signature, 200, ['Content-Type' => 'text/plain']);
    }

    public function uploadIsNotValid()
    {
        return $this->responseFactory->json(["invalid" => true], 500);
    }

    private function shouldReplaceFile($id)
    {
        return is_numeric($id) ? $this->repository->whereId($id)->exists() : false;
    }

    public function foldersIndex(Request $request): JsonResponse
    {
        $paths = [];

        // include explicit folders if table exists
        if (Schema::hasTable('library_folders')) {
            $explicit = DB::table('library_folders')
                ->where('library', 'file')
                ->pluck('path')
                ->all();
            $paths = array_merge($paths, $explicit);
        }

        // include distinct paths from files if column exists
        if (Schema::hasColumn('twill_files', $this->folderColumn)) {
            $fromFiles = DB::table('twill_files')
                ->whereNotNull($this->folderColumn)
                ->where($this->folderColumn, '!=', '')
                ->distinct()
                ->pluck($this->folderColumn)
                ->all();
            $paths = array_merge($paths, $fromFiles);
        }

        $paths = array_values(array_unique($paths));

        return $this->responseFactory->json([
            'tree' => $this->buildTree($paths),
        ], 200);
    }

    public function foldersStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'parent' => 'nullable|string',
            'name' => 'required|string|max:255',
        ]);

        if (!Schema::hasTable('library_folders')) {
            return $this->responseFactory->json([
                'message' => 'Folders table missing. Create `library_folders` or rely on implicit folders from file paths.',
            ], 422);
        }

        $parent = $this->normalizePath($data['parent'] ?? '');
        $name = $this->sanitizeName($data['name']);
        $path = trim($parent . '/' . $name, '/');
        if ($path === '') {
            return $this->responseFactory->json(['message' => 'Invalid folder name'], 422);
        }

        $parentId = null;
        if ($parent !== '') {
            $parentId = DB::table('library_folders')->where('path', $parent)->value('id');
        }

        DB::table('library_folders')->updateOrInsert(
            ['path' => $path],
            ['library' => 'file', 'name' => $name, 'parent_id' => $parentId, 'updated_at' => now(), 'created_at' => now()]
        );

        return $this->responseFactory->json(['ok' => true], 201);
    }

    public function foldersMove(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:file',
            'target' => 'nullable|string',
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ]);

        if (!Schema::hasColumn('twill_files', $this->folderColumn)) {
            return $this->responseFactory->json([
                'message' => 'Column `twill_files.' . $this->folderColumn . '` missing. Add it to support folders.',
            ], 422);
        }

        $target = $this->normalizePath($data['target'] ?? '');

        DB::table('twill_files')->whereIn('id', $data['mediaIds'])
            ->update([$this->folderColumn => $target]);

        return $this->responseFactory->json(['ok' => true], 200);
    }

    protected function maybeAttachFolder(array &$fields, Request $request): void
    {
        if (!Schema::hasColumn('twill_files', $this->folderColumn)) {
            return;
        }
        $folder = $this->normalizePath((string)$request->input('folder', ''));
        $fields[$this->folderColumn] = $folder;
    }

    protected function normalizePath(?string $path): string
    {
        $path = trim((string)$path, '/');
        // forbid directory traversal / NUL
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return trim($path, '/'); // will become '' if invalid
        }
        // collapse duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }

    protected function sanitizeName(string $name): string
    {
        $name = trim($name);
        // remove slashes/control chars
        $name = preg_replace('#[\/\0]#', '', $name);
        return $name;
    }

    protected function buildTree(array $paths): array
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
