<?php

namespace App\Filament\Resources\ProductVariationResource\Pages;

use App\Filament\Resources\ProductVariationResource;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\ProductNaming\VariationDisplayNameResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Bearbeitungsseite für Produktvarianten.
 *
 * Die Seite wird nicht über die Hauptnavigation geöffnet, sondern aus der
 * Varianten-Relation eines Produktes heraus. Sie stellt einen fokussierten
 * Bearbeitungsworkflow für einzelne Varianten bereit und bietet einen
 * Rücksprung zum zugehörigen Parent-Produkt.
 */
class EditProductVariation extends EditRecord
{
    protected static string $resource = ProductVariationResource::class;

    /**
     * Definiert die Header-Aktionen der Varianten-Bearbeitungsseite.
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToProduct')
                ->label('Zurück zum Produkt')
                ->icon('heroicon-m-arrow-left')
                ->url(fn() => ProductResource::getUrl('edit', [
                    'record' => $this->record->product_id,
                ])),

            Actions\Action::make('refreshName')
                ->label('Namen neu berechnen')
                ->icon('heroicon-m-arrow-path')
                ->action(function () {
                    $resolvedName = app(VariationDisplayNameResolver::class)->resolve($this->record);

                    $this->record->forceFill([
                        'slug' => Str::slug($resolvedName),
                    ])->save();

                    $this->record->refresh();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Variantenname und Slug neu berechnet')
                        ->send();
                }),
        ];
    }

    /**
     * Überschreibt die Breadcrumbs, damit die technische Varianten-Indexseite
     * nicht als anklickbarer Zwischenschritt angezeigt wird.
     *
     * @return array<string|int, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.products.edit', [
                'record' => $this->record->product_id,
            ]) => 'Produkt',

            'Variante bearbeiten',
        ];
    }
}
