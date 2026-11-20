<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Commands;

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Illuminate\Console\Command;

/**
 * Repairs system fields created by releases up to v1.8.1:
 *  - select-style fields whose choices were parked in settings.default_options (never read)
 *  - auto-fill fields (created/updated at/by) that were never written, back-filled from the
 *    record's own created_at / updated_at / created_by / updated_by columns
 *
 * Idempotent: only fills what is missing and never overwrites existing data.
 */
class RepairSystemFieldsCommand extends Command
{
    protected $signature = 'studio:repair-system-fields';

    protected $description = 'Create missing select options and back-fill empty created/updated system fields';

    public function handle(): int
    {
        $options = $this->repairLegacyOptions();
        $values = $this->backfillAutoFillValues();
        $this->defaultStatusToDraft();

        $this->info("Repaired {$options} field(s) with missing options and back-filled {$values} value(s).");

        return self::SUCCESS;
    }

    protected function repairLegacyOptions(): int
    {
        $repaired = 0;

        StudioField::query()
            ->whereNotNull('settings')
            ->orderBy('id')
            ->each(function (StudioField $field) use (&$repaired) {
                $settings = $field->settings ?? [];
                $legacy = $settings['default_options'] ?? null;

                if (! is_array($legacy)) {
                    return;
                }

                if (! StudioFieldOption::where('field_id', $field->id)->exists()) {
                    foreach (array_values($legacy) as $index => $option) {
                        if (! isset($option['value'])) {
                            continue;
                        }

                        StudioFieldOption::create([
                            'field_id' => $field->id,
                            'tenant_id' => $field->tenant_id,
                            'value' => (string) $option['value'],
                            'label' => (string) ($option['label'] ?? $option['value']),
                            'sort_order' => $index,
                        ]);
                    }

                    $repaired++;
                }

                unset($settings['default_options']);
                $field->settings = $settings;
                $field->saveQuietly();
            });

        return $repaired;
    }

    /**
     * Status fields created up to v1.9.0 were required but had no default. Only fills an
     * empty default, and only when "draft" is one of the field's options.
     */
    protected function defaultStatusToDraft(): void
    {
        StudioField::query()
            ->where('is_system', true)
            ->where('column_name', 'status')
            ->where(fn ($q) => $q->whereNull('default_value')->orWhere('default_value', ''))
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from((new StudioFieldOption)->getTable().' as o')
                ->whereColumn('o.field_id', (new StudioField)->getTable().'.id')
                ->where('o.value', 'draft'))
            ->update(['default_value' => 'draft']);
    }

    protected function backfillAutoFillValues(): int
    {
        $filled = 0;
        $locales = app(LocaleResolver::class);

        StudioField::query()
            ->whereNotNull('auto_fill_on')
            ->whereIn('auto_fill_value', ['$NOW', '$CURRENT_USER'])
            ->orderBy('id')
            ->each(function (StudioField $field) use (&$filled, $locales) {
                $source = $this->recordColumnFor($field);
                $collection = StudioCollection::find($field->collection_id);

                if ($source === null || $collection === null) {
                    return;
                }

                $valueColumn = $field->eavColumn();
                $locale = $locales->defaultLocale($collection);

                StudioRecord::withTrashed()
                    ->where('collection_id', $field->collection_id)
                    ->whereNotNull($source)
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')
                        ->from((new StudioValue)->getTable().' as v')
                        ->whereColumn('v.record_id', (new StudioRecord)->getTable().'.id')
                        ->where('v.field_id', $field->id))
                    ->orderBy('id')
                    ->each(function (StudioRecord $record) use ($field, $source, $valueColumn, $locale, &$filled) {
                        $value = $record->getRawOriginal($source);

                        StudioValue::create([
                            'record_id' => $record->id,
                            'field_id' => $field->id,
                            'locale' => $locale,
                            $valueColumn => $valueColumn === 'val_integer' ? (int) $value : $value,
                        ]);

                        $filled++;
                    });
            });

        return $filled;
    }

    /**
     * Which studio_records column holds the value this auto-fill field should have had.
     */
    protected function recordColumnFor(StudioField $field): ?string
    {
        $onUpdate = in_array('update', $field->auto_fill_on ?? [], true);

        return match ($field->auto_fill_value) {
            '$NOW' => $onUpdate ? 'updated_at' : 'created_at',
            '$CURRENT_USER' => $onUpdate ? 'updated_by' : 'created_by',
            default => null,
        };
    }
}
