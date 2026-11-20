<?php

namespace Flexpik\FilamentStudio\Api;

use Flexpik\FilamentStudio\Api\Resources\RecordCollection;
use Flexpik\FilamentStudio\Api\Resources\RecordResource;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Flexpik\FilamentStudio\Services\RecordInputRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StudioApiController extends Controller
{
    protected function resolveCollection(Request $request, string $slug): StudioCollection
    {
        $query = StudioCollection::query()
            ->where('slug', $slug)
            ->where('api_enabled', true);

        $apiKey = $this->getApiKey($request);

        if ($apiKey && $apiKey->tenant_id !== null) {
            $query->forTenant($apiKey->tenant_id);
        }

        return $query->firstOrFail();
    }

    protected function getApiKey(Request $request): ?StudioApiKey
    {
        return $request->attributes->get('studio_api_key');
    }

    protected function resolveLocale(Request $request, StudioCollection $collection): string
    {
        return app(LocaleResolver::class)->resolve($collection);
    }

    protected function isAllLocales(Request $request): bool
    {
        return filter_var($request->query('all_locales', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function index(Request $request, string $collection_slug): RecordCollection
    {
        $collection = $this->resolveCollection($request, $collection_slug);
        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage < 1 ? 25 : min($perPage, 100);

        $paginator = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $resourceCollection = new RecordCollection($paginator);
        $resourceCollection->setCollection($collection);

        return $resourceCollection;
    }

    public function show(Request $request, string $collection_slug, string $uuid): JsonResponse|RecordResource
    {
        $collection = $this->resolveCollection($request, $collection_slug);

        $record = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $locale = $this->resolveLocale($request, $collection);

        if ($this->isAllLocales($request)) {
            $data = EavQueryBuilder::for($collection)->getAllLocaleData($record);
            $data = RecordResource::presentData($collection, $data);

            return response()->json([
                'data' => [
                    'uuid' => $record->uuid,
                    'data' => $data,
                    'created_by' => $record->created_by,
                    'updated_by' => $record->updated_by,
                    'created_at' => $record->created_at?->toIso8601String(),
                    'updated_at' => $record->updated_at?->toIso8601String(),
                ],
            ]);
        }

        $result = EavQueryBuilder::for($collection)
            ->locale($locale)
            ->getRecordDataWithMeta($record);

        return response()->json([
            'data' => [
                'uuid' => $record->uuid,
                'data' => RecordResource::presentData($collection, $result['data']),
                'created_by' => $record->created_by,
                'updated_by' => $record->updated_by,
                'created_at' => $record->created_at?->toIso8601String(),
                'updated_at' => $record->updated_at?->toIso8601String(),
            ],
            '_meta' => [
                'locale' => $locale,
                'fallbacks' => $result['fallbacks'],
            ],
        ]);
    }

    public function store(Request $request, string $collection_slug): JsonResponse
    {
        $collection = $this->resolveCollection($request, $collection_slug);
        $fields = EavQueryBuilder::getCachedFields($collection);
        $locale = $this->resolveLocale($request, $collection);

        $rules = RecordInputRules::for($collection, $fields, 'store');
        $validated = $request->validate($rules);

        $data = $validated['data'] ?? [];

        $record = EavQueryBuilder::for($collection)
            ->locale($locale)
            ->create($data);

        $recordData = EavQueryBuilder::for($collection)
            ->locale($locale)
            ->getRecordData($record);

        return response()->json([
            'data' => [
                'uuid' => $record->uuid,
                'data' => RecordResource::presentData($collection, $recordData),
                'created_by' => $record->created_by,
                'updated_by' => $record->updated_by,
                'created_at' => $record->created_at?->toIso8601String(),
                'updated_at' => $record->updated_at?->toIso8601String(),
            ],
            '_meta' => ['locale' => $locale],
        ], 201);
    }

    public function update(Request $request, string $collection_slug, string $uuid): JsonResponse
    {
        $collection = $this->resolveCollection($request, $collection_slug);

        $record = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $fields = EavQueryBuilder::getCachedFields($collection);
        $locale = $this->resolveLocale($request, $collection);
        $rules = RecordInputRules::for($collection, $fields, 'update', $record->id);
        $validated = $request->validate($rules);

        $data = $validated['data'] ?? [];

        EavQueryBuilder::for($collection)->locale($locale)->update($record->id, $data);

        $record = $record->fresh();

        $recordData = EavQueryBuilder::for($collection)
            ->locale($locale)
            ->getRecordData($record);

        return response()->json([
            'data' => [
                'uuid' => $record->uuid,
                'data' => RecordResource::presentData($collection, $recordData),
                'created_by' => $record->created_by,
                'updated_by' => $record->updated_by,
                'created_at' => $record->created_at?->toIso8601String(),
                'updated_at' => $record->updated_at?->toIso8601String(),
            ],
            '_meta' => ['locale' => $locale],
        ]);
    }

    public function destroy(Request $request, string $collection_slug, string $uuid): JsonResponse
    {
        $collection = $this->resolveCollection($request, $collection_slug);

        $record = StudioRecord::query()
            ->where('collection_id', $collection->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        EavQueryBuilder::for($collection)->delete($record->id);

        return response()->json(null, 204);
    }
}
