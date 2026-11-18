<?php

use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Widgets\BarChartWidget;
use Flexpik\FilamentStudio\Widgets\ListWidget;
use Flexpik\FilamentStudio\Widgets\MeterWidget;
use Flexpik\FilamentStudio\Widgets\MetricWidget;
use Flexpik\FilamentStudio\Widgets\PieChartWidget;
use Livewire\Livewire;

it('keeps a dashboard panel at its grid width after the widget re-renders (polling)', function (string $widget, string $type) {
    $collection = StudioCollection::factory()->create();
    $panel = StudioPanel::factory()->create([
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => $type,
        'grid_col_span' => 3,
        'config' => ['collection_id' => $collection->id],
    ]);

    $component = Livewire::test($widget, ['panel' => $panel]);
    expect($component->instance()->getColumnSpan())->toBe(3);

    // A poll is just another request: mount() does not run again.
    $component->call('$refresh');

    expect($component->instance()->getColumnSpan())->toBe(3);
})->with([
    'metric' => [MetricWidget::class, 'metric'],
    'meter' => [MeterWidget::class, 'meter'],
    'pie' => [PieChartWidget::class, 'pie_chart'],
    'bar' => [BarChartWidget::class, 'bar_chart'],
    'list' => [ListWidget::class, 'list'],
]);
