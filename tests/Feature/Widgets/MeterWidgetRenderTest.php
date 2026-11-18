<?php

use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Widgets\MeterWidget;
use Livewire\Livewire;

function meterPanel(array $config): StudioPanel
{
    return StudioPanel::factory()->create([
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'meter',
        'header_label' => 'Stock Health',
        'config' => ['collection_id' => StudioCollection::factory()->create()->id] + $config,
    ]);
}

it('maps a Filament colour name to its theme variable', function () {
    Livewire::test(MeterWidget::class, ['panel' => meterPanel(['color' => 'success'])])
        ->assertSeeHtml('stroke="var(--success-500)"')
        ->assertDontSeeHtml('stroke="success"');
});

it('passes a hex colour through unchanged', function () {
    Livewire::test(MeterWidget::class, ['panel' => meterPanel(['color' => '#10b981'])])
        ->assertSeeHtml('stroke="#10b981"');
});

it('draws the track without depending on host Tailwind utilities', function () {
    Livewire::test(MeterWidget::class, ['panel' => meterPanel([])])
        ->assertDontSeeHtml('text-gray-200')
        ->assertDontSeeHtml('max-w-[200px]')
        ->assertSeeHtml('color-mix(');
});

it('leaves plain CSS colour names alone', function () {
    Livewire::test(MeterWidget::class, ['panel' => meterPanel(['color' => 'tomato'])])
        ->assertSeeHtml('stroke="tomato"');
});
