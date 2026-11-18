<?php

use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Widgets\BarChartWidget;
use Flexpik\FilamentStudio\Widgets\PieChartWidget;

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['tenant_id' => 1, 'slug' => 'customers']);
    $status = StudioField::factory()->create(['collection_id' => $this->collection->id, 'tenant_id' => 1, 'column_name' => 'status', 'field_type' => 'select', 'eav_cast' => 'text']);
    StudioField::factory()->create(['collection_id' => $this->collection->id, 'tenant_id' => 1, 'column_name' => 'name', 'field_type' => 'text', 'eav_cast' => 'text']);

    foreach (['lead' => 'Lead', 'customer' => 'Customer', 'churned' => 'Churned'] as $value => $label) {
        StudioFieldOption::create(['field_id' => $status->id, 'tenant_id' => 1, 'value' => $value, 'label' => $label, 'sort_order' => 0]);
    }

    foreach (['lead', 'customer', 'customer', 'churned'] as $i => $value) {
        EavQueryBuilder::for($this->collection)->tenant(1)->create(['status' => $value, 'name' => "C{$i}"]);
    }

    $this->panel = fn (string $type) => StudioPanel::factory()->create([
        'tenant_id' => 1,
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => $type,
        'header_label' => 'Customers by Status',
        'config' => ['collection_id' => $this->collection->id, 'group_field' => 'status', 'value_field' => 'name', 'aggregate_function' => 'count'],
    ]);
});

it('gives every slice and bar its own solid colour', function (string $widgetClass, string $type) {
    $widget = new $widgetClass;
    $widget->mount(($this->panel)($type));
    $data = $widget->getData();

    $colours = $data['datasets'][0]['backgroundColor'];

    expect($colours)->toHaveCount(count($data['labels']))
        ->and(array_unique($colours))->toHaveCount(count($data['labels']));
})->with([
    'pie' => [PieChartWidget::class, 'pie_chart'],
    'bar' => [BarChartWidget::class, 'bar_chart'],
]);

it('labels groups by their option label, not the stored value', function (string $widgetClass, string $type) {
    $widget = new $widgetClass;
    $widget->mount(($this->panel)($type));

    expect($widget->getData()['labels'])->toEqualCanonicalizing(['Lead', 'Customer', 'Churned']);
})->with([
    'pie' => [PieChartWidget::class, 'pie_chart'],
    'bar' => [BarChartWidget::class, 'bar_chart'],
]);
