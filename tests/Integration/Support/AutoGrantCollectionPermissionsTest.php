<?php

use Flexpik\FilamentStudio\Mcp\Actions\Collections\CreateCollection as McpCreateCollection;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Tests\SpatieUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function makeUserWithRole(string $role): SpatieUser
{
    $user = SpatieUser::forceCreate([
        'name' => 'Role User',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ]);

    $user->assignRole($role);

    return $user;
}

function canDoEverything(SpatieUser $user, StudioCollection $collection): bool
{
    $user = $user->fresh();

    foreach (['viewRecords', 'createRecord', 'updateRecord', 'deleteRecord'] as $ability) {
        if (! $user->can($ability, $collection)) {
            return false;
        }
    }

    return true;
}

it('grants a code-created collection to every role that can manage Studio fields', function () {
    Permission::firstOrCreate(['name' => 'studio.manageFields', 'guard_name' => 'web']);
    Role::create(['name' => 'studio_admin', 'guard_name' => 'web'])->givePermissionTo('studio.manageFields');
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $admin = makeUserWithRole('studio_admin');
    $editor = makeUserWithRole('editor');

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);

    expect(canDoEverything($admin, $collection))->toBeTrue()
        ->and($editor->fresh()->can('viewRecords', $collection))->toBeFalse();
});

it('grants a new collection to the roles listed in permissions.auto_grant_roles', function () {
    config()->set('filament-studio.permissions.auto_grant_roles', ['super_admin']);
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = makeUserWithRole('super_admin');

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);

    expect(canDoEverything($superAdmin, $collection))->toBeTrue();
});

it('ignores configured roles that do not exist instead of creating them', function () {
    config()->set('filament-studio.permissions.auto_grant_roles', ['ghost']);

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);

    expect($collection->exists)->toBeTrue()
        ->and(Role::where('name', 'ghost')->exists())->toBeFalse();
});

it('does not grant to field managers when auto_grant_to_field_managers is disabled', function () {
    config()->set('filament-studio.permissions.auto_grant_to_field_managers', false);
    Permission::firstOrCreate(['name' => 'studio.manageFields', 'guard_name' => 'web']);
    Role::create(['name' => 'studio_admin', 'guard_name' => 'web'])->givePermissionTo('studio.manageFields');

    $admin = makeUserWithRole('studio_admin');

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);

    expect($admin->fresh()->can('viewRecords', $collection))->toBeFalse();
});

it('skips roles whose guard differs from the collection permissions', function () {
    config()->set('filament-studio.permissions.auto_grant_roles', ['api_admin']);
    $role = Role::create(['name' => 'api_admin', 'guard_name' => 'api']);

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);

    expect($collection->exists)->toBeTrue()
        ->and($role->fresh()->permissions)->toHaveCount(0);
});

it('re-grants auto-grant roles after a slug rename wipes the old permissions', function () {
    config()->set('filament-studio.permissions.auto_grant_roles', ['super_admin']);
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = makeUserWithRole('super_admin');

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);
    $collection->update(['slug' => 'posts']);

    expect(canDoEverything($superAdmin, $collection->fresh()))->toBeTrue();
});

it('does not re-grant a permission an admin revoked when the collection is edited', function () {
    config()->set('filament-studio.permissions.auto_grant_roles', ['super_admin']);
    $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $collection = StudioCollection::factory()->create(['slug' => 'articles', 'name' => 'articles']);
    $role->revokePermissionTo('studio.collection.articles.deleteRecord');

    $collection->update(['label' => 'Renamed']);

    expect($role->fresh()->hasPermissionTo('studio.collection.articles.deleteRecord'))->toBeFalse();
});

it('grants an MCP-created collection to the acting user', function () {
    $user = $this->makeUserWith([]);
    $this->actingAs($user);

    $collection = (new McpCreateCollection)(['name' => 'Products'], tenantId: null);

    expect(canDoEverything($user, $collection))->toBeTrue();
});

it('creates an MCP collection without an authenticated user', function () {
    $collection = (new McpCreateCollection)(['name' => 'Products'], tenantId: null);

    expect($collection->exists)->toBeTrue();
});
