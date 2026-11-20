<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Mcp\Actions\Records;

use Flexpik\FilamentStudio\Mcp\Exceptions\StudioNotFoundException;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\RecordInputRules;
use Illuminate\Support\Facades\Validator;

class CreateRecord
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input, ?int $tenantId): StudioRecord
    {
        $base = Validator::validate($input, [
            'collection_slug' => ['required', 'string'],
            'data' => ['required', 'array'],
            'locale' => ['nullable', 'string'],
        ]);

        $collection = StudioCollection::query()
            ->forTenant($tenantId)
            ->where('slug', $base['collection_slug'])
            ->first();

        if ($collection === null) {
            throw new StudioNotFoundException('collection', $base['collection_slug']);
        }

        $fields = EavQueryBuilder::getCachedFields($collection);
        $rules = RecordInputRules::for($collection, $fields, 'store');
        Validator::validate($input, $rules);

        return EavQueryBuilder::for($collection)
            ->locale($base['locale'] ?? null)
            ->create($base['data'] ?? []);
    }
}
