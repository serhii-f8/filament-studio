<?php

namespace Flexpik\FilamentStudio\Concerns;

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioFieldOption;

/**
 * Shared presentation for charts that aggregate by a group field (pie, bar).
 */
trait PresentsGroupedChart
{
    /**
     * Chart.js draws on a canvas, so colours must be concrete values (not CSS variables).
     * Tailwind 500-shades, ordered so neighbouring slices stay distinguishable.
     *
     * @var array<int, string>
     */
    protected static array $chartPalette = [
        '#f59e0b', '#0ea5e9', '#10b981', '#8b5cf6', '#f43f5e',
        '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#ec4899',
    ];

    /**
     * @return array<int, string>
     */
    protected function chartColours(int $count): array
    {
        $palette = static::$chartPalette;

        return array_map(fn (int $i): string => $palette[$i % count($palette)], range(0, max(0, $count - 1)));
    }

    /**
     * Swap stored option values (e.g. "churned") for their labels ("Churned") when the group
     * field is an option-backed field; anything without an option keeps its raw value.
     *
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    protected function groupLabels(StudioCollection $collection, string $groupField, array $keys): array
    {
        $field = $collection->fields()->where('column_name', $groupField)->first();

        $labels = $field === null
            ? []
            : StudioFieldOption::where('field_id', $field->id)->pluck('label', 'value')->all();

        return array_map(fn (mixed $key): string => (string) ($labels[$key] ?? $key), $keys);
    }
}
