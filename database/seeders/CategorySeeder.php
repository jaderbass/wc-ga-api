<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Legt die Grundkategorien für Produkte an.
 */
class CategorySeeder extends Seeder
{
    /**
     * Führe den Seeder aus.
     */
    public function run(): void
    {
        $categories = [
            'Allgemein',
            'Sitz- oder Arbeitsgurte',
            'Seile',
            'Karabiner',
            'Schutzhelme',
        ];

        foreach ($categories as $name) {
            Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
