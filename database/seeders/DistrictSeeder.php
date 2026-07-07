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
            'Banteay Meanchey' => ['Mongkol Borey', 'Phnom Srok', 'Preah Netr Preah', 'Ou Chrov', 'Serei Saophoan', 'Thma Puok', 'Svay Chek', 'Malai', 'Poipet'],
            'Battambang' => ['Banan', 'Battambang', 'Bavel', 'Kamrieng', 'Koas Krala', 'Moung Ruessei', 'Rukh Kiri', 'Sampov Loun', 'Sangkae', 'Thma Koul', 'Ek Phnom'],
            'Kampong Cham' => ['Baray', 'Chamkar Leu', 'Chhlor', 'Damrey Reul', 'Kaoh Soutin', 'Kampong Cham', 'Kampong Siem', 'Kang Meas', 'Koh Keng', 'Koh Samui', 'Kratie Leu', 'Memot', 'Preah Sdach', 'Proek Ksach', 'Srei Santhor', 'Stung Trang'],
            'Kampong Chhnang' => ['Baset', 'Chol Kiri', 'Kompong Chhnang', 'Kro Sahong', 'Sangsai', 'Sompov Riey'],
            'Kampong Speu' => ['Ang Snuol', 'Aoral', 'Chbar Mon', 'Dampnou', 'Kong Pisey', 'Samroong'],
            'Kampong Thom' => ['Baray', 'Chhlong', 'Kampong Thom', 'Kravanh', 'Prasat', 'Santuk', 'Stoung'],
            'Kandal' => ['Ang Snuol', 'Kandal', 'Kaoh Thom', 'Khsach Kandal', 'Leuk Daem', 'Lvea Em', 'Mukh Kampul', 'Ponhea Leu', 'Samrong', 'Ta Khmau'],
            'Kep' => ['Kep'],
            'Koh Kong' => ['Andaung Meas', 'Botum Sakor', 'Chumnik', 'Kiri Sakor', 'Koh Kong', 'Mondul Seima', 'Sre Ambel', 'Thma Banteay'],
            'Kratié' => ['Chhlong', 'Kratie', 'Prek Prasab', 'Sambor', 'Snuol'],
            'Mondulkiri' => ['Kaoh Nheaem', 'Keo Seima', 'Mondulkiri', 'Pichreada', 'Senchey'],
            'Oddar Meanchey' => ['Anlong Veng', 'Banteay Ampil', 'Chong Kal', 'Oddar Meanchey', 'Samraong'],
            'Pailin' => ['Pailin', 'Sala Krau'],
            'Phnom Penh' => ['Chamkarmon', 'Daun Penh', 'Dangkao', 'Meanchey', 'Por Sen Chey', 'Russei Keo', 'Sen Sok', 'Siem Reap', 'Toul Kork', 'Tumnup Tek'],
            'Preah Sihanouk' => ['Kampong Seila', 'Kep', 'Preah Sihanouk', 'Stung Hav'],
            'Preah Vihear' => ['Anlong Veng', 'Chhep', 'Kulen', 'Rovieng', 'Tbeng'],
            'Prey Veng' => ['Ang Snuol', 'Chhlor', 'Koah Thum', 'Mesang', 'Nachan', 'Peam Chhlong', 'Prey Veng', 'Rovieng'],
            'Pursat' => ['Bakan', 'Kandieng', 'Pursat', 'Sampov Trey'],
            'Ratanakiri' => ['Andoung Meas', 'Ban Lung', 'Kaoh Nheaem', 'Lumphat', 'O Yadav', 'Snuol'],
            'Siem Reap' => ['Angkor', 'Banteay Srei', 'Chhlong', 'Floating (Kompong Khleang)', 'Kravanh', 'Puok', 'Rolous', 'Siem Reap', 'Stung Trang'],
            'Stung Treng' => ['Kracheh', 'Sesan', 'Stung Treng', 'Thala Borivat'],
            'Svay Rieng' => ['Chantrea', 'Kbal Kaoh', 'Nimit', 'Peam Chhlong', 'Svay Chrum', 'Svay Rieng'],
            'Takéo' => ['Angkor Borei', 'Bati', 'Chhuk', 'Kaoh Andaet', 'Kirivong', 'Prey Kabbas', 'Samraong', 'Takeo'],
            'Tboung Khmum' => ['Chon Thnaot', 'Kroem Samlanh', 'Memot', 'Ou Reang', 'Tboung Khmum'],
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

