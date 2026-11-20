<?php

use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Widgets\VariableWidget;
use Livewire\Livewire;

it('renders the variable widget label without host Tailwind utilities', function () {
    $panel = StudioPanel::factory()->create([
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'variable',
        'config' => ['variable_key' => 'region', 'label' => 'Region', 'interface' => 'text'],
    ]);

    Livewire::test(VariableWidget::class, ['panel' => $panel])
        ->assertSee('Region')
        ->assertDontSeeHtml('space-y-2')
        ->assertDontSeeHtml('text-gray-700')
        ->assertDontSeeHtml('dark:text-gray-300')
        ->assertSeeHtml('fi-input');
});
