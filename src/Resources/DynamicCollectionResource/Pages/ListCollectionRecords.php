<?php

namespace Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Flexpik\FilamentStudio\Enums\FilterOperator;
use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Filtering\FilterGroup;
use Flexpik\FilamentStudio\Models\StudioSavedFilter;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns\HasPanelWidgets;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns\ResolvesCollection;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\RecordCsv;
use Flexpik\FilamentStudio\Services\RecordSortOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCollectionRecords extends ListRecords
{
    use HasPanelWidgets;
    use ResolvesCollection;

    protected static string $resource = DynamicCollectionResource::class;

    /** @var array{logic: string, rules: array} */
    public array $advancedFilterTree = ['logic' => 'and', 'rules' => []];

    public function mount(): void
    {
        $this->initializeCollectionSlug();

        // A singleton has no list: go straight to its record, or to the form that creates it.
        $collection = $this->getResolvedCollection();
        if ($collection->is_singleton) {
            $record = $collection->singletonRecord(Filament::getTenant()?->getKey() ?? $collection->tenant_id);

            $this->redirect($record
                ? DynamicCollectionResource::getUrl('edit', ['collection_slug' => $this->collectionSlug, 'record' => $record])
                : DynamicCollectionResource::getUrl('create', ['collection_slug' => $this->collectionSlug]));

            return;
        }

        parent::mount();
    }

    public function getTitle(): string
    {
        return $this->getResolvedCollection()->label_plural;
    }

    /**
     * Apply an advanced filter tree from the Alpine.js filter builder.
     *
     * @param  array{logic: string, rules: array}  $tree
     */
    public function applyFilterTree(array $tree): void
    {
        $this->advancedFilterTree = $tree;
        $this->resetTable();
    }

    /**
     * Apply the advanced filter tree to the table query.
     */
    protected function applyAdvancedFilter(Builder $query): Builder
    {
        $tree = FilterGroup::fromArray($this->advancedFilterTree);

        if ($tree->isEmpty()) {
            return $query;
        }

        $collection = $this->getResolvedCollection();

        EavQueryBuilder::for($collection)
            ->applyFilterTree($tree)
            ->applyFilterToQuery($query);

        return $query;
    }

    /**
     * Persist a drag-and-drop order. Filament's default writes the reorder column on the records
     * table, but `sort_order` lives in studio_values, so write the positions there instead.
     *
     * @param  array<int | string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        $table = $this->getTable();

        if (! $table->isReorderable()) {
            return;
        }

        $table->callBeforeReordering($order);

        RecordSortOrder::reorder(
            $this->getResolvedCollection(),
            $order,
            Filament::getTenant()?->getKey(),
            $table->getReorderDirection(),
        );

        $table->callAfterReordering($order);
    }

    protected function getHeaderActions(): array
    {
        $collection = $this->getResolvedCollection();

        return [
            Action::make('advancedFilter')
                ->label('Advanced Filter')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->badge(fn () => $this->getActiveFilterCount())
                ->slideOver()
                ->modalContent(function () use ($collection) {
                    $fields = EavQueryBuilder::getCachedFields($collection);

                    $fieldOptions = $fields
                        ->where('is_filterable', true)
                        ->mapWithKeys(fn ($f) => [$f->column_name => $f->label ?? $f->column_name])
                        ->all();

                    $operatorsByField = $fields
                        ->where('is_filterable', true)
                        ->mapWithKeys(fn ($f) => [$f->column_name => FilterOperator::labelsForCast($f->eav_cast)])
                        ->all();

                    return view('filament-studio::livewire.filter-builder-alpine', [
                        'tree' => $this->advancedFilterTree,
                        'fieldOptions' => $fieldOptions,
                        'operatorsByField' => $operatorsByField,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelAction(false),

            Action::make('saveFilter')
                ->label('Save Filter')
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->visible(fn () => ! empty($this->advancedFilterTree['rules']))
                ->form([
                    TextInput::make('name')
                        ->label('Filter name')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_shared')
                        ->label('Share with team')
                        ->helperText('When enabled, all team members can use this filter'),
                ])
                ->action(function (array $data) use ($collection) {
                    StudioSavedFilter::create([
                        'collection_id' => $collection->id,
                        'tenant_id' => Filament::getTenant()?->getKey(),
                        'created_by' => auth()->id(),
                        'name' => $data['name'],
                        'is_shared' => $data['is_shared'] ?? false,
                        'filter_tree' => $this->advancedFilterTree,
                    ]);

                    Notification::make()
                        ->title('Filter saved')
                        ->success()
                        ->send();
                }),

            Action::make('loadFilter')
                ->label('Saved Filters')
                ->icon('heroicon-o-bookmark-square')
                ->color('gray')
                ->form([
                    Select::make('filter_id')
                        ->label('Choose a saved filter')
                        ->options(function () use ($collection) {
                            return StudioSavedFilter::visibleTo(auth()->id() ?? 0)
                                ->forCollection($collection->id)
                                ->forTenant(Filament::getTenant()?->getKey())
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filter = StudioSavedFilter::findOrFail($data['filter_id']);
                    $this->advancedFilterTree = $filter->filter_tree;
                    $this->resetPage();
                }),

            ActionGroup::make([
                $this->exportCsvAction(),
                $this->importCsvAction(),
            ])
                ->label('CSV')
                ->icon('heroicon-m-arrows-up-down')
                ->color('gray')
                ->button(),

            Actions\CreateAction::make()
                ->label('Create '.$collection->label)
                ->visible(fn (): bool => DynamicCollectionResource::canCreate()),
        ];
    }

    /**
     * Download the records currently listed (search, filters and sort applied) as CSV.
     */
    public function exportCsvAction(): Action
    {
        return Action::make('exportCsv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (): StreamedResponse {
                $collection = $this->getResolvedCollection();
                $query = $this->getFilteredSortedTableQuery();
                $tenantId = Filament::getTenant()?->getKey();

                return response()->streamDownload(function () use ($collection, $query, $tenantId): void {
                    $handle = fopen('php://output', 'w');
                    RecordCsv::write($collection, $query->cursor(), $handle, $tenantId);
                    fclose($handle);
                }, $collection->slug.'-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /**
     * Create records from an uploaded CSV whose header row names the fields.
     */
    public function importCsvAction(): Action
    {
        return Action::make('importCsv')
            ->label('Import CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => DynamicCollectionResource::canCreate())
            ->modalDescription('The first row must name the fields (column names or labels). Each other row becomes a new record. Lists can be JSON or comma-separated; Yes/No columns take 1/0, true/false or yes/no. Tip: export first to get a template.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                $result = RecordCsv::import(
                    $this->getResolvedCollection(),
                    $file->getRealPath(),
                    Filament::getTenant()?->getKey(),
                    auth()->id(),
                );

                $failed = count($result['failed']);
                $lines = collect($result['failed'])
                    ->take(5)
                    ->map(fn (array $row): string => "Row {$row['row']}: ".implode(' ', $row['errors']));

                if ($result['ignored_columns'] !== []) {
                    $lines->push('Ignored columns: '.implode(', ', $result['ignored_columns']));
                }

                Notification::make()
                    ->title("Imported {$result['created']} ".str($this->getResolvedCollection()->label)->plural($result['created']).($failed ? ", {$failed} rows skipped" : ''))
                    ->body($lines->isEmpty() ? null : new HtmlString($lines->map(fn (string $line): string => e($line))->implode('<br>')))
                    ->status($failed ? 'warning' : 'success')
                    ->persistent($failed > 0)
                    ->send();

                $this->resetTable();
            });
    }

    protected function getHeaderWidgets(): array
    {
        return $this->buildWidgetsForPlacement(PanelPlacement::CollectionHeader);
    }

    protected function getFooterWidgets(): array
    {
        return $this->buildWidgetsForPlacement(PanelPlacement::CollectionFooter);
    }

    protected function getActiveFilterCount(): ?int
    {
        $count = count($this->advancedFilterTree['rules'] ?? []);

        return $count > 0 ? $count : null;
    }
}
