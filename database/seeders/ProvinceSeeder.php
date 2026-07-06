<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    // Verify this list against the NEP Programme Mapping Framework v1.2 —
    // this is a standard 25-province list but the framework may use its own
    // spelling/ordering conventions.
    public function run(): void
    {
        $provinces = [
            'Banteay Meanchey', 'Battambang', 'Kampong Cham', 'Kampong Chhnang',
            'Kampong Speu', 'Kampong Thom', 'Kampot', 'Kandal', 'Kep', 'Koh Kong',
            'Kratié', 'Mondulkiri', 'Oddar Meanchey', 'Pailin', 'Phnom Penh',
            'Preah Sihanouk', 'Preah Vihear', 'Prey Veng', 'Pursat', 'Ratanakiri',
            'Siem Reap', 'Stung Treng', 'Svay Rieng', 'Takéo', 'Tboung Khmum',
        ];

        foreach ($provinces as $name) {
            Province::firstOrCreate(['province_name' => $name]);
        }
    }
}
