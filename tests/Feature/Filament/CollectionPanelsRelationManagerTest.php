<?php

use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioDashboard;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\EditCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\RelationManagers\PanelsRelationManager;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ViewCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Widgets\LabelWidget;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->create(['name' => 'orders', 'slug' => 'orders', 'tenant_id' => null]);
    StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text',
    ]);
});

function collectionPanelsManager(StudioCollection $collection)
{
    return Livewire::test(PanelsRelationManager::class, [
        'ownerRecord' => $collection,
        'pageClass' => EditCollection::class,
    ]);
}

/** @return list<object> */
function widgetsOn(object $page, string $method): array
{
    $reflection = new ReflectionMethod($page, $method);

    return $reflection->invoke($page);
}

function bindOrdersRoute(): void
{
    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'orders');
    request()->setRouteResolver(fn () => $route);
}

it('is registered on the collection manager', function () {
    expect(CollectionManagerResource::getRelations())->toContain(PanelsRelationManager::class);
});

it('creates a collection header panel that renders above the record list', function () {
    collectionPanelsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['placement' => 'collection_header', 'panel_type' => 'label'])
        ->setTableActionData(['header_label' => 'Heads up', 'config.text' => 'Orders ship on Fridays'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $panel = StudioPanel::sole();

    expect($panel->placement)->toBe(PanelPlacement::CollectionHeader)
        ->and($panel->context_collection_id)->toBe($this->collection->id)
        ->and($panel->dashboard_id)->toBeNull()
        ->and($panel->sort_order)->toBe(1)
        ->and($panel->config['text'])->toBe('Orders ship on Fridays');

    bindOrdersRoute();
    $page = Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'orders'])->instance();
    $widgets = widgetsOn($page, 'getHeaderWidgets');

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0]->widget)->toBe(LabelWidget::class)
        ->and($widgets[0]->getProperties()['panel']->is($panel))->toBeTrue()
        ->and(widgetsOn($page, 'getFooterWidgets'))->toBeEmpty();
});

it('creates a record header panel that renders on the record page', function () {
    collectionPanelsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['placement' => 'record_header', 'panel_type' => 'label'])
        ->setTableActionData(['config.text' => 'Check the address'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $record = EavQueryBuilder::for($this->collection)->create(['title' => 'Order 1']);

    bindOrdersRoute();
    $page = Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'orders', 'record' => $record->uuid])->instance();
    $widgets = widgetsOn($page, 'getHeaderWidgets');

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0]->getProperties()['recordUuid'])->toBe($record->uuid);
});

it('points a new data panel at the owning collection', function () {
    collectionPanelsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['placement' => 'collection_footer', 'panel_type' => 'metric'])
        ->assertTableActionDataSet(['config.collection_id' => $this->collection->id]);
});

it('offers only the panel types the placement supports', function () {
    $manager = collectionPanelsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['placement' => 'record_footer']);

    $options = $manager->instance()->getMountedTableActionForm()->getComponent('panel_type')->getOptions();

    expect($options)->toHaveKey('label')
        ->and($options)->not->toHaveKey('variable');
});

it('does not offer the dashboard placement', function () {
    expect(PanelsRelationManager::placementOptions())
        ->not->toHaveKey('dashboard')
        ->toHaveKeys(['collection_header', 'collection_footer', 'record_header', 'record_footer']);
});

it('lists only this collection\'s non-dashboard panels', function () {
    $mine = StudioPanel::factory()->forCollectionHeader($this->collection->id)->create(['tenant_id' => null]);
    $other = StudioPanel::factory()->forCollectionHeader(StudioCollection::factory()->create(['tenant_id' => null])->id)->create(['tenant_id' => null]);
    $dashboardPanel = StudioPanel::factory()->forDashboard(StudioDashboard::factory()->create(['tenant_id' => null]))
        ->create(['tenant_id' => null, 'context_collection_id' => $this->collection->id]);

    collectionPanelsManager($this->collection)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other, $dashboardPanel]);
});

it('edits a panel\'s placement and label', function () {
    $panel = StudioPanel::factory()->forCollectionHeader($this->collection->id)->create([
        'tenant_id' => null, 'panel_type' => 'label', 'header_label' => 'Old', 'config' => ['text' => 'Hi'],
    ]);

    collectionPanelsManager($this->collection)
        ->mountTableAction('edit', $panel)
        ->assertTableActionDataSet(['placement' => 'collection_header', 'config.text' => 'Hi'])
        ->setTableActionData(['placement' => 'collection_footer', 'header_label' => 'New'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $panel->refresh();

    expect($panel->placement)->toBe(PanelPlacement::CollectionFooter)
        ->and($panel->header_label)->toBe('New');
});

it('deletes a panel', function () {
    $panel = StudioPanel::factory()->forCollectionHeader($this->collection->id)->create(['tenant_id' => null]);

    collectionPanelsManager($this->collection)->callTableAction('delete', $panel);

    expect(StudioPanel::find($panel->id))->toBeNull();
});
