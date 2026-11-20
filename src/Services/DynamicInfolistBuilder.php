<?php

namespace Flexpik\FilamentStudio\Services;

use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Section;
use Flexpik\FilamentStudio\FieldTypes\FieldTypeRegistry;
use Flexpik\FilamentStudio\FieldTypes\Types\SectionHeaderFieldType;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User;

/**
 * Builds the read-only record view: one entry per form field, grouped by section headers the same
 * way as the form, with visibility conditions evaluated against the record's stored values.
 */
class DynamicInfolistBuilder
{
    /**
     * @param  array<string, mixed>  $data  the record's values keyed by column name
     * @return array<Section>
     */
    public static function build(StudioCollection $collection, array $data, ?User $user = null): array
    {
        /** @var Collection<int, StudioField> $fields */
        $fields = $collection->fields()
            ->where('is_hidden_in_form', false)
            ->orderBy('is_system')
            ->orderBy('sort_order')
            ->get();

        $registry = app(FieldTypeRegistry::class);
        $get = fn (string $name): mixed => data_get($data, $name);

        $sections = [];
        $header = null;
        $entries = [];

        $flush = function () use (&$sections, &$header, &$entries): void {
            if ($entries !== []) {
                $sections[] = static::makeSection($header, $entries);
            }
            $entries = [];
        };

        foreach ($fields as $field) {
            if ($field->field_type === SectionHeaderFieldType::$key) {
                $flush();
                $header = $field;

                continue;
            }

            if (! array_key_exists($field->field_type, $registry->all())) {
                continue;
            }

            $entry = $registry->make($field)->buildInfolistEntry();

            if ($entry === null) {
                continue;
            }

            $conditions = $field->settings['conditions'] ?? null;
            if ($conditions) {
                $evaluator = new ConditionEvaluator($conditions, 'edit', $user);

                if ($evaluator->hasVisible()) {
                    $visible = $evaluator->buildVisibleClosure();
                    $entry->visible(fn (): bool => $visible($get));
                }
            }

            $entries[] = $entry;
        }

        $flush();

        return $sections;
    }

    /**
     * @param  array<int, Entry>  $entries
     */
    protected static function makeSection(?StudioField $header, array $entries): Section
    {
        $settings = $header?->settings ?? [];

        $section = Section::make($settings['section_label'] ?? null)
            ->description($settings['description'] ?? null)
            ->icon($settings['icon'] ?? null)
            ->columns((int) ($settings['columns'] ?? 2))
            ->schema($entries);

        if ($settings['collapsible'] ?? false) {
            $section->collapsible()->collapsed((bool) ($settings['collapsed'] ?? false));
        }

        return $section;
    }
}
