<?php

namespace Flexpik\FilamentStudio\Services;

use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * CSV export and import of a collection's records. The header row holds field column names
 * (import also accepts field labels); arrays travel as JSON, booleans as 1/0.
 */
class RecordCsv
{
    /** Types with nothing to export or import: layout-only, or computed from other records. */
    protected const SKIPPED_TYPES = ['section_header', 'callout', 'divider', 'has_many'];

    /** Leading characters a spreadsheet would run as a formula. */
    protected const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Fields that appear as CSV columns (never passwords).
     *
     * @return Collection<int, StudioField>
     */
    public static function exportFields(StudioCollection $collection): Collection
    {
        return EavQueryBuilder::getCachedFields($collection)
            ->reject(fn (StudioField $field): bool => in_array($field->field_type, [...self::SKIPPED_TYPES, 'password'], true))
            ->sortBy([['is_system', 'asc'], ['sort_order', 'asc']])
            ->values();
    }

    /**
     * Write the records as CSV to the given stream (e.g. php://output).
     *
     * @param  iterable<StudioRecord>  $records
     * @param  resource  $handle
     */
    public static function write(StudioCollection $collection, iterable $records, $handle, ?int $tenantId = null): void
    {
        $fields = static::exportFields($collection);
        $builder = EavQueryBuilder::for($collection)->tenant($tenantId);

        fputcsv($handle, ['uuid', ...$fields->pluck('column_name')->all()], escape: '');

        foreach ($records as $record) {
            $data = $builder->getRecordData($record);

            $row = [$record->uuid];
            foreach ($fields as $field) {
                $row[] = static::toCell($data[$field->column_name] ?? null);
            }

            fputcsv($handle, $row, escape: '');
        }
    }

    public static function toCsvString(StudioCollection $collection, iterable $records, ?int $tenantId = null): string
    {
        $handle = fopen('php://temp', 'r+');
        static::write($collection, $records, $handle, $tenantId);
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Create one record per CSV row. Rows are validated like REST API writes; a failing row is
     * skipped and reported, the others are still imported.
     *
     * @return array{created: int, failed: list<array{row: int, errors: list<string>}>, ignored_columns: list<string>}
     */
    public static function import(StudioCollection $collection, string $path, ?int $tenantId = null, ?int $userId = null): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('The CSV file could not be read.');
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if (! is_array($header) || $header === [null]) {
                return ['created' => 0, 'failed' => [['row' => 1, 'errors' => ['The file has no header row.']]], 'ignored_columns' => []];
            }

            // Strip a UTF-8 BOM that spreadsheet apps prepend.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

            [$map, $ignored] = static::mapHeader($collection, $header);

            $fields = EavQueryBuilder::getCachedFields($collection);
            $rules = RecordInputRules::for($collection, $fields, 'store');
            $builder = EavQueryBuilder::for($collection)->tenant($tenantId);

            $created = 0;
            $failed = [];
            $line = 1;

            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($cells === [null]) {
                    continue; // blank line
                }

                $data = [];
                foreach ($map as $index => $field) {
                    $value = static::fromCell($field, $cells[$index] ?? '');

                    if ($value !== null) {
                        $data[$field->column_name] = $value;
                    }
                }

                $validator = Validator::make(['data' => $data], $rules, [], static::attributeNames($fields));

                if ($validator->fails()) {
                    $failed[] = ['row' => $line, 'errors' => array_values($validator->errors()->all())];

                    continue;
                }

                try {
                    $builder->create($data, $userId);
                    $created++;
                } catch (\Throwable $e) {
                    $failed[] = ['row' => $line, 'errors' => [$e->getMessage()]];
                }
            }

            return ['created' => $created, 'failed' => $failed, 'ignored_columns' => $ignored];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Match header cells to writable fields by column name or label (case-insensitive).
     *
     * @param  array<int, string|null>  $header
     * @return array{0: array<int, StudioField>, 1: list<string>}
     */
    protected static function mapHeader(StudioCollection $collection, array $header): array
    {
        $writable = EavQueryBuilder::getCachedFields($collection)
            ->reject(fn (StudioField $field): bool => $field->is_system
                || ! empty($field->auto_fill_on)
                || in_array($field->field_type, self::SKIPPED_TYPES, true));

        $map = [];
        $ignored = [];

        foreach ($header as $index => $name) {
            $name = trim((string) $name);
            $key = Str::lower($name);

            $field = $writable->first(fn (StudioField $f): bool => Str::lower($f->column_name) === $key)
                ?? $writable->first(fn (StudioField $f): bool => Str::lower((string) $f->label) === $key);

            if ($field) {
                $map[$index] = $field;
            } elseif ($name !== '' && $key !== 'uuid') {
                $ignored[] = $name;
            }
        }

        return [$map, $ignored];
    }

    protected static function toCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) $value;

        // Defuse spreadsheet formula injection; import strips the quote again.
        if ($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    protected static function fromCell(StudioField $field, ?string $cell): mixed
    {
        $cell = (string) $cell;

        if (strlen($cell) > 1 && $cell[0] === "'" && in_array($cell[1], self::FORMULA_PREFIXES, true)) {
            $cell = substr($cell, 1);
        }

        if (trim($cell) === '') {
            return null;
        }

        return match ($field->eav_cast) {
            EavCast::Boolean => match (Str::lower(trim($cell))) {
                '1', 'true', 'yes', 'y', 'on' => true,
                '0', 'false', 'no', 'n', 'off' => false,
                default => $cell, // left for validation to reject
            },
            EavCast::Integer => is_numeric($cell) && (int) $cell == $cell ? (int) $cell : $cell,
            EavCast::Decimal => is_numeric($cell) ? (float) $cell : $cell,
            EavCast::Json => static::decodeList($cell),
            default => $cell,
        };
    }

    /**
     * JSON when the cell holds JSON, otherwise a comma-separated list ("red, blue").
     */
    protected static function decodeList(string $cell): mixed
    {
        $decoded = json_decode($cell, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return array_values(array_filter(array_map('trim', explode(',', $cell)), fn (string $v): bool => $v !== ''));
    }

    /**
     * @param  Collection<int, StudioField>  $fields
     * @return array<string, string>
     */
    protected static function attributeNames(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (StudioField $field): array => ['data.'.$field->column_name => (string) ($field->label ?: $field->column_name)])->all();
    }
}
