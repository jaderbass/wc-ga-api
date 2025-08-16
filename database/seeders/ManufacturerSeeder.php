<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;

class ManufacturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $manufacturers = [
            [
                'manufacturer' => 'Aliens',
                'manufacturercountry' => 'FR',
                'import_type' => 'csv',
            ],
            [
                'manufacturer' => 'Kask',
                'manufacturercountry' => 'FR',
                'import_type' => 'csv',
            ],
            [
                'manufacturer' => 'Petzl',
                'manufacturercountry' => 'IT',
                'import_type' => 'csv',
            ],
            [
                'manufacturer' => 'Kratos Safety',
                'manufacturercountry' => 'DE',
                'import_type' => 'xml',
            ],
            [
                'manufacturer' => 'Singing Rock',
                'manufacturercountry' => 'DE',
                'api_url' => 'https://b2b.singingrock.com/feed/products/',
                'api_user' => 'Marketing',
                'api_password' => 'eyJpdiI6Im9RL0hnd2F0QUxiZXhrdG1JMHRka3c9PSIsInZhbHVlIjoib0oxOFRwLzJBWTVEZWhXcnBpQkVkMElNRFBwTU1BazkxL2ZXc01Tb3RWcz0iLCJtYWMiOiJlYjRmNjkyMTZlZWM4ZDRhZjgxZGM3YWM2MDA3ODJhMGU5MGYyZTMwNmEzZDk4MmFiNzdlOGFkM2I0MGY4ODJlIiwidGFnIjoiIn0=',
                'import_type' => 'api',
            ],
        ];

        foreach ($manufacturers as $data) {
            Manufacturer::updateOrCreate(['manufacturer' => $data['manufacturer']], $data);
        }
    }
}
