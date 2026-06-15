<?php

namespace Database\Seeders;

use App\Models\PetzlCategoryMapping;
use Illuminate\Database\Seeder;

class PetzlCategoryMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = database_path('seeders/data/petzl-category-mappings.json');

        $rows = json_decode(
            file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($rows as $row) {
            PetzlCategoryMapping::updateOrCreate(
                [
                    'source_category' => $row['source_category'],
                    'source_subcategory' => $row['source_subcategory'],
                ],
                $row
            );
        }
    }
}
