<?php

use Filament\Facades\Filament;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\CreateCollection;
use Illuminate\Foundation\Auth\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]);
    actingAs($this->user);
});

function fakeTenant(int $id): User
{
    $tenant = new User;
    $tenant->forceFill(['id' => $id]);
    $tenant->exists = true;

    return $tenant;
}

function createViaWizard(string $name): Testable
{
    return Livewire::test(CreateCollection::class)
        ->fillForm(['name' => $name, 'label' => 'Article', 'label_plural' => 'Articles'])
        ->call('create');
}

it('allows a name that only exists in another tenant (no current tenant)', function () {
    StudioCollection::factory()->create(['name' => 'articles', 'slug' => 'articles', 'tenant_id' => 5]);

    createViaWizard('articles')->assertHasNoFormErrors();

    expect(StudioCollection::where('name', 'articles')->whereNull('tenant_id')->exists())->toBeTrue();
});

it('rejects a name that already exists without a tenant when there is no current tenant', function () {
    StudioCollection::factory()->create(['name' => 'articles', 'slug' => 'articles', 'tenant_id' => null]);

    createViaWizard('articles')->assertHasFormErrors(['name' => 'unique']);
});

it('allows a name used by another tenant when a tenant is active, and stores the current tenant', function () {
    StudioCollection::factory()->create(['name' => 'articles', 'slug' => 'articles', 'tenant_id' => 5]);
    Filament::setTenant(fakeTenant(7), isQuiet: true);

    createViaWizard('articles')->assertHasNoFormErrors();

    expect(StudioCollection::where('name', 'articles')->where('tenant_id', 7)->exists())->toBeTrue();
});

it('rejects a name already used in the current tenant', function () {
    StudioCollection::factory()->create(['name' => 'articles', 'slug' => 'articles', 'tenant_id' => 7]);
    Filament::setTenant(fakeTenant(7), isQuiet: true);

    createViaWizard('articles')->assertHasFormErrors(['name' => 'unique']);
});

it('rejects a name whose generated slug is already taken in the current tenant', function () {
    // e.g. created via MCP with a different name but the slug the wizard would generate
    StudioCollection::factory()->create(['name' => 'Articles', 'slug' => 'articles', 'tenant_id' => null]);

    createViaWizard('articles')->assertHasFormErrors(['name']);

    expect(StudioCollection::where('slug', 'articles')->count())->toBe(1);
});

it('allows a generated slug that is only taken in another tenant', function () {
    StudioCollection::factory()->create(['name' => 'Articles', 'slug' => 'articles', 'tenant_id' => 5]);

    createViaWizard('articles')->assertHasNoFormErrors();
});
