<?php

namespace Flexpik\FilamentStudio\FieldTypes\Concerns;

use Filament\Support\Colors\Color;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Illuminate\Support\Collection;

/**
 * Label and badge colour lookup for fields whose values are StudioFieldOption values.
 */
trait DisplaysOptions
{
    /** @var Collection<string, StudioFieldOption>|null */
    protected ?Collection $displayOptions = null;

    protected function displayOption(mixed $state): ?StudioFieldOption
    {
        // Loaded once, on first render, not when the column or entry is built.
        $this->displayOptions ??= StudioFieldOption::where('field_id', $this->field->id)->get()->keyBy('value');

        return $this->displayOptions->get((string) $state);
    }

    protected function optionLabel(mixed $state): string
    {
        return (string) ($this->displayOption($state)?->label ?? $state);
    }

    /**
     * @return string|array<int|string, string>
     */
    protected function optionColor(mixed $state): string|array
    {
        $color = $this->displayOption($state)?->color ?: 'primary';

        // Option colours may be Filament names ("success") or hex values set via API/MCP.
        return str_starts_with($color, '#') ? Color::hex($color) : $color;
    }
}
