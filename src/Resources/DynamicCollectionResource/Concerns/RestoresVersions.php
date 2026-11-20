<?php

namespace Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;

/**
 * The "Restore" buttons in the version history slide-over mount this action by name.
 *
 * Mounted while the history is open, Filament resolves it as a child of the Version History
 * action, so that action registers it as a modal action. It is also a page action method so it
 * can be mounted on its own. (It used to be a hidden header action, which Filament refuses to
 * mount, so Restore silently did nothing.)
 */
trait RestoresVersions
{
    public function restoreVersionAction(): Action
    {
        return Action::make('restoreVersion')
            ->label('Restore')
            ->icon('heroicon-o-arrow-uturn-left')
            ->requiresConfirmation()
            ->modalHeading('Restore this version?')
            ->modalDescription('This will overwrite the current record values with the selected version snapshot. The current state will be saved as a new version before restoring.')
            ->visible(fn (): bool => (bool) $this->getResolvedCollection()->enable_versioning)
            ->action(function (array $arguments): void {
                /** @var StudioRecord $record */
                $record = $this->getRecord();

                EavQueryBuilder::for($this->getResolvedCollection())
                    ->tenant($record->tenant_id)
                    ->restoreFromVersion($record->uuid, (int) $arguments['versionId']);

                // Reload the page so the form and history show the restored values.
                $this->redirect(DynamicCollectionResource::getUrl($this instanceof EditRecord ? 'edit' : 'view', [
                    'collection_slug' => $this->collectionSlug,
                    'record' => $record,
                ]));
            });
    }
}
