<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\SystemLock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Betriebsschalter der Datenbank, nur für geschützte Konten sichtbar.
 */
class SystemSwitch extends Page
{
    protected static ?string $slug = 'betrieb';

    protected static ?string $navigationIcon = 'heroicon-o-power';

    protected static ?string $navigationLabel = 'Datenbank an/aus';

    protected static ?string $title = 'Datenbank an/aus';

    protected static ?int $navigationSort = 1000;

    protected static string $view = 'filament.pages.system-switch';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isProtectedAccount();
    }

    /**
     * Für alle anderen "nicht gefunden" statt "Zugriff verweigert",
     * damit die Seite nicht als vorhanden auffällt.
     */
    public function mountCanAuthorizeAccess(): void
    {
        abort_unless(static::canAccess(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'locked' => SystemLock::isLocked(),
            'info' => SystemLock::info(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lock')
                ->label('Datenbank abschalten')
                ->icon('heroicon-o-power')
                ->color('danger')
                ->visible(fn (): bool => ! SystemLock::isLocked())
                ->requiresConfirmation()
                ->modalHeading('Datenbank abschalten?')
                ->modalDescription('Danach hat nur noch dieses Konto Zugang. Webhooks und Schnittstellen antworten nicht mehr, Hintergrundaufgaben pausieren. Angemeldete Benutzer verlieren sofort den Zugriff.')
                ->modalSubmitActionLabel('Abschalten')
                ->action(function (): void {
                    SystemLock::lock(auth()->user());

                    Notification::make()->title('Datenbank abgeschaltet')->warning()->send();
                }),

            Action::make('unlock')
                ->label('Datenbank einschalten')
                ->icon('heroicon-o-power')
                ->color('success')
                ->visible(fn (): bool => SystemLock::isLocked())
                ->requiresConfirmation()
                ->modalHeading('Datenbank einschalten?')
                ->modalDescription('Alle Benutzer mit Zugang können sich wieder anmelden, Webhooks und Hintergrundaufgaben laufen wieder.')
                ->modalSubmitActionLabel('Einschalten')
                ->action(function (): void {
                    SystemLock::unlock(auth()->user());

                    Notification::make()->title('Datenbank eingeschaltet')->success()->send();
                }),
        ];
    }
}
