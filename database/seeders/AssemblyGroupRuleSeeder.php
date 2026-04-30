<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AssemblyGroupRule;

class AssemblyGroupRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AssemblyGroupRule::create([
            'name' => 'Gurt → Baugruppe 2',
            'field' => 'product_name',
            'operator' => 'contains',
            'value' => 'gurt',
            'assembly_group' => 2,
            'sort_order' => 10,
        ]);
    }
}
