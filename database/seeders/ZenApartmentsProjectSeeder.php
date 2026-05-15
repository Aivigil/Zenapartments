<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Models\Block;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Zen Apartments" project (Karachi-based development, separate
 * from the existing "Zen Retreats — Barian" we use for demos).
 *
 * This is the project that holds the 37 real customers from OneDrive/SharePoint.
 * Units are NOT seeded here — the `migrate:snapshot` command creates them as
 * it reads the master receivables CSV. We just stand up the project shell + a
 * "Main" block + ensure unit categories exist for the three apartment types.
 *
 * Idempotent — safe to re-run.
 */
class ZenApartmentsProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::firstOrCreate(
            ['code' => 'ZA-KHI'],
            [
                'name' => 'Zen Apartments — Karachi',
                'location' => 'Karachi, Pakistan',
                'city' => 'Karachi',
                'country' => 'PK',
                'status' => 'active',
                'metadata' => [
                    'description' => 'Zen Apartments residential project. Units imported from OneDrive master receivables file.',
                    'company' => 'ZEN (PRIVATE) LIMITED',
                    'bank_account_title' => 'Zen Private Limited',
                    'bank_iban' => 'PK80SAMB0000002000997528',
                    'bank_name' => 'Samba Bank',
                    'bank_branch' => 'F-7-Islamabad',
                ],
            ]
        );

        $project = Project::where('code', 'ZA-KHI')->first();
        Block::firstOrCreate(
            ['project_id' => $project->id, 'code' => 'MAIN'],
            ['name' => 'Main Block', 'block_type' => 'block', 'sort_order' => 1]
        );

        // Ensure the three category codes the snapshot importer maps to exist.
        // We use codes that match what we'll see in the master file's Category column.
        $categories = [
            ['code' => 'ZA-STU',  'name' => 'Studio Apartment',  'kind' => 'studio'],
            ['code' => 'ZA-1BR',  'name' => '1-Bed Apartment',   'kind' => 'apartment'],
            ['code' => 'ZA-2BR',  'name' => '2-Bed Apartment',   'kind' => 'apartment'],
        ];

        foreach ($categories as $cat) {
            UnitCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }
}
