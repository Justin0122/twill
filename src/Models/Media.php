<?php

namespace A17\Twill\Models;

use A17\Twill\Models\Behaviors\HasSlug;
use A17\Twill\Services\MediaLibrary\ImageService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Media extends Model
{
    public $timestamps = true;
    protected $appends = ['owners'];
    protected $fillable = [
        'uuid',
        'filename',
        'alt_text',
        'caption',
        'width',
        'height',
        'path',
        'folder_id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->fillable(array_merge($this->fillable, Collection::make(config('twill.media_library.extra_metadatas_fields'))->map(function ($field) {
            return $field['name'];
        })->toArray()));

        Collection::make(config('twill.media_library.translatable_metadatas_fields'))->each(function ($field) {
            $this->casts[$field] = 'json';
        });

        parent::__construct($attributes);
    }

    public function scopeUnused($query)
    {
        $usedIds = DB::table(config('twill.mediables_table'))->pluck('media_id');

        return $query->whereNotIn('id', $usedIds->toArray())->get();
    }

    public function getDimensionsAttribute()
    {
        return $this->width . 'x' . $this->height;
    }

    public function altTextFrom($filename)
    {
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        if (Str::endsWith($filename, '@2x')) {
            $filename = substr($filename, 0, -3);
        }

        return Str::ucfirst(preg_replace('/[-_]/', ' ', $filename));
    }

    public function canDeleteSafely(): bool
    {
        return ! $this->isReferenced();
    }

    public function isReferenced(): bool
    {
        return DB::table(config('twill.mediables_table', 'twill_mediables'))->where('media_id', $this->id)->count() > 0;
    }

    public function getOwnersAttribute(): array
    {
        return $this->getOwnerDetails() ?: [];
    }

    public function toCmsArray()
    {
        // Compute once and reuse
        $owners = $this->getOwnersAttribute();
        $uploadedDate = [];
        if (config('twill.file_library.show_uploaded_date')) {
            $uploadedDate = [
                'uploadedDate' => $this->created_at->format(config("twill.file_library.format_uploaded_date", "d/m/Y H:i"))
            ];
        }

        return [
                'id'       => $this->id,
                'name'     => $this->filename,
                'thumbnail'=> ImageService::getCmsUrl($this->uuid, ['h' => '256']),
                'original' => ImageService::getRawUrl($this->uuid),
                'medium'   => ImageService::getUrl($this->uuid, ['h' => '430']),
                'width'    => $this->width,
                'height'   => $this->height,
                'owners'   => $owners,
                'tags'     => $this->tags
                    ? $this->tags->pluck('name')->values()->all()
                    : [],

                'deleteUrl'     => $this->canDeleteSafely()
                    ? moduleRoute('medias', 'media-library', 'destroy', $this->id)
                    : null,
                'updateUrl'     => route(config('twill.admin_route_name_prefix') . 'media-library.medias.single-update'),
                'updateBulkUrl' => route(config('twill.admin_route_name_prefix') . 'media-library.medias.bulk-update'),
                'deleteBulkUrl' => route(config('twill.admin_route_name_prefix') . 'media-library.medias.bulk-delete'),

                'metadatas' => [
                    'default' => [
                            'caption' => $this->caption,
                            'altText' => $this->alt_text,
                            'video'   => null,
                        ] + Collection::make(config('twill.media_library.extra_metadatas_fields'))
                            ->mapWithKeys(fn ($field) => [$field['name'] => $this->{$field['name']}])
                            ->toArray(),
                    'custom' => [
                        'caption' => null,
                        'altText' => null,
                        'video'   => null,
                        'owners'  => [],
                    ],
                ],
            ] + $uploadedDate;
    }

    public function getMetadata($name, $fallback = null)
    {
        $metadatas = (object) json_decode($this->pivot->metadatas);
        $language = app()->getLocale();

        if ($metadatas->$name->$language ?? false) {
            return $metadatas->$name->$language;
        }

        $fallbackLocale = config('translatable.fallback_locale');

        if (in_array($name, config('twill.media_library.translatable_metadatas_fields', [])) && config('translatable.use_property_fallback', false) && ($metadatas->$name->$fallbackLocale ?? false)) {
            return $metadatas->$name->$fallbackLocale;
        }

        $fallbackValue = $fallback ? $this->$fallback : $this->$name;

        $fallback = $fallback ?? $name;

        if (in_array($fallback, config('twill.media_library.translatable_metadatas_fields', []))) {
            $fallbackValue = $fallbackValue[$language] ?? '';

            if ($fallbackValue === '' && config('translatable.use_property_fallback', false)) {
                $fallbackValue = $this->$fallback[config('translatable.fallback_locale')] ?? '';
            }
        }

        if (is_object($metadatas->$name ?? null)) {
            return $fallbackValue ?? '';
        }

        return $metadatas->$name ?? $fallbackValue ?? '';
    }

    public function replace($fields)
    {
        $prevHeight = $this->height;
        $prevWidth = $this->width;

        if ($this->update($fields) && $this->isReferenced()) {
            DB::table(config('twill.mediables_table', 'twill_mediables'))->where('media_id', $this->id)->get()->each(function ($mediable) use ($prevWidth, $prevHeight) {
                if ($prevWidth != $this->width) {
                    $mediable->crop_x = 0;
                    $mediable->crop_w = $this->width;
                }

                if ($prevHeight != $this->height) {
                    $mediable->crop_y = 0;
                    $mediable->crop_h = $this->height;
                }

                DB::table(config('twill.mediables_table', 'twill_mediables'))->where('id', $mediable->id)->update((array) $mediable);
            });
        }
    }

    public function delete()
    {
        if ($this->canDeleteSafely()) {
            return parent::delete();
        }

        return false;
    }

    public function getTable()
    {
        return config('twill.medias_table', 'twill_medias');
    }

    public function getOwners()
    {
        $morphMap = Relation::morphMap();

        $owners = collect(
            DB::table(config('twill.mediables_table', 'twill_mediables'))
                ->where('media_id', $this->id)->get()
        );

        return $owners->map(function ($owner) use ($morphMap) {
            $resolvedClass = array_key_exists($owner->mediable_type, $morphMap) ? $morphMap[$owner->mediable_type] : $owner->mediable_type;
            return resolve($resolvedClass)::find($owner->mediable_id);
        });
    }

    public function getOwnerDetails()
    {
        return collect($this->getOwners())
            ->filter(fn ($v) => is_object($v))
            ->map(function ($item) {
                if ($item instanceof Block) {
                    $model = $item->blockable;
                    if (!$model) {
                        return null;
                    }
                    $module = Str::plural(lcfirst(class_basename($model)));
                    return [
                        'id'       => $model->id,
                        'slug'     => classHasTrait($model, HasSlug::class) ? $model->slug : null,
                        'name'     => $model->{$model->titleKey},
                        'titleKey' => $model->titleKey,
                        'model'    => $model,
                        'module'   => $module,
                        'edit'     => moduleRoute($module, config('twill.module_route_prefixes.' . $module), 'edit', $model->id)
                            ? moduleRoute($module, config('twill.module_route_prefixes.' . $module), 'edit', $model->id)
                            : null,
                        '_unique'  => get_class($model) . ':' . $model->id,
                    ];
                }

                $module = Str::plural(lcfirst(class_basename($item)));
                return [
                    'id'       => $item->id,
                    'slug'     => classHasTrait($item, HasSlug::class) ? $item->slug : null,
                    'name'     => $item->{$item->titleKey},
                    'titleKey' => $item->titleKey,
                    'model'    => $item,
                    'module'   => $module,
                    'edit'     => moduleRoute($module, config('twill.module_route_prefixes.' . $module), 'edit', $item->id)
                        ? moduleRoute($module, config('twill.module_route_prefixes.' . $module), 'edit', $item->id)
                        : null,
                    '_unique'  => get_class($item) . ':' . $item->id,
                ];
            })
            ->filter()
            ->unique('_unique')
            ->map(function ($o) {
                unset($o['_unique']);
                return $o;
            })
            ->values()
            ->toArray();
    }
}
