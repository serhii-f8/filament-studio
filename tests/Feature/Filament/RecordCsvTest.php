<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\RecordCsv;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->create(['name' => 'books', 'slug' => 'books', 'label' => 'Book', 'tenant_id' => null]);
    $make = fn (string $col, string $type, string $cast, int $sort, array $extra = []) => StudioField::factory()->create(array_merge([
        'collection_id' => $this->collection->id, 'column_name' => $col, 'label' => ucfirst($col),
        'field_type' => $type, 'eav_cast' => $cast, 'sort_order' => $sort,
    ], $extra));

    $make('title', 'text', 'text', 1, ['is_required' => true]);
    $make('pages', 'integer', 'integer', 2);
    $make('in_print', 'toggle', 'boolean', 3);
    $make('tags', 'tags', 'json', 4);
    $genre = $make('genre', 'select', 'text', 5, ['settings' => ['options_source' => 'static']]);
    StudioFieldOption::create(['field_id' => $genre->id, 'value' => 'scifi', 'label' => 'Sci-fi']);
    $make('secret', 'password', 'text', 6);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'books');
    request()->setRouteResolver(fn () => $route);
});

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $contents);

    return $path;
}

it('exports records with a header row, JSON lists, 1/0 booleans and no passwords', function () {
    $record = EavQueryBuilder::for($this->collection)->create([
        'title' => 'Dune', 'pages' => 412, 'in_print' => true, 'tags' => ['classic', 'desert'], 'genre' => 'scifi', 'secret' => 'hunter2',
    ]);

    $csv = RecordCsv::toCsvString($this->collection, [$record]);
    $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), array_filter(explode("\n", trim($csv))));

    expect($rows[0])->toBe(['uuid', 'title', 'pages', 'in_print', 'tags', 'genre'])
        ->and($rows[1])->toBe([$record->uuid, 'Dune', '412', '1', '["classic","desert"]', 'scifi'])
        ->and($csv)->not->toContain('hunter2');
});

it('defuses spreadsheet formulas on export and restores them on import', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['title' => '=HYPERLINK("http://evil")']);

    $csv = RecordCsv::toCsvString($this->collection, [$record]);

    expect($csv)->toContain("'=HYPERLINK");

    StudioRecord::query()->delete();
    RecordCsv::import($this->collection, csvFile($csv));

    $data = EavQueryBuilder::for($this->collection)->getRecordData(StudioRecord::sole());
    expect($data['title'])->toBe('=HYPERLINK("http://evil")');
});

it('round-trips an export through import', function () {
    EavQueryBuilder::for($this->collection)->create(['title' => 'Dune', 'pages' => 412, 'in_print' => true, 'tags' => ['classic'], 'genre' => 'scifi']);
    $csv = RecordCsv::toCsvString($this->collection, StudioRecord::all());

    $result = RecordCsv::import($this->collection, csvFile($csv));

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe([])
        ->and(StudioRecord::count())->toBe(2);

    $copy = EavQueryBuilder::for($this->collection)->getRecordData(StudioRecord::latest('id')->first());
    expect($copy)->toMatchArray(['title' => 'Dune', 'pages' => 412, 'in_print' => true, 'tags' => ['classic'], 'genre' => 'scifi']);
});

it('matches headers by label, accepts comma lists and yes/no, and reports bad rows without stopping', function () {
    $csv = "\xEF\xBB\xBFTitle,Pages,In_print,Tags,Genre,Unknown\n"
        ."Neuromancer,271,yes,\"cyber, punk\",scifi,x\n"
        .",10,no,,scifi,x\n"
        ."Bad genre,10,no,,romance,x\n";

    $result = RecordCsv::import($this->collection, csvFile($csv));

    expect($result['created'])->toBe(1)
        ->and(collect($result['failed'])->pluck('row')->all())->toBe([3, 4])
        ->and($result['failed'][0]['errors'][0])->toContain('Title')
        ->and($result['ignored_columns'])->toBe(['Unknown']);

    $data = EavQueryBuilder::for($this->collection)->getRecordData(StudioRecord::sole());
    expect($data)->toMatchArray(['title' => 'Neuromancer', 'pages' => 271, 'in_print' => true, 'tags' => ['cyber', 'punk']]);
});

it('imports from the list page and reports the result', function () {
    $file = UploadedFile::fake()->createWithContent('books.csv', "title,pages\nDune,412\nEmma,300\n");

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'books'])
        ->callAction('importCsv', ['file' => $file])
        ->assertHasNoActionErrors()
        ->assertNotified('Imported 2 Books');

    expect(StudioRecord::where('collection_id', $this->collection->id)->count())->toBe(2);
});

it('exports the listed records from the list page as a download', function () {
    EavQueryBuilder::for($this->collection)->create(['title' => 'Dune']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'books'])
        ->callAction('exportCsv')
        ->assertFileDownloaded();
});
