<?php

namespace A17\Twill\Models;

use A17\Twill\Models\Behaviors\HasSlug;
use A17\Twill\Services\FileLibrary\FileService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class File extends Model
{
    public $timestamps = true;

    protected $appends = ['owners'];

    protected $fillable = [
        'uuid',
        'filename',
        'size',
    ];

    public function getSizeAttribute($value)
    {
        return bytesToHuman($value);
    }

    public function canDeleteSafely()
    {
        return DB::table(config('twill.fileables_table', 'twill_fileables'))
            ->where('file_id', $this->id)->doesntExist();
    }

    public function scopeUnused($query)
    {
        $usedIds = DB::table(config('twill.fileables_table'))->pluck('file_id');

        return $query->whereNotIn('id', $usedIds->toArray())->get();
    }

    public function getOwnersAttribute(): array
    {
        return $this->getOwnerDetails() ?: [];
    }

    public function toCmsArray()
    {
        $uploadedDate = [];
        if (config('twill.file-library.show_uploaded_date')) {
            $uploadedDate = [
                'uploadedDate' => $this->created_at->format(config("twill.file-library.format_uploaded_date", "d/m/Y H:i"))
            ];
        }
        return [
            'id' => $this->id,
            'name' => $this->filename,
            'src' => FileService::getUrl($this->uuid),
            'original' => FileService::getUrl($this->uuid),
            'size' => $this->size,
            'owners' => $this->getOwnersAttribute(),
            'filesizeInMb' => number_format($this->attributes['size'] / 1048576, 2),
        ] + $uploadedDate;
    }

    public function getTable()
    {
        return config('twill.files_table', 'twill_files');
    }

    public function getOwners()
    {
        $morphMap = Relation::morphMap();

        $owners = collect(
            DB::table(config('twill.fileables_table', 'twill_fileables'))
                ->where('file_id', $this->id)
                ->get()
        );

        return $owners->map(function ($owner) use ($morphMap) {
            $resolvedClass = array_key_exists($owner->fileable_type, $morphMap)
                ? $morphMap[$owner->fileable_type]
                : $owner->fileable_type;

            return resolve($resolvedClass)::find($owner->fileable_id);
        });
    }

    public function getOwnerDetails(): array
    {
        $owners = $this->getOwners();

        return collect($owners)
            ->filter(fn ($v) => is_object($v))
            ->map(function ($item) {
                if ($item instanceof Block) {
                    $model = $item->blockable;
                    if (! $model) {
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
