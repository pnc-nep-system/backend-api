<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/geography_data.json'), true);
        $provinces = Province::pluck('id', 'province_name');

        foreach ($data['districts'] as $provinceName => $districtNames) {
            $provinceId = $provinces[$provinceName] ?? null;
            if (!$provinceId) {
                continue;
            }
            $rows = [];
            foreach ($districtNames as $districtName) {
                $rows[] = [
                    'province_id' => $provinceId,
                    'name' => $districtName,
                ];
            }
            DB::table('districts')->insertOrIgnore($rows);
        }
    }
}
