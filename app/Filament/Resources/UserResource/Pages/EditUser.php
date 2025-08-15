<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
/* use Filament\Forms\Form;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\UserController;
use Filament\Forms\Components\Select; // Sicherstellen, dass dies korrekt importiert wird */

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('clearRoles')
                ->label('Alle Rollen entfernen')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->syncRoles([]);
                    $this->record->refresh();             // DB -> Model
                    $this->fillForm();                    // Model -> Formular (EditRecord-Hilfsmethode)
                    \Filament\Notifications\Notification::make()
                        ->title('Alle Rollen entfernt')
                        ->success()
                        ->send();
                }),

        ];
    }

    /* public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ... andere Formularfelder ...
                Select::make('role_id')
                    ->label('Rolle')
                    ->options(Role::all()->pluck('name', 'id'))
                    ->required(),
            ]);
    }

    // nach dem Speichern Rollen synchronisieren
    protected function afterSave():void
    {
        // Rollen aus dem Formularstate lesen (Select-Feld 'roles')
        $roles = $this->form->getState()['roles'] ?? [];
        $this->record->syncRoles($roles); // Spatie HasRoles
    } */
}
