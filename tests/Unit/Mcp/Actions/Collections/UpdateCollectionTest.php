<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Mcp\Actions\Collections\UpdateCollection;
use Flexpik\FilamentStudio\Mcp\Exceptions\IntegrityException;
use Flexpik\FilamentStudio\Mcp\Exceptions\StudioNotFoundException;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->action = new UpdateCollection);

it('updates collection meta in place', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1, 'name' => 'Old']);

    $updated = ($this->action)('products', ['name' => 'New', 'icon' => 'heroicon-o-cube'], 1);

    expect($updated->name)->toBe('New')->and($updated->icon)->toBe('heroicon-o-cube');
});

it('throws StudioNotFoundException for unknown slug', function () {
    expect(fn () => ($this->action)('missing', ['name' => 'X'], 1))
        ->toThrow(StudioNotFoundException::class);
});

it('respects tenant scoping', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 2]);

    expect(fn () => ($this->action)('products', ['name' => 'X'], 1))
        ->toThrow(StudioNotFoundException::class);
});

it('rejects attempts to change slug', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1]);

    expect(fn () => ($this->action)('products', ['slug' => 'items'], 1))
        ->toThrow(ValidationException::class);
});

it('rejects renaming to a name already used in the same tenant', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1, 'name' => 'Products']);
    StudioCollection::factory()->create(['slug' => 'orders', 'tenant_id' => 1, 'name' => 'Orders']);

    expect(fn () => ($this->action)('orders', ['name' => 'Products'], 1))
        ->toThrow(IntegrityException::class);
});

it('allows renaming to a name used only in another tenant', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 2, 'name' => 'Products']);
    StudioCollection::factory()->create(['slug' => 'orders', 'tenant_id' => 1, 'name' => 'Orders']);

    $updated = ($this->action)('orders', ['name' => 'Products'], 1);

    expect($updated->name)->toBe('Products');
});

it('allows saving a collection with its own unchanged name', function () {
    StudioCollection::factory()->create(['slug' => 'orders', 'tenant_id' => 1, 'name' => 'Orders']);

    $updated = ($this->action)('orders', ['name' => 'Orders', 'icon' => 'heroicon-o-cube'], 1);

    expect($updated->icon)->toBe('heroicon-o-cube');
});

it('rejects renaming to a name used by another tenant-less collection', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => null, 'name' => 'Products']);
    StudioCollection::factory()->create(['slug' => 'orders', 'tenant_id' => null, 'name' => 'Orders']);

    expect(fn () => ($this->action)('orders', ['name' => 'Products'], null))
        ->toThrow(IntegrityException::class);
});
