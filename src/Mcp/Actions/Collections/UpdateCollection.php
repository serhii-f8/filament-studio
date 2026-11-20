<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Mcp\Actions\Collections;

use Flexpik\FilamentStudio\Mcp\Exceptions\IntegrityException;
use Flexpik\FilamentStudio\Mcp\Exceptions\StudioNotFoundException;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Illuminate\Support\Facades\Validator;

class UpdateCollection
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(string $slug, array $input, ?int $tenantId): StudioCollection
    {
        $data = Validator::validate($input, [
            'slug' => ['prohibited'],
            'name' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_hidden' => ['nullable', 'boolean'],
            'api_enabled' => ['nullable', 'boolean'],
            'enable_versioning' => ['nullable', 'boolean'],
            'enable_soft_deletes' => ['nullable', 'boolean'],
            'archive_field' => ['nullable', 'string'],
            'supported_locales' => ['nullable', 'array'],
        ]);

        $collection = StudioCollection::query()
            ->forTenant($tenantId)
            ->where('slug', $slug)
            ->first();

        if ($collection === null) {
            throw new StudioNotFoundException('collection', $slug);
        }

        // Names are unique per tenant — compare against the collection's own tenant (whereNull when
        // tenant-less, where the (tenant_id, name) unique index can't catch duplicates).
        if (isset($data['name']) && $data['name'] !== $collection->name) {
            $taken = StudioCollection::query()
                ->whereKeyNot($collection->getKey())
                ->where('name', $data['name'])
                ->when(
                    $collection->tenant_id === null,
                    fn ($query) => $query->whereNull('tenant_id'),
                    fn ($query) => $query->where('tenant_id', $collection->tenant_id),
                )
                ->exists();

            if ($taken) {
                throw IntegrityException::duplicate('name', $data['name']);
            }
        }

        $collection->fill(array_filter($data, fn ($v) => $v !== null))->save();

        return $collection->fresh(['fields']);
    }
}
