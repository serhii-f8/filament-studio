<?php

namespace Flexpik\FilamentStudio\Services;

use Filament\GlobalSearch\GlobalSearchResult;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Support\RecordTitle;
use Illuminate\Support\Collection;

/**
 * Searches Studio records' text values for Filament's global search, across every collection
 * the user may view in the current tenant.
 */
class RecordGlobalSearch
{
    /** Text-stored types whose values are not meaningful or not safe to match. */
    protected const EXCLUDED_TYPES = [
        'password', 'hidden', 'file', 'image', 'avatar', 'color',
        'section_header', 'callout', 'divider',
    ];

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    public function search(string $query, ?int $tenantId, int $limitPerCollection = 5): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $user = auth()->user();
        // "!" escapes LIKE wildcards: a backslash is itself an escape in MySQL string literals.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)).'%';
        $results = collect();

        $collections = StudioCollection::query()
            ->forTenant($tenantId)
            ->orderBy('label')
            ->get();

        foreach ($collections as $collection) {
            if (! $user?->can('viewRecords', $collection)) {
                continue;
            }

            $fieldIds = EavQueryBuilder::getCachedFields($collection)
                ->filter(fn (StudioField $field): bool => $field->eav_cast->column() === 'val_text'
                    && ! in_array($field->field_type, self::EXCLUDED_TYPES, true))
                ->pluck('id');

            if ($fieldIds->isEmpty()) {
                continue;
            }

            $recordIds = StudioValue::query()
                ->whereIn('field_id', $fieldIds)
                ->whereRaw("LOWER(val_text) LIKE ? ESCAPE '!'", [$pattern])
                ->distinct()
                ->pluck('record_id');

            if ($recordIds->isEmpty()) {
                continue;
            }

            $records = StudioRecord::query()
                ->whereIn('id', $recordIds)
                ->where('collection_id', $collection->id)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->latest('id')
                ->limit($limitPerCollection)
                ->get();

            foreach ($records as $record) {
                $results->push(new GlobalSearchResult(
                    title: RecordTitle::forRecord($collection, $record) ?? $collection->label,
                    url: DynamicCollectionResource::getUrl('view', [
                        'collection_slug' => $collection->slug,
                        'record' => $record->uuid,
                    ]),
                    details: ['Collection' => $collection->label_plural ?: $collection->label],
                ));
            }
        }

        return $results;
    }
}
