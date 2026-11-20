<?php

namespace Flexpik\FilamentStudio\Services;

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Illuminate\Support\Facades\DB;

/**
 * Manual ordering of records backed by the `sort_order` system field
 * (created by the collection wizard), whose values live in studio_values.
 */
class RecordSortOrder
{
    public const COLUMN = 'sort_order';

    /**
     * The collection's `sort_order` system field, or null when manual ordering is not enabled.
     */
    public static function field(StudioCollection $collection): ?StudioField
    {
        return EavQueryBuilder::getCachedFields($collection)
            ->first(fn (StudioField $field): bool => $field->column_name === self::COLUMN
                && $field->is_system
                && $field->eav_cast->column() === 'val_integer');
    }

    public static function isEnabled(StudioCollection $collection): bool
    {
        return self::field($collection) !== null;
    }

    /**
     * Fill `sort_order` with (current max + 1) when the collection has the field and no value was given.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillNext(StudioCollection $collection, ?int $tenantId, array $data): array
    {
        $field = self::field($collection);

        if ($field === null || filled($data[self::COLUMN] ?? null)) {
            return $data;
        }

        $data[self::COLUMN] = self::max($collection, $field, $tenantId) + 1;

        return $data;
    }

    protected static function max(StudioCollection $collection, StudioField $field, ?int $tenantId): int
    {
        $values = (new StudioValue)->getTable();
        $records = (new StudioRecord)->getTable();

        return (int) DB::table($values)
            ->join($records, "{$records}.id", '=', "{$values}.record_id")
            ->where("{$values}.field_id", $field->id)
            ->where("{$records}.collection_id", $collection->id)
            ->when(
                $tenantId === null,
                fn ($q) => $q->whereNull("{$records}.tenant_id"),
                fn ($q) => $q->where("{$records}.tenant_id", $tenantId),
            )
            ->max("{$values}.val_integer");
    }

    /**
     * Persist a new order: each record key gets its 1-based position (reversed for a descending order).
     * Keys that do not belong to the collection (or tenant, when given) are ignored.
     *
     * @param  array<int|string>  $order  record primary keys in their new display order
     */
    public static function reorder(StudioCollection $collection, array $order, ?int $tenantId = null, string $direction = 'asc'): void
    {
        $field = self::field($collection);

        if ($field === null) {
            return;
        }

        $order = array_values($order);

        if ($direction === 'desc') {
            $order = array_reverse($order);
        }

        $validKeys = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $order)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $locale = app(LocaleResolver::class)->defaultLocale($collection);

        DB::transaction(function () use ($order, $validKeys, $field, $locale): void {
            $position = 0;

            foreach ($order as $key) {
                if (! in_array((string) $key, $validKeys, true)) {
                    continue;
                }

                StudioValue::updateOrCreate(
                    ['record_id' => $key, 'field_id' => $field->id, 'locale' => $locale],
                    ['val_integer' => ++$position],
                );
            }
        });
    }
}
