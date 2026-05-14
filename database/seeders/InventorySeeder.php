<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Models\Block;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitCategory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['code' => 'ZR-BAR'],
            [
                'name' => 'Zen Retreats — Barian',
                'location' => 'Barian, Murree',
                'city' => 'Murree',
                'country' => 'PK',
                'status' => 'active',
                'metadata' => [
                    'description' => 'Vacation chalets and apartments in Barian, Murree.',
                    'launch_year' => 2023,
                ],
            ]
        );

        $categories = [
            ['code' => 'CHL-1B', 'name' => 'One-Bedroom Chalet', 'kind' => 'chalet'],
            ['code' => 'CHL-2B', 'name' => 'Two-Bedroom Chalet', 'kind' => 'chalet'],
            ['code' => 'APT-STD', 'name' => 'Studio Apartment', 'kind' => 'apartment'],
            ['code' => 'APT-1B', 'name' => 'One-Bedroom Apartment', 'kind' => 'apartment'],
            ['code' => 'PLT-5M', 'name' => '5 Marla Plot', 'kind' => 'plot'],
        ];

        foreach ($categories as $cat) {
            UnitCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }

        $blockA = Block::firstOrCreate(
            ['project_id' => $project->id, 'code' => 'A'],
            ['name' => 'Block A', 'block_type' => 'block', 'sort_order' => 1]
        );

        $blockB = Block::firstOrCreate(
            ['project_id' => $project->id, 'code' => 'B'],
            ['name' => 'Block B', 'block_type' => 'block', 'sort_order' => 2]
        );

        $studioCat = UnitCategory::where('code', 'APT-STD')->first();
        $chalet1 = UnitCategory::where('code', 'CHL-1B')->first();
        $chalet2 = UnitCategory::where('code', 'CHL-2B')->first();

        // Block A — 8 studio apartments
        for ($i = 1; $i <= 8; $i++) {
            Unit::firstOrCreate(
                ['project_id' => $project->id, 'code' => "A-S{$i}"],
                [
                    'block_id' => $blockA->id,
                    'unit_category_id' => $studioCat->id,
                    'name' => "Studio A-S{$i}",
                    'size_value' => 450,
                    'size_unit' => 'sqft',
                    'base_price_minor' => 7_500_000 * 100, // PKR 7.5M
                    'currency' => 'PKR',
                    'status' => UnitStatus::Available->value,
                    'attributes' => ['floor' => ceil($i / 4), 'facing' => $i % 2 === 0 ? 'north' : 'south'],
                ]
            );
        }

        // Block B — 4× 1BR chalets + 2× 2BR chalets
        for ($i = 1; $i <= 4; $i++) {
            Unit::firstOrCreate(
                ['project_id' => $project->id, 'code' => "B-C1-{$i}"],
                [
                    'block_id' => $blockB->id,
                    'unit_category_id' => $chalet1->id,
                    'name' => "1BR Chalet B-{$i}",
                    'size_value' => 850,
                    'size_unit' => 'sqft',
                    'base_price_minor' => 14_500_000 * 100,
                    'currency' => 'PKR',
                    'status' => UnitStatus::Available->value,
                    'attributes' => ['view' => 'valley'],
                ]
            );
        }

        for ($i = 1; $i <= 2; $i++) {
            Unit::firstOrCreate(
                ['project_id' => $project->id, 'code' => "B-C2-{$i}"],
                [
                    'block_id' => $blockB->id,
                    'unit_category_id' => $chalet2->id,
                    'name' => "2BR Chalet B-{$i}",
                    'size_value' => 1250,
                    'size_unit' => 'sqft',
                    'base_price_minor' => 22_000_000 * 100,
                    'currency' => 'PKR',
                    'status' => UnitStatus::Available->value,
                    'attributes' => ['view' => 'valley', 'corner' => $i === 1],
                ]
            );
        }
    }
}
