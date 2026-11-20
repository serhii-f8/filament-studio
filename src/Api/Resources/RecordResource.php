<?php

namespace Flexpik\FilamentStudio\Api\Resources;

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudioRecord
 */
class RecordResource extends JsonResource
{
    protected ?StudioCollection $collection = null;

    public function setCollection(StudioCollection $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * Keys that are always present at the top level of an API record.
     */
    public const RECORD_META_KEYS = ['uuid', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /**
     * Prepare a record's field values for an API response: never expose password
     * hashes, and drop system fields that duplicate the record's top-level metadata.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function presentData(StudioCollection $collection, array $data): array
    {
        $hidden = EavQueryBuilder::getCachedFields($collection)
            ->filter(fn ($field) => $field->is_system && in_array($field->column_name, self::RECORD_META_KEYS, true))
            ->pluck('column_name')
            ->merge(EavQueryBuilder::secretColumns($collection))
            ->all();

        return array_diff_key($data, array_flip($hidden));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $collection = $this->collection ?? $request->attributes->get('studio_collection');

        $locale = app(LocaleResolver::class)->resolve($collection);

        $data = EavQueryBuilder::for($collection)
            ->locale($locale)
            ->getRecordData($this->resource);

        $data = static::presentData($collection, $data);

        return [
            'uuid' => $this->uuid,
            'data' => $data,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
