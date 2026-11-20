<?php

namespace Flexpik\FilamentStudio\Support;

use Filament\Facades\Filament;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Str;

/**
 * A human-readable title for a record: the collection's display template when it has one,
 * otherwise the first filled plain-text field.
 */
class RecordTitle
{
    /** Field types whose value can stand in as a title. */
    protected const TITLE_FIELD_TYPES = ['text', 'slug', 'textarea'];

    protected const MAX_LENGTH = 80;

    /**
     * @param  array<string, mixed>  $data  the record's values keyed by column name
     */
    public static function fromData(StudioCollection $collection, array $data): ?string
    {
        $template = trim((string) $collection->display_template);

        if ($template !== '') {
            $filled = false;

            $title = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function (array $match) use ($data, &$filled): string {
                $value = static::scalar($data[$match[1]] ?? null);
                $filled = $filled || $value !== '';

                return $value;
            }, $template);

            if ($filled) {
                return static::clean((string) $title);
            }
        }

        $fields = EavQueryBuilder::getCachedFields($collection)
            ->filter(fn (StudioField $field): bool => ! $field->is_system && in_array($field->field_type, self::TITLE_FIELD_TYPES, true))
            ->sortBy('sort_order');

        foreach ($fields as $field) {
            $value = static::scalar($data[$field->column_name] ?? null);

            if ($value !== '') {
                return static::clean($value);
            }
        }

        return null;
    }

    public static function forRecord(StudioCollection $collection, StudioRecord $record): ?string
    {
        $data = EavQueryBuilder::for($collection)
            ->tenant($record->tenant_id)
            ->getRecordData($record);

        return static::fromData($collection, $data);
    }

    /**
     * Titles for related records referenced by id or uuid, keyed by the reference as given.
     * A related field set as the relationship's display field wins over the record title.
     *
     * @param  array<int, int|string>  $keys
     * @return array<string, string>
     */
    public static function forKeys(string $collectionSlug, array $keys, ?string $displayField = null): array
    {
        $keys = array_values(array_filter($keys, fn (mixed $key): bool => is_int($key) || (is_string($key) && $key !== '')));

        if ($keys === []) {
            return [];
        }

        $collection = StudioCollection::where('slug', $collectionSlug)
            ->forTenant(Filament::getTenant()?->getKey())
            ->first();

        if (! $collection) {
            return [];
        }

        $records = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->where(fn ($query) => $query
                ->whereIn('id', array_filter($keys, 'is_numeric'))
                ->orWhereIn('uuid', array_map('strval', $keys)))
            ->get();

        $titles = [];

        foreach ($records as $record) {
            $data = EavQueryBuilder::for($collection)->tenant($record->tenant_id)->getRecordData($record);
            $display = $displayField ? static::scalar($data[$displayField] ?? null) : '';
            $title = $display !== '' ? static::clean($display) : (static::fromData($collection, $data) ?? "#{$record->id}");

            $titles[(string) $record->id] = $title;
            $titles[(string) $record->uuid] = $title;
        }

        return $titles;
    }

    protected static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    protected static function clean(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', strip_tags($title)) ?? '');

        return Str::limit($title, self::MAX_LENGTH);
    }
}
