<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use App\Models\ManufacturerAudit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateManufacturer extends CreateRecord
{
    protected static string $resource = ManufacturerResource::class;

    /**
     * Loggt initiale Werte für sensible Felder (API-URL, Benutzername, Passwort, Token).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $userId = Auth::id();

        foreach (['api_url', 'api_username', 'api_password', 'api_token'] as $field) {
            $new = $data[$field] ?? null;
            $newLog = in_array($field, ['api_password', 'api_token']) ? '***' : $new;

            if ($new !== null) {
                ManufacturerAudit::create([
                    'manufacturer_id' => null, // ID noch nicht bekannt – setzen wir im AfterCreate-Hook
                    'field' => $field,
                    'old_value' => null,
                    'new_value' => $newLog,
                    'changed_by' => $userId,
                ]);
            }
        }

        return $data;
    }

    /**
     * Setzt die manufacturer_id für die Audit-Einträge nach dem Speichern.
     */
    protected function afterCreate(): void
    {
        $audits = ManufacturerAudit::whereNull('manufacturer_id')->get();
        foreach ($audits as $audit) {
            $audit->update(['manufacturer_id' => $this->record->id]);
        }

        Notification::make()
            ->title('Neuer Hersteller mit API-Zugangsdaten erstellt')
            ->body("Hersteller **{$this->record->manufacturer}** wurde von **" . (Auth::user()?->name ?? 'Unbekannt') . "** angelegt.")
            ->success()
            ->sendToDatabase(\App\Models\User::role('Admin')->get());
    }
}
