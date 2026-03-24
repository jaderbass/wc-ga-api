<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\Action::make('rebuildName')
                ->label('Produktnamen neu generieren')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    /** @var \App\Models\Product $record */
                    $record = $this->record;

                    /** @var \App\Models\Product $record */
                    $updater = app(\App\Services\ProductNaming\ProductNameUpdater::class);

                    $updater->update($record);

                    $this->record->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('Produktnamen neu generiert')
                        ->success()
                        ->send();
                }),
        ];
    }
}
