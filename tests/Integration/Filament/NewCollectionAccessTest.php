<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\CreateCollection;
use Livewire\Livewire;

it('gives the creator record access to the data model they just created', function () {
    $creator = $this->makeUserWith(['ViewAny:StudioCollection', 'Create:StudioCollection']);
    $bystander = $this->makeUserWith(['ViewAny:StudioCollection', 'Create:StudioCollection']);
    $this->actingAs($creator);

    Livewire::test(CreateCollection::class)
        ->fillForm(['name' => 'articles', 'label' => 'Article', 'label_plural' => 'Articles'])
        ->call('create')
        ->assertHasNoFormErrors();

    $collection = StudioCollection::where('slug', 'articles')->firstOrFail();
    $creator = $creator->fresh();

    foreach (['viewRecords', 'createRecord', 'updateRecord', 'deleteRecord'] as $ability) {
        expect($creator->can($ability, $collection))->toBeTrue("creator should be able to {$ability}")
            ->and($bystander->fresh()->can($ability, $collection))->toBeFalse("bystander should not be able to {$ability}");
    }
});
