<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Mcp\Tools\Collections\CreateCollectionTool;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;

it('creates a collection via tool', function () {
    $key = StudioApiKey::factory()
        ->withPermissions(['_studio' => ['manage_collections']])
        ->forTenant(1)
        ->create();

    mcpCallTool($key, CreateCollectionTool::class, ['name' => 'Products'])
        ->assertSee('products');

    expect(StudioCollection::count())->toBe(1);
});

it('returns STUDIO_UNAUTHORIZED without manage_collections scope', function () {
    $key = StudioApiKey::factory()
        ->withPermissions(['_studio' => ['read_schema']])
        ->forTenant(1)
        ->create();

    mcpCallTool($key, CreateCollectionTool::class, ['name' => 'X'])
        ->assertSee('STUDIO_UNAUTHORIZED');
});

it('returns STUDIO_VALIDATION_FAILED for missing name', function () {
    $key = StudioApiKey::factory()
        ->withPermissions(['_studio' => ['manage_collections']])
        ->forTenant(1)
        ->create();

    mcpCallTool($key, CreateCollectionTool::class, [])
        ->assertSee('STUDIO_VALIDATION_FAILED');
});

it('derives singular and plural labels from a plural name', function () {
    $key = StudioApiKey::factory()
        ->withPermissions(['_studio' => ['manage_collections']])
        ->forTenant(1)
        ->create();

    mcpCallTool($key, CreateCollectionTool::class, ['name' => 'qa_tasks']);

    $collection = StudioCollection::sole();

    expect($collection->label)->toBe('Qa Task')
        ->and($collection->label_plural)->toBe('Qa Tasks');
});

it('accepts an explicit plural label', function () {
    $key = StudioApiKey::factory()
        ->withPermissions(['_studio' => ['manage_collections']])
        ->forTenant(1)
        ->create();

    mcpCallTool($key, CreateCollectionTool::class, ['name' => 'people', 'label' => 'Person', 'label_plural' => 'Folks']);

    expect(StudioCollection::sole()->label_plural)->toBe('Folks');
});
