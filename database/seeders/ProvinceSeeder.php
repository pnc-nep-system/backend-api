<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/geography_data.json'), true);

        foreach ($data['provinces'] as $name) {
            Province::firstOrCreate(['province_name' => $name]);
        }
    }
}
