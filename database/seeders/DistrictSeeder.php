<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        // Districts list keyed by province_name
        $districts = [
            'Banteay Meanchey' => [
                'Mongkol Borei',
                'Phnom Srok',
                'Preah Netr Preah',
                'Ou Chrov',
                'Serei Saophoan',
                'Thma Puok',
                'Svay Chek',
                'Malai',
                'Poipet',
            ],

            'Battambang' => [
                'Banan',
                'Battambang',
                'Bavel',
                'Kamrieng',
                'Koas Krala',
                'Moung Ruessei',
                'Ratanak Mondol',
                'Rotonak Mondol',
                'Sampov Loun',
                'Sangkae',
                'Thma Koul',
                'Ek Phnom',
                'Samlot',
                'Phnum Proek',
            ],

            'Kampong Cham' => [
                'Batheay',
                'Chamkar Leu',
                'Cheung Prey',
                'Kampong Cham',
                'Kampong Siem',
                'Kang Meas',
                'Kaoh Soutin',
                'Prey Chhor',
                'Srey Santhor',
                'Stung Trang',
            ],

            'Kampong Chhnang' => [
                'Baribour',
                'Chol Kiri',
                'Kampong Chhnang',
                'Kampong Leaeng',
                'Kampong Tralach',
                'Rolea B\'ier',
                'Sameakki Mean Chey',
                'Tuek Phos',
            ],

            'Kampong Speu' => [
                'Basedth',
                'Chbar Mon',
                'Kong Pisei',
                'Aoral',
                'Odongk',
                'Phnum Sruoch',
                'Samraong Tong',
                'Thpong',
            ],

            'Kampong Thom' => [
                'Baray',
                'Kampong Svay',
                'Stoung',
                'Prasat Balangk',
                'Prasat Sambour',
                'Sandan',
                'Santuk',
                'Stung Sen',
            ],

            'Kampot' => [
                'Angkor Chey',
                'Banteay Meas',
                'Chhuk',
                'Dang Tong',
                'Kampong Trach',
                'Tuek Chhou',
                'Kampot',
                'Bokor',
            ],

            'Kandal' => [
                'Angk Snuol',
                'Kandal Stueng',
                'Kaoh Thom',
                'Khsach Kandal',
                'Lvea Aem',
                'Mukh Kampul',
                'Ponhea Lueu',
                'Saang',
                'Ta Khmau',
            ],

            'Kep' => [
                'Damnak Chang\'aeur',
                'Kep',
            ],

            'Koh Kong' => [
                'Botum Sakor',
                'Kiri Sakor',
                'Koh Kong',
                'Smach Mean Chey',
                'Mondul Seima',
                'Srae Ambel',
                'Thma Bang',
            ],

            'Kratie' => [
                'Chhloung',
                'Kratie',
                'Preaek Prasab',
                'Sambour',
                'Snuol',
            ],

            'Mondulkiri' => [
                'Kaev Seima',
                'Kaoh Nheaek',
                'Ou Reang',
                'Pech Chreada',
                'Sen Monorom',
            ],

            'Oddar Meanchey' => [
                'Anlong Veng',
                'Banteay Ampil',
                'Chong Kal',
                'Samraong',
                'Trapeang Prasat',
            ],

            'Pailin' => [
                'Pailin',
                'Sala Krau',
            ],

            'Phnom Penh' => [
                'Chamkar Mon',
                'Daun Penh',
                'Prampir Meakkakra',
                'Tuol Kouk',
                'Dangkao',
                'Mean Chey',
                'Russey Keo',
                'Sen Sok',
                'Pou Senchey',
                'Chbar Ampov',
                'Chroy Changvar',
                'Prek Pnov',
                'Boeng Keng Kang',
                'Kamboul',
            ],

            'Preah Vihear' => [
                'Chey Saen',
                'Chhaeb',
                'Choam Ksant',
                'Kulen',
                'Rovieng',
                'Sangkum Thmei',
                'Tbaeng Mean Chey',
            ],

            'Preah Sihanouk' => [
                'Prey Nob',
                'Sihanoukville',
                'Stueng Hav',
                'Kampong Seila',
                'Kaoh Rong',
            ],

            'Prey Veng' => [
                'Ba Phnum',
                'Kamchay Mear',
                'Kampong Leav',
                'Kanhchriech',
                'Mesang',
                'Peam Chor',
                'Peam Ro',
                'Preah Sdach',
                'Prey Veng',
                'Pur Rieng',
                'Svay Antor',
            ],

            'Pursat' => [
                'Bakan',
                'Kandieng',
                'Krakor',
                'Phnum Kravanh',
                'Pursat',
                'Talou Sen Chey',
                'Veal Veng',
            ],

            'Ratanakiri' => [
                'Andoung Meas',
                'Banlung',
                'Bar Kaev',
                'Koun Mom',
                'Lumphat',
                'Ou Chum',
                'Ou Ya Dav',
                'Ta Veaeng',
                'Ta Veaeng Leu',
                'Veun Sai',
            ],


            'Siem Reap' => [
                'Angkor Chum',
                'Angkor Thom',
                'Banteay Srei',
                'Chi Kraeng',
                'Kralanh',
                'Puok',
                'Prasat Bakong',
                'Siem Reap',
                'Soutr Nikom',
                'Srei Snam',
                'Svay Leu',
                'Varin',
            ],

            'Stung Treng' => [
                'Sesan',
                'Siem Bouk',
                'Siem Pang',
                'Stung Treng',
                'Thala Barivat',
            ],

            'Svay Rieng' => [
                'Bavet',
                'Chantrea',
                'Kampong Rou',
                'Romeas Haek',
                'Rumduol',
                'Svay Chrum',
                'Svay Rieng',
                'Svay Theab',
            ],

            'Takeo' => [
                'Angkor Borei',
                'Bati',
                'Borei Cholsar',
                'Doun Kaev',
                'Kiri Vong',
                'Kaoh Andaet',
                'Prey Kabbas',
                'Samraong',
                'Tram Kak',
                'Treang',
            ],

            'Tboung Khmum' => [
                'Dambae',
                'Krouch Chhmar',
                'Memot',
                'Ou Reang Ov',
                'Ponhea Kraek',
                'Suong',
                'Tboung Khmum',
            ],
        ];

        $provinces = Province::pluck('id', 'province_name');
        foreach ($districts as $provinceName => $districtNames) {
            $provinceId = $provinces[$provinceName] ?? null;
            // Skip if province doesn't exist
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
            // Insert districts and ignore duplicates
            DB::table('districts')->insertOrIgnore($rows);
        }
    }
}
