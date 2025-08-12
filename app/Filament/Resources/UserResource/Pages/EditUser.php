<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\UserController;
use Filament\Forms\Components\Select; // Sicherstellen, dass dies korrekt importiert wird

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Form $form): Form
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

    public function save()
    {
        parent::save();
        // Rufe die Methode aus dem Controller auf oder direkt hier implementieren
        app(UserController::class)->assignRole(request(), $this->record->id);
    }
}
