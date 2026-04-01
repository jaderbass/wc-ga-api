<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Bearbeitungsseite für Kategorien.
 *
 * Diese Seite blendet die Delete-Action aus, wenn eine Kategorie
 * fachlich nicht gelöscht werden darf.
 *
 * Regeln:
 * - Die Standardkategorie "Allgemein" darf nicht gelöscht werden.
 * - Kategorien mit bestehender Produktzuordnung dürfen nicht gelöscht werden.
 */
class EditCategory extends EditRecord
{
    /**
     * Zugehörige Filament-Resource.
     *
     * @var class-string<CategoryResource>
     */
    protected static string $resource = CategoryResource::class;

    /**
     * Liefert die Header-Actions der Bearbeitungsseite.
     *
     * Die Delete-Action wird nur dann angezeigt, wenn das Löschen
     * der aktuellen Kategorie fachlich erlaubt ist.
     *
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->canDeleteRecord()) {
            $actions[] = Actions\DeleteAction::make();
        }

        return $actions;
    }

    /**
     * Prüft, ob die aktuelle Kategorie gelöscht werden darf.
     *
     * Nicht löschbar sind:
     * - die Kategorie "Allgemein"
     * - Kategorien, die bereits Produkten zugewiesen sind
     *
     * @return bool
     */
    protected function canDeleteRecord(): bool
    {
        /** @var Category $record */
        $record = $this->getRecord();

        if ($record->name === 'Allgemein') {
            return false;
        }

        return ! $record->products()->exists();
    }

    /**
     * Normalisiert und dedupliziert die Regeln vor dem Speichern.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = $this->normalizeRules($data['rules'] ?? []);

        return $data;
    }

    /**
     * Bereinigt Regel-Daten für das Formular.
     *
     * - trimmt Keywords
     * - normalisiert Leerzeichen
     * - wandelt in Kleinbuchstaben um
     * - entfernt leere und doppelte Keywords
     * - setzt die Sortierung anhand der Reihenfolge im Repeater
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRules(array $rules): array
    {
        $seen = [];
        $result = [];

        foreach ($rules as $rule) {
            $keyword = mb_strtolower(trim((string) ($rule['keyword'] ?? '')), 'UTF-8');
            $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

            if ($keyword === '' || isset($seen[$keyword])) {
                continue;
            }

            $seen[$keyword] = true;

            $result[] = [
                'keyword' => $keyword,
                'sort_order' => count($result),
            ];
        }

        return $result;
    }
}
