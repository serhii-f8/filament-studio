<?php

use Filament\GlobalSearch\GlobalSearchResult;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->books = StudioCollection::factory()->create(['name' => 'books', 'slug' => 'books', 'label' => 'Book', 'label_plural' => 'Books', 'tenant_id' => null]);
    StudioField::factory()->create(['collection_id' => $this->books->id, 'column_name' => 'title', 'label' => 'Title', 'field_type' => 'text', 'eav_cast' => 'text', 'sort_order' => 1]);
    StudioField::factory()->create(['collection_id' => $this->books->id, 'column_name' => 'secret', 'label' => 'Secret', 'field_type' => 'password', 'eav_cast' => 'text', 'sort_order' => 2]);

    $this->authors = StudioCollection::factory()->create(['name' => 'authors', 'slug' => 'authors', 'label' => 'Author', 'label_plural' => 'Authors', 'tenant_id' => null]);
    StudioField::factory()->create(['collection_id' => $this->authors->id, 'column_name' => 'name', 'label' => 'Name', 'field_type' => 'text', 'eav_cast' => 'text', 'sort_order' => 1]);
});

it('takes part in global search', function () {
    expect(DynamicCollectionResource::canGloballySearch())->toBeTrue();

    config()->set('filament-studio.global_search.enabled', false);

    expect(DynamicCollectionResource::canGloballySearch())->toBeFalse();
});

it('finds records across collections by their text values, case-insensitively', function () {
    $book = EavQueryBuilder::for($this->books)->create(['title' => 'The Left Hand of Darkness']);
    EavQueryBuilder::for($this->books)->create(['title' => 'Dune']);
    EavQueryBuilder::for($this->authors)->create(['name' => 'Ursula Le Guin, darkness fan']);

    $results = DynamicCollectionResource::getGlobalSearchResults('DARKNESS');

    expect($results)->toHaveCount(2)
        ->and($results)->each->toBeInstanceOf(GlobalSearchResult::class)
        ->and($results->pluck('title')->all())->toContain('The Left Hand of Darkness', 'Ursula Le Guin, darkness fan')
        ->and($results->firstWhere('title', 'The Left Hand of Darkness')->details)->toBe(['Collection' => 'Books'])
        ->and($results->firstWhere('title', 'The Left Hand of Darkness')->url)->toContain('/studio/books/'.$book->uuid);
});

it('never matches password values or treats % as a wildcard', function () {
    EavQueryBuilder::for($this->books)->create(['title' => 'Plain', 'secret' => 'hunter2']);

    expect(DynamicCollectionResource::getGlobalSearchResults('hunter2'))->toBeEmpty()
        ->and(DynamicCollectionResource::getGlobalSearchResults('%'))->toBeEmpty();
});

it('skips collections the user may not view', function () {
    EavQueryBuilder::for($this->books)->create(['title' => 'Hidden book']);
    Gate::before(fn ($user, string $ability, array $args) => $ability === 'viewRecords' && ($args[0]->slug ?? null) === 'books' ? false : null);

    expect(DynamicCollectionResource::getGlobalSearchResults('Hidden'))->toBeEmpty();
});
