<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action): void {
                    $record = $this->getRecord();

                    if ($record->name === 'Allgemein') {
                        Notification::make()
                            ->title('Kategorie kann nicht gelöscht werden')
                            ->body('Die Standardkategorie "Allgemein" darf nicht gelöscht werden.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($record->products()->exists()) {
                        Notification::make()
                            ->title('Kategorie kann nicht gelöscht werden')
                            ->body('Diese Kategorie ist Produkten zugewiesen und kann daher nicht gelöscht werden.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = $this->normalizeRules($data['rules'] ?? []);

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRules(array $rules): array
    {
        $seen = [];
        $result = [];

        foreach ($rules as $index => $rule) {
            $keyword = mb_strtolower(trim((string) ($rule['keyword'] ?? '')), 'UTF-8');
            $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

            if ($keyword === '' || isset($seen[$keyword])) {
                continue;
            }

            $seen[$keyword] = true;

            $result[] = [
                'keyword' => $keyword,
                'sort_order' => isset($rule['sort_order']) ? (int) $rule['sort_order'] : $index,
            ];
        }

        return $result;
    }
}
