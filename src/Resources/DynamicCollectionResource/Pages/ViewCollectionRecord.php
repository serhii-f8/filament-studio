<?php

namespace Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages;

use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioRecordVersion;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns\HasPanelWidgets;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns\ResolvesCollection;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns\RestoresVersions;
use Flexpik\FilamentStudio\Services\DynamicInfolistBuilder;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class ViewCollectionRecord extends ViewRecord
{
    use HasPanelWidgets;
    use ResolvesCollection;
    use RestoresVersions;

    protected static string $resource = DynamicCollectionResource::class;

    public function mount(int|string $record): void
    {
        $this->initializeCollectionSlug();
        DynamicCollectionResource::$currentPageContext = 'edit';

        parent::mount($record);
    }

    public function getTitle(): string
    {
        return DynamicCollectionResource::getRecordTitle($this->getRecord());
    }

    /**
     * Show the record read-only as an infolist (labels and formatted values) instead of a
     * disabled copy of the edit form.
     */
    public function infolist(Schema $schema): Schema
    {
        $collection = $this->getResolvedCollection();

        /** @var User|null $user */
        $user = auth()->user();

        return $schema
            ->columns(1)
            ->constantState($this->recordData())
            ->components(DynamicInfolistBuilder::build($collection, $this->recordData(), $user));
    }

    /** @var array<string, mixed>|null */
    protected ?array $cachedRecordData = null;

    /**
     * @return array<string, mixed>
     */
    protected function recordData(): array
    {
        return $this->cachedRecordData ??= EavQueryBuilder::for($this->getResolvedCollection())
            ->tenant(Filament::getTenant()?->getKey())
            ->locale(app(LocaleResolver::class)->resolve($this->getResolvedCollection()))
            ->getRecordData($this->getRecord());
    }

    /**
     * Resolve the record by UUID.
     */
    protected function resolveRecord(int|string $key): Model
    {
        $collection = $this->getResolvedCollection();

        return StudioRecord::query()
            ->where(fn ($q) => $q->where('uuid', $key)->orWhere('id', $key))
            ->where('collection_id', $collection->id)
            ->forTenant(Filament::getTenant()?->getKey())
            ->firstOrFail();
    }

    /**
     * Fill the form with EAV values for display.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $collection = $this->getResolvedCollection();

        /** @var StudioRecord $record */
        $record = $this->getRecord();

        $locale = app(LocaleResolver::class)->resolve($collection);

        $eavData = EavQueryBuilder::for($collection)
            ->tenant(Filament::getTenant()?->getKey())
            ->locale($locale)
            ->getRecordData($record);

        return array_merge($data, $eavData);
    }

    protected function getHeaderActions(): array
    {
        $collection = $this->getResolvedCollection();
        $resolver = app(LocaleResolver::class);

        $actions = [];

        // Locale switcher actions
        if ($resolver->isEnabled()
            && ! empty($collection->supported_locales)
            && $collection->fields()->where('is_translatable', true)->exists()
        ) {
            $activeLocale = $resolver->resolve($collection);

            foreach ($resolver->availableLocales($collection) as $locale) {
                $actions[] = Actions\Action::make("locale_{$locale}")
                    ->label(strtoupper($locale))
                    ->size('sm')
                    ->color($activeLocale === $locale ? 'primary' : 'gray')
                    ->action(function () use ($locale) {
                        session(['studio_locale' => $locale]);
                        $this->redirect(DynamicCollectionResource::getUrl('view', [
                            'collection_slug' => $this->collectionSlug,
                            'record' => $this->getRecord(),
                        ]));
                    });
            }
        }

        $actions[] = Actions\EditAction::make();

        if ($collection->enable_versioning) {
            $actions[] = Actions\Action::make('versionHistory')
                ->label('Version History')
                ->icon('heroicon-o-clock')
                ->slideOver()
                // Read-only history: no Submit button, only Close.
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                // The Restore buttons inside the slide-over mount this as a child action.
                ->registerModalActions([$this->restoreVersionAction()])
                ->modalContent(function () {
                    /** @var StudioRecord $record */
                    $record = $this->getRecord();
                    $collection = $this->getResolvedCollection();

                    $versions = StudioRecordVersion::with('creator')
                        ->where('record_id', $record->id)
                        ->latestFirst()
                        ->get();

                    $fields = $collection->fields()->get();

                    $fieldLabels = $fields->pluck('label', 'column_name')->all();
                    $fieldTypes = $fields->pluck('eav_cast', 'column_name')
                        ->map(fn ($cast) => $cast instanceof EavCast ? $cast->value : (string) $cast)
                        ->all();
                    $sensitiveFields = $fields->where('field_type', 'password')
                        ->pluck('column_name')
                        ->all();
                    $translatableFields = $fields->where('is_translatable', true)
                        ->pluck('column_name')
                        ->all();

                    return view('filament-studio::version-history', [
                        'versions' => $versions,
                        'fieldLabels' => $fieldLabels,
                        'fieldTypes' => $fieldTypes,
                        'sensitiveFields' => $sensitiveFields,
                        'translatableFields' => $translatableFields,
                        'showRestore' => true,
                    ]);
                });
        }

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        /** @var StudioRecord|null $record */
        $record = $this->record instanceof Model ? $this->record : null;

        return $this->buildWidgetsForPlacement(PanelPlacement::RecordHeader, $record?->uuid);
    }

    protected function getFooterWidgets(): array
    {
        /** @var StudioRecord|null $record */
        $record = $this->record instanceof Model ? $this->record : null;

        return $this->buildWidgetsForPlacement(PanelPlacement::RecordFooter, $record?->uuid);
    }
}
