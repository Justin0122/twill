<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Http\Controllers\Traits\ResolvesMediaUsage;
use A17\Twill\Http\Requests\Admin\MediaRequest;
use A17\Twill\Models\LibraryFolder;
use A17\Twill\Models\Media;
use A17\Twill\Services\Listings\Filters\BasicFilter;
use A17\Twill\Services\Listings\Filters\TableFilters;
use A17\Twill\Services\Uploader\SignAzureUpload;
use A17\Twill\Services\Uploader\SignS3Upload;
use A17\Twill\Services\Uploader\SignUploadListener;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MediaLibraryController extends ModuleController implements SignUploadListener
{
    use ResolvesMediaUsage;

    /**
     * @var string
     */
    protected $moduleName = 'medias';

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
     * @var array
     */
    protected $customFields = [];

    /**
     * @var Illuminate\Routing\ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var Illuminate\Config\Repository
     */
    protected $config;

    public function __construct(
        Application $app,
        Config $config,
        Request $request,
        ResponseFactory $responseFactory
    ) {
        parent::__construct($app, $request);
        $this->responseFactory = $responseFactory;
        $this->config = $config;

        $this->middleware('can:access-media-library', ['only' => ['index']]);
        $this->middleware(
            'can:edit-media-library',
            ['only' => ['signS3Upload', 'signAzureUpload', 'tags', 'store', 'singleUpdate', 'bulkUpdate']]
        );
        $this->endpointType = $this->config->get('twill.media_library.endpoint_type');
        $this->customFields = $this->config->get('twill.media_library.extra_metadatas_fields');
    }

    public function setUpController(): void
    {
        $this->setSearchColumns(['alt_text', 'filename', 'caption']);
    }

    public function filters(): TableFilters
    {
        return TableFilters::make([
            BasicFilter::make()
                ->queryString('tag')
                ->apply(function (Builder $builder, ?int $value) {
                    if ($value) {
                        $builder->whereHas('tags', function (Builder $builder) use ($value) {
                            $builder->where('tag_id', $value);
                        });
                    }

                    return $builder;
                }),

            BasicFilter::make()
                ->queryString('unused')
                ->apply(function (Builder $builder, ?bool $value) {
                    return $value ? $builder->unused() : $builder;
                }),

            BasicFilter::make()
                ->queryString('folder_id')
                ->apply(function (Builder $builder, $value) {
                    // When folder_id is not provided or is an empty string, do not filter by folder.
                    if ($value === null || $value === '') {
                        return $builder;
                    }

                    // virtual "trash" folder -> list only soft-deleted items
                    if ($value === 'trash') {
                        return $builder->onlyTrashed();
                    }

                    // Explicit "null" string means filter for items without a folder.
                    if ($value === 'null') {
                        return $builder->whereNull('folder_id');
                    }

                    // Numeric value means filter by that specific folder.
                    if (is_numeric($value)) {
                        return $builder->where('folder_id', (int) $value);
                    }

                    // Fallback: no folder filtering.
                    return $builder;
                }),
        ]);
    }

    public function index(?int $parentModuleId = null): array
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
            'items'   => $items->map(fn ($item) => $item->toCmsArray())->toArray(),
            'maxPage' => $items->lastPage(),
            'total'   => $items->total(),
            'tags'    => $this->repository->getTagsList(),
        ];
    }

    /**
     * @return array
     */
    protected function getRequestFilters(): array
    {
        if ($this->request->has('search')) {
            $requestFilters['search'] = $this->request->get('search');
        }

        if ($this->request->has('tag')) {
            $requestFilters['tag'] = $this->request->get('tag');
        }

        if ($this->request->has('unused') && (int) $this->request->unused === 1) {
            $requestFilters['unused'] = $this->request->get('unused');
        }

        if ($this->request->has('folder_id')) {
            $requestFilters['folder_id'] = $this->request->get('folder_id');
        }

        return $requestFilters ?? [];
    }

    /**
     * @param int|null $parentModuleId
     * @return
     */
    public function store($parentModuleId = null)
    {
        $request = $this->app->make(MediaRequest::class);
        $media = $this->endpointType === 'local'
            ? $this->storeFile($request)
            : $this->storeReference($request);

        return $this->responseFactory->json(['media' => $media->toCmsArray(), 'success' => true], 200);
    }

    /**
     * @param Request $request
     * @return Media
     */
    public function storeFile($request)
    {
        $originalFilename = $request->input('qqfilename');
        $filename = sanitizeFilename($originalFilename);
        $fileDirectory = $request->input('unique_folder_name');
        $uuid = $request->input('unique_folder_name') . '/' . $filename;

        if ($this->config->get('twill.media_library.prefix_uuid_with_local_path', false)) {
            $prefix = trim($this->config->get('twill.media_library.local_path'), '/ ') . '/';
            $fileDirectory = $prefix . $fileDirectory;
            $uuid = $prefix . $uuid;
        }

        $disk = $this->config->get('twill.media_library.disk');
        $uploadedFile = $request->file('qqfile');

        if ($request->input('width') && $request->input('height')) {
            $w = $request->input('width');
            $h = $request->input('height');
        } else {
            [$w, $h] = getimagesize($uploadedFile->path());
        }

        $uploadedFile->storeAs($fileDirectory, $filename, $disk);

        $folderId = $request->input('folder_id');
        $folderPath = trim((string) $request->input('folder', ''), '/');

        if ($folderId !== null && ! LibraryFolder::whereKey($folderId)->exists()) {
            $folderId = null;
        }

        $fields = [
            'uuid'      => $uuid,
            'filename'  => $originalFilename,
            'width'     => $w,
            'height'    => $h,
            'folder_id' => $folderId,
            'folder_path' => $folderPath,
        ];

        if ($this->shouldReplaceMedia($id = $request->input('media_to_replace_id'))) {
            $media = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($media);
            $media->replace($fields);

            return $media->fresh();
        }

        return $this->repository->create($fields);
    }

    /**
     * @param Request $request
     * @return Media
     */
    public function storeReference($request)
    {
        $folderId = $request->input('folder_id');
        if ($folderId !== null && ! LibraryFolder::whereKey($folderId)->exists()) {
            $folderId = null;
        }

        $fields = [
            'uuid'      => $request->input('key') ?? $request->input('blob'),
            'filename'  => $request->input('name'),
            'width'     => $request->input('width'),
            'height'    => $request->input('height'),
            'folder_id' => $folderId,
        ];

        if ($this->shouldReplaceMedia($id = $request->input('media_to_replace_id'))) {
            $media = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($media);
            $media->update($fields);

            return $media->fresh();
        }

        return $this->repository->create($fields);
    }

    /**
     * @return JsonResponse
     */
    public function singleUpdate()
    {
        $id = $this->request->input('id');

        $this->repository->update(
            $id,
            array_merge([
                'alt_text' => $this->request->get('alt_text', null),
                'caption'  => $this->request->get('caption', null),
                'tags'     => $this->request->get('tags', null),
            ], $this->getExtraMetadatas()->toArray())
        );

        $items = $this->getIndexItems(['id' => $id]);

        return $this->responseFactory->json([
            'item' => $items->first()->toCmsArray(),
            'tags' => $this->repository->getTagsList(),
        ], 200);
    }

    /**
     * @return JsonResponse
     */
    public function bulkUpdate()
    {
        $ids = explode(',', $this->request->input('ids'));

        $metadatasFromRequest = $this->getExtraMetadatas()->reject(fn ($meta) => is_null($meta))->toArray();

        $extraMetadatas = array_diff_key(
            $metadatasFromRequest,
            array_flip((array) $this->request->get('fieldsRemovedFromBulkEditing', []))
        );

        if (in_array('tags', $this->request->get('fieldsRemovedFromBulkEditing', []))) {
            $this->repository->addIgnoreFieldsBeforeSave('bulk_tags');
        } else {
            $previousCommonTags = $this->repository->getTags(null, $ids);
            $newTags = array_filter(explode(',', $this->request->input('tags')));
        }

        foreach ($ids as $id) {
            $this->repository->update(
                $id,
                [
                    'bulk_tags'            => $newTags ?? [],
                    'previous_common_tags' => $previousCommonTags ?? [],
                ] + $extraMetadatas
            );
        }

        $items = $this->getIndexItems(['id' => $ids]);

        return $this->responseFactory->json([
            'items' => $items->map(fn ($item) => $item->toCmsArray())->toArray(),
            'tags'  => $this->repository->getTagsList(),
        ], 200);
    }

    public function bulkDelete()
    {
        $idsParam = (string) $this->request->get('ids', '');
        $ids = collect(explode(',', $idsParam))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'message' => 'No media ids provided.',
            ], 422);
        }

        // Check usages
        $usages = DB::table('twill_mediables')
            ->whereIn('media_id', $ids)
            ->select('media_id', 'mediable_type', 'mediable_id', 'role')
            ->orderBy('media_id')
            ->get();

        if ($usages->count() > 0) {
            $mediaMeta = DB::table('twill_medias')
                ->whereIn('id', $ids)
                ->pluck('filename', 'id');

            $usedReport = $usages->groupBy('media_id')->map(function ($rows, $mediaId) use ($mediaMeta) {
                $places = $rows->map(function ($row) {
                    [$pageType, $pageId] = $this->resolveUsageToPage($row->mediable_type, (int) $row->mediable_id);

                    $title = null;
                    if ($pageType && class_exists($pageType)) {
                        try {
                            $model = $pageType::find($pageId);
                            if ($model) {
                                $title = $model->title
                                    ?? $model->name
                                    ?? (method_exists($model, 'getTitle') ? $model->getTitle() : null)
                                    ?? (method_exists($model, '__toString') ? (string) $model : null);
                            }
                        } catch (\Throwable $e) { /* ignore */
                        }
                    }

                    return [
                        'type' => $pageType ?: $row->mediable_type,
                        'id' => $pageId ?: $row->mediable_id,
                        'role' => $row->role,
                        'title' => $title,
                        'admin_url' => $this->adminEditUrlFor($pageType, $pageId),
                        'via' => [
                            'mediable_type' => $row->mediable_type,
                            'mediable_id' => $row->mediable_id,
                        ],
                    ];
                })
                    ->unique(fn ($p) => ($p['type'] ?? '') . '#' . ($p['id'] ?? '') . '|' . ($p['role'] ?? ''))
                    ->values();

                return [
                    'media_id' => (int) $mediaId,
                    'filename' => (string) ($mediaMeta[$mediaId] ?? ''),
                    'places' => $places,
                ];
            })->values();

            return response()->json([
                'message' => 'Some selected media are used. Remove usages first.',
                'used' => $usedReport,
            ], 422);
        }

        // No usages: proceed with deletion through the repository
        if ($this->repository->bulkDelete($ids->all())) {
            $this->fireEvent();

            return $this->respondWithSuccess(
                twillTrans('twill::lang.listing.bulk-delete.success', ['modelTitle' => $this->modelTitle])
            );
        }

        return $this->respondWithError(
            twillTrans('twill::lang.listing.bulk-delete.error', ['modelTitle' => $this->modelTitle])
        );
    }

    /**
     * @return mixed
     */
    public function signS3Upload(Request $request, SignS3Upload $signS3Upload)
    {
        return $signS3Upload->fromPolicy($request->getContent(), $this, $this->config->get('twill.media_library.disk'));
    }

    /**
     * @return mixed
     */
    public function signAzureUpload(Request $request, SignAzureUpload $signAzureUpload)
    {
        return $signAzureUpload->getSasUrl($request, $this, $this->config->get('twill.media_library.disk'));
    }

    /**
     * @param $signature
     * @param bool $isJsonResponse
     * @return mixed
     */
    public function uploadIsSigned($signature, $isJsonResponse = true)
    {
        return $isJsonResponse
            ? $this->responseFactory->json($signature, 200)
            : $this->responseFactory->make($signature, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * @return JsonResponse
     */
    public function uploadIsNotValid()
    {
        return $this->responseFactory->json(['invalid' => true], 500);
    }

    /**
     * @return Collection
     */
    private function getExtraMetadatas()
    {
        return Collection::make($this->customFields)->mapWithKeys(function ($field) {
            $fieldInRequest = $this->request->get($field['name']);

            if (isset($field['type']) && $field['type'] === 'checkbox') {
                return [$field['name'] => $fieldInRequest ? Arr::first($fieldInRequest) : false];
            }

            return [$field['name'] => $fieldInRequest];
        });
    }

    /**
     * @return bool
     */
    private function shouldReplaceMedia($id)
    {
        return is_numeric($id) ? $this->repository->whereId($id)->exists() : false;
    }
}
