<?php

namespace Flexpik\FilamentStudio\Resources;

use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Flexpik\FilamentStudio\Filtering\FilterGroup;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages;
use Flexpik\FilamentStudio\Services\DynamicFiltersBuilder;
use Flexpik\FilamentStudio\Services\DynamicFormSchemaBuilder;
use Flexpik\FilamentStudio\Services\DynamicTableColumnsBuilder;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Flexpik\FilamentStudio\Services\RecordGlobalSearch;
use Flexpik\FilamentStudio\Services\RecordSortOrder;
use Flexpik\FilamentStudio\Support\RecordTitle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DynamicCollectionResource extends Resource
{
    protected static ?string $model = StudioRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'studio/{collection_slug}';

    protected static ?string $currentCollectionSlug = null;

    protected static ?StudioCollection $currentCollection = null;

    public static ?string $currentPageContext = null;

    /**
     * Resolve the current collection from the route's {collection_slug} parameter,
     * scoped to the current tenant when available.
     */
    public static function resolveCollection(?string $slug = null): StudioCollection
    {
        $slug ??= request()->route('collection_slug') ?? static::$currentCollectionSlug;

        // Return cached if same slug
        if (static::$currentCollection && static::$currentCollectionSlug === $slug) {
            return static::$currentCollection;
        }

        static::$currentCollectionSlug = $slug;
        static::$currentCollection = StudioCollection::query()
            ->forTenant(Filament::getTenant()?->getKey())
            ->where('slug', $slug)
            ->firstOrFail();

        return static::$currentCollection;
    }

    /**
     * Reset the cached collection (useful in tests).
     */
    public static function resetResolvedCollection(): void
    {
        static::$currentCollectionSlug = null;
        static::$currentCollection = null;
    }

    public static function canViewAny(): bool
    {
        $collection = static::resolveCollectionOrNull();
        if (! $collection) {
            return true;
        }

        return auth()->user()?->can('viewRecords', $collection) ?? false;
    }

    public static function canCreate(): bool
    {
        $collection = static::resolveCollectionOrNull();
        if (! $collection) {
            return false;
        }

        return auth()->user()?->can('createRecord', $collection) ?? false;
    }

    public static function canEdit($record): bool
    {
        $collection = static::resolveCollectionOrNull();
        if (! $collection) {
            return false;
        }

        return auth()->user()?->can('updateRecord', $collection) ?? false;
    }

    public static function canDelete($record): bool
    {
        $collection = static::resolveCollectionOrNull();
        if (! $collection) {
            return false;
        }

        return auth()->user()?->can('deleteRecord', $collection) ?? false;
    }

    /**
     * Resolve collection without throwing — returns null if not in a route context.
     */
    protected static function resolveCollectionOrNull(): ?StudioCollection
    {
        try {
            return static::resolveCollection();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getModelLabel(): string
    {
        return static::resolveCollectionOrNull()?->label ?? 'Record';
    }

    public static function getPluralModelLabel(): string
    {
        return static::resolveCollectionOrNull()?->label_plural ?? 'Records';
    }

    /**
     * Studio records take part in the panel's global search (one resource serves every
     * collection, so the search itself spans collections; see RecordGlobalSearch).
     */
    public static function canGloballySearch(): bool
    {
        return (bool) config('filament-studio.global_search.enabled', true) && auth()->check();
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        return app(RecordGlobalSearch::class)->search(
            $search,
            Filament::getTenant()?->getKey(),
            (int) config('filament-studio.global_search.limit_per_collection', 5),
        );
    }

    /**
     * The record's display title (collection display template, else its first text field),
     * used for page headings and breadcrumbs. Falls back to the collection's singular label.
     */
    public static function getRecordTitle(?Model $record): string
    {
        $collection = static::resolveCollectionOrNull();

        if ($record instanceof StudioRecord && $collection) {
            return RecordTitle::forRecord($collection, $record) ?? $collection->label;
        }

        return static::getModelLabel();
    }

    public static function getNavigationLabel(): string
    {
        return static::resolveCollectionOrNull()?->label_plural ?? 'Records';
    }

    public static function form(Schema $schema): Schema
    {
        $collection = static::resolveCollection();
        $formSchema = DynamicFormSchemaBuilder::build(
            $collection,
            static::$currentPageContext,
            auth()->user(),
        );

        return $schema->components($formSchema);
    }

    public static function table(Table $table): Table
    {
        $collection = static::resolveCollection();

        return $table
            ->query(
                EavQueryBuilder::for($collection)
                    ->tenant(Filament::getTenant()?->getKey())
                    ->locale(app(LocaleResolver::class)->resolve($collection))
                    ->toEloquentQuery()
            )
            ->modifyQueryUsing(function (Builder $query) use ($collection, $table) {
                $livewire = $table->getLivewire();

                if (property_exists($livewire, 'advancedFilterTree')) {
                    $tree = FilterGroup::fromArray($livewire->advancedFilterTree);

                    if (! $tree->isEmpty()) {
                        EavQueryBuilder::for($collection)
                            ->applyFilterTree($tree)
                            ->applyFilterToQuery($query);
                    }
                }

                return $query;
            })
            ->defaultSort(
                fn (Builder $query, string $direction): ?Builder => static::applyCollectionDefaultSort($collection, $query, $direction),
                $collection->sort_direction?->value ?? 'asc',
            )
            ->reorderable(
                RecordSortOrder::isEnabled($collection) ? RecordSortOrder::COLUMN : null,
                direction: $collection->sort_field === RecordSortOrder::COLUMN
                    ? ($collection->sort_direction?->value ?? 'asc')
                    : 'asc',
            )
            ->authorizeReorder(fn (): bool => static::canEdit(null))
            ->columns(DynamicTableColumnsBuilder::build($collection))
            ->filters([
                ...DynamicFiltersBuilder::build($collection),
                ...($collection->enable_soft_deletes ? [TrashedFilter::make()] : []),
            ])
            ->actions([
                ViewAction::make()
                    ->hidden(fn (StudioRecord $record): bool => $record->trashed()),
                EditAction::make()
                    ->visible(fn (StudioRecord $record): bool => ! $record->trashed() && static::canEdit(null)),
                DeleteAction::make()
                    ->using(fn (StudioRecord $record) => static::deleteRecord($collection, $record))
                    ->visible(fn (): bool => static::canDelete(null)),
                RestoreAction::make()
                    ->using(fn (StudioRecord $record) => static::restoreRecord($collection, $record))
                    ->authorize(fn (): bool => static::canDelete(null)),
                ForceDeleteAction::make()
                    ->using(fn (StudioRecord $record) => static::forceDeleteRecord($collection, $record))
                    ->authorize(fn (): bool => static::canDelete(null)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(fn (EloquentCollection $records) => $records->each(fn (StudioRecord $record) => static::deleteRecord($collection, $record)))
                        ->visible(fn (): bool => static::canDelete(null)),
                    RestoreBulkAction::make()
                        ->using(fn (EloquentCollection $records) => $records->filter->trashed()->each(fn (StudioRecord $record) => static::restoreRecord($collection, $record)))
                        ->visible(fn (): bool => $collection->enable_soft_deletes && static::canDelete(null)),
                    ForceDeleteBulkAction::make()
                        ->using(fn (EloquentCollection $records) => $records->filter->trashed()->each(fn (StudioRecord $record) => static::forceDeleteRecord($collection, $record)))
                        ->visible(fn (): bool => $collection->enable_soft_deletes && static::canDelete(null)),
                ]),
            ]);
    }

    public static function restoreRecord(StudioCollection $collection, StudioRecord $record): bool
    {
        EavQueryBuilder::for($collection)->restore($record->id);

        return true;
    }

    public static function forceDeleteRecord(StudioCollection $collection, StudioRecord $record): bool
    {
        EavQueryBuilder::for($collection)->forceDelete($record->id);

        return true;
    }

    /**
     * Delete the way the collection is configured: soft delete when enabled, otherwise remove the
     * record and its values. (The model always has SoftDeletes, so Filament's default would
     * soft delete even collections that have it turned off.)
     */
    public static function deleteRecord(StudioCollection $collection, StudioRecord $record): bool
    {
        EavQueryBuilder::for($collection)->delete($record->id);

        return true;
    }

    /**
     * Order the records table by the collection's configured sort field (an EAV field, or the
     * record's own created_at/updated_at timestamps when no field of that name exists).
     */
    protected static function applyCollectionDefaultSort(StudioCollection $collection, Builder $query, string $direction): ?Builder
    {
        $sortField = $collection->sort_field;

        if (blank($sortField)) {
            return null;
        }

        $field = EavQueryBuilder::getCachedFields($collection)->firstWhere('column_name', $sortField);
        $recordsTable = $query->getModel()->getTable();

        if ($field) {
            $valuesTable = (new StudioValue)->getTable();

            return $query->orderBy(
                StudioValue::query()
                    ->select($field->eav_cast->column())
                    ->whereColumn("{$valuesTable}.record_id", "{$recordsTable}.id")
                    ->where("{$valuesTable}.field_id", $field->id)
                    ->limit(1),
                $direction,
            );
        }

        if (in_array($sortField, ['created_at', 'updated_at'], true)) {
            return $query->orderBy("{$recordsTable}.{$sortField}", $direction);
        }

        return null;
    }

    /**
     * Resolve a record by UUID instead of integer ID.
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        $collection = static::resolveCollection();

        $query = StudioRecord::query()
            ->where(fn ($q) => $q->where('uuid', $key)->orWhere('id', $key))
            ->where('collection_id', $collection->id)
            ->forTenant(Filament::getTenant()?->getKey());

        if ($modifyQuery) {
            $modifyQuery($query);
        }

        return $query->first();
    }

    public static function getRecordRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        if (! isset($parameters['collection_slug'])) {
            $parameters['collection_slug'] = request()->route('collection_slug')
                ?? static::$currentCollectionSlug
                ?? '';
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectionRecords::route('/'),
            'create' => Pages\CreateCollectionRecord::route('/create'),
            'view' => Pages\ViewCollectionRecord::route('/{record}'),
            'edit' => Pages\EditCollectionRecord::route('/{record}/edit'),
        ];
    }
}
