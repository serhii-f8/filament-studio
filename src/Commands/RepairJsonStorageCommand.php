<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Commands;

use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\FieldTypes\FieldTypeRegistry;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs values stored in the val_json column that are not valid JSON.
 *
 *  - v1.0.3 moved rich_editor, markdown, file, image and avatar fields from JSON to text
 *    storage without migrating fields created earlier. Those fields are switched to their
 *    type's current storage column and their values moved across.
 *  - Releases up to v1.9.0 wrote plain strings into val_json unencoded. Those values are
 *    JSON-encoded in place.
 *
 * Malformed JSON makes SQLite fail every query that touches the column ("malformed JSON")
 * and MySQL reject the write. Idempotent: valid data is left untouched.
 */
class RepairJsonStorageCommand extends Command
{
    protected $signature = 'studio:repair-json-storage';

    protected $description = 'Move legacy JSON-stored text fields to text storage and re-encode malformed JSON values';

    public function handle(FieldTypeRegistry $registry): int
    {
        $types = $registry->all();
        $migrated = 0;
        $encoded = 0;
        $table = (new StudioValue)->getTable();

        StudioField::query()
            ->where('eav_cast', EavCast::Json->value)
            ->orderBy('id')
            ->each(function (StudioField $field) use ($types, $table, &$migrated, &$encoded) {
                $class = $types[$field->field_type] ?? null;
                $target = $class !== null ? $class::$eavCast : EavCast::Json;

                if ($target === EavCast::Text) {
                    $this->moveToText($field, $table);
                    $migrated++;

                    return;
                }

                if ($target === EavCast::Json) {
                    $encoded += $this->encodeMalformed($field, $table);
                }
            });

        $this->info("Moved {$migrated} legacy field(s) to text storage and re-encoded {$encoded} malformed value(s).");

        return self::SUCCESS;
    }

    protected function moveToText(StudioField $field, string $table): void
    {
        DB::transaction(function () use ($field, $table) {
            DB::table($table)
                ->where('field_id', $field->id)
                ->whereNotNull('val_json')
                ->orderBy('id')
                ->each(function (object $row) use ($table) {
                    DB::table($table)->where('id', $row->id)->update([
                        'val_text' => $this->asText((string) $row->val_json),
                        'val_json' => null,
                    ]);
                });

            $field->eav_cast = EavCast::Text;
            $field->saveQuietly();
        });
    }

    protected function encodeMalformed(StudioField $field, string $table): int
    {
        $count = 0;

        DB::table($table)
            ->where('field_id', $field->id)
            ->whereNotNull('val_json')
            ->orderBy('id')
            ->each(function (object $row) use ($table, &$count) {
                if (json_validate((string) $row->val_json)) {
                    return;
                }

                DB::table($table)->where('id', $row->id)->update(['val_json' => json_encode($row->val_json)]);
                $count++;
            });

        return $count;
    }

    /**
     * A JSON-encoded string becomes the plain string; other valid JSON (e.g. a list of
     * uploaded file paths) stays encoded; anything else already is the plain text.
     */
    protected function asText(string $raw): string
    {
        if (! json_validate($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return is_string($decoded) ? $decoded : $raw;
    }
}
