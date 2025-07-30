<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\ManufacturerAudit;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EditManufacturer extends EditRecord
{
    protected static string $resource = ManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Loggt Änderungen an sensiblen Feldern (API-URL, Benutzername, Passwort, Token).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\Manufacturer $manufacturer */
        $manufacturer = $this->record;
        $userId = Auth::id();
        $userName = Auth::user()?->name ?? 'Unbekannt';

        foreach (['api_url', 'api_username', 'api_password', 'api_token'] as $field) {
            $old = $manufacturer?->$field;
            $new = $data[$field] ?? null;

            $oldLog = in_array($field, ['api_password', 'api_token']) ? '***' : $old;
            $newLog = in_array($field, ['api_password', 'api_token']) ? '***' : $new;

            if ($old !== $new) {
                ManufacturerAudit::create([
                    'manufacturer_id' => $manufacturer->id,
                    'field' => $field,
                    'old_value' => $oldLog,
                    'new_value' => $newLog,
                    'changed_by' => $userId,
                ]);

                // Admin-Benachrichtigung
                Notification::make()
                    ->title('API-Zugangsdaten geändert')
                    ->body("Das Feld **{$field}** für Hersteller **{$manufacturer->manufacturer}** wurde von **{$userName}** geändert.")
                    ->warning()
                    ->sendToDatabase(\App\Models\User::role('Admin')->get()); // nur Admins
            }
        }

        return $data;
    }
}
