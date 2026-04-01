<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
