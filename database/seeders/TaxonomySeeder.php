<?php
namespace Database\Seeders;
use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $b1 = ActivityCategory::firstOrCreate(
            ['code' => 'B1'],
            ['label' => 'Access and Equity', 'active' => true]
        );
        $b1_1 = ActivitySubcategory::firstOrCreate(
            ['code' => 'B1.1'],
            ['category_id' => $b1->id, 'label' => 'Financial support to learners', 'active' => true]
        );
        ActivityItem::firstOrCreate(
            ['code' => 'B1.1.1'],
            ['subcategory_id' => $b1_1->id, 'label' => 'Scholarships', 'active' => true, 'is_other' => false]
        );
        ActivityItem::firstOrCreate(
            ['code' => 'B1.1.99'],
            ['subcategory_id' => $b1_1->id, 'label' => 'Other (please specify)', 'active' => true, 'is_other' => true]
        );

        $b2 = ActivityCategory::firstOrCreate(
            ['code' => 'B2'],
            ['label' => 'Teacher Development', 'active' => true]
        );
        $b2_2 = ActivitySubcategory::firstOrCreate(
            ['code' => 'B2.2'],
            ['category_id' => $b2->id, 'label' => 'Continuing professional development', 'active' => true]
        );
        ActivityItem::firstOrCreate(
            ['code' => 'B2.2.01'],
            ['subcategory_id' => $b2_2->id, 'label' => 'Structured CPD programmes', 'active' => true, 'is_other' => false]
        );
        ActivityItem::firstOrCreate(
            ['code' => 'B2.2.99'],
            ['subcategory_id' => $b2_2->id, 'label' => 'Other (please specify)', 'active' => true, 'is_other' => true]
        );
    }
}
