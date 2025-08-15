<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Http\Requests\Admin\MediaRequest;
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

class MediaLibraryController extends ModuleController implements SignUploadListener
{
    protected $moduleName = 'medias';
    protected $namespace = 'A17\Twill';

    protected $defaultOrders = ['id' => 'desc'];
    protected $perPage = 40;

    protected $endpointType;
    protected $customFields = [];

    /** @var \Illuminate\Routing\ResponseFactory */
    protected $responseFactory;

    /** @var \Illuminate\Config\Repository */
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
        $this->middleware('can:edit-media-library', ['only' => [
            'signS3Upload', 'signAzureUpload', 'tags', 'store', 'singleUpdate', 'bulkUpdate',
        ]]);

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
                ->queryString('folder')
                ->apply(function (Builder $builder, ?string $value) {
                    if ($value === null) {
                        // no folder param => no extra filter
                        return $builder;
                    }
                    $value = trim($value, '/');

                    if ($value === '') {
                        // root folder = empty string (or null)
                        return $builder->where(function ($q) {
                            $q->whereNull('folder_path')->orWhere('folder_path', '');
                        });
                    }

                    return $builder->where('folder_path', $value);
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

        if ($this->request->has('folder')) {
            $requestFilters['folder'] = $this->request->get('folder');
        }

        return $requestFilters ?? [];
    }

    public function store($parentModuleId = null)
    {
        $request = $this->app->make(MediaRequest::class);
        $media = $this->endpointType === 'local'
            ? $this->storeFile($request)
            : $this->storeReference($request);

        return $this->responseFactory->json(['media' => $media->toCmsArray(), 'success' => true], 200);
    }

    /**
     * Local uploads
     *
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

        // normalize: '' for root, 'a/b' for nested
        $folderPath = trim((string) $request->input('folder', ''), '/');

        $fields = [
            'uuid'        => $uuid,
            'filename'    => $originalFilename,
            'width'       => $w,
            'height'      => $h,
            'folder_path' => $folderPath,
        ];

        if ($this->shouldReplaceMedia($id = $request->input('media_to_replace_id'))) {
            $media = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($media);

            // avoid mass-assignment restrictions during replace
            $media->replace(Arr::except($fields, ['folder_path']));

            // force-persist folder path
            if ($request->has('folder')) {
                $media->folder_path = $folderPath;
                $media->save();
            }

            return $media->fresh();
        }

        // create first (bypass mass-assignment for folder_path), then force-set it
        $media = $this->repository->create(Arr::except($fields, ['folder_path']));
        if ($request->has('folder')) {
            $media->folder_path = $folderPath;
            $media->save();
        }

        return $media;
    }

    /**
     * Remote (S3/Azure) references
     *
     * @return Media
     */
    public function storeReference($request)
    {
        // ⭐ NEW: persist folder_path from request (root is '')
        $folderPath = trim((string) $request->input('folder', ''), '/');

        $fields = [
            'uuid'        => $request->input('key') ?? $request->input('blob'),
            'filename'    => $request->input('name'),
            'width'       => $request->input('width'),
            'height'      => $request->input('height'),
            'folder_path' => $folderPath,
        ];

        if ($this->shouldReplaceMedia($id = $request->input('media_to_replace_id'))) {
            $media = $this->repository->whereId($id)->first();
            $this->repository->afterDelete($media);
            $media->update($fields);
            return $media->fresh();
        }

        return $this->repository->create($fields);
    }

    public function singleUpdate(): JsonResponse
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

    public function bulkUpdate(): JsonResponse
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

    public function signS3Upload(Request $request, SignS3Upload $signS3Upload)
    {
        return $signS3Upload->fromPolicy($request->getContent(), $this, $this->config->get('twill.media_library.disk'));
    }

    public function signAzureUpload(Request $request, SignAzureUpload $signAzureUpload)
    {
        return $signAzureUpload->getSasUrl($request, $this, $this->config->get('twill.media_library.disk'));
    }

    public function uploadIsSigned($signature, $isJsonResponse = true)
    {
        return $isJsonResponse
            ? $this->responseFactory->json($signature, 200)
            : $this->responseFactory->make($signature, 200, ['Content-Type' => 'text/plain']);
    }

    public function uploadIsNotValid(): JsonResponse
    {
        return $this->responseFactory->json(['invalid' => true], 500);
    }

    private function getExtraMetadatas(): Collection
    {
        return Collection::make($this->customFields)->mapWithKeys(function ($field) {
            $fieldInRequest = $this->request->get($field['name']);

            if (isset($field['type']) && $field['type'] === 'checkbox') {
                return [$field['name'] => $fieldInRequest ? Arr::first($fieldInRequest) : false];
            }

            return [$field['name'] => $fieldInRequest];
        });
    }

    private function shouldReplaceMedia($id): bool
    {
        return is_numeric($id) ? $this->repository->whereId($id)->exists() : false;
    }
}
