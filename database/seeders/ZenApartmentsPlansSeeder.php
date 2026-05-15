<?php

namespace Database\Seeders;

use App\Models\PlanTemplate;
use Illuminate\Database\Seeder;

/**
 * Plan templates for the real Zen Apartments customer base.
 *
 * Derived from survey of 6 representative statements:
 *   - Behram Khan F-401 (1-Bed):    30% DP + 48 monthly + 15% possession
 *   - Hira Azhar S-213 (Studio):    30% DP + 12 quarterly + 20% possession
 *   - Sana Fawad A-501 (2-Bed):     30% DP + 11 quarterly + ~30% possession
 *   - Farrukh Maqbool F-502 (2-Bed): 30% DP + 11 quarterly + ~30% possession
 *   - Mian Mubashar G-08 (Studio):   plan revised mid-flight
 *   - Asad Ali A-311 (1-Bed):        cash plan — paid in lump sums
 *
 * These templates only model the FORWARD schedule that's generated from
 * cutover-date onward. Past schedule rows aren't reconstructed — the
 * `opening_balance` adjustment per booking calibrates outstanding to the
 * spreadsheet number.
 */
class ZenApartmentsPlansSeeder extends Seeder
{
    public function run(): void
    {
        // 1-Bed plan: monthly installments, 4-year horizon, 15% possession
        PlanTemplate::updateOrCreate(
            ['code' => 'ZA-1BR-MONTHLY-48'],
            [
                'name' => '1-Bed Apartment — 30% down + 48 monthly + 15% possession',
                'description' => 'Standard 1-bedroom plan used across most ZA 1-bed apartments.',
                'down_payment_bps' => 3000,
                'installment_count' => 48,
                'installment_frequency' => 'monthly',
                'milestone_charges' => [
                    ['code' => 'possession', 'label' => 'Possession charges', 'type' => 'percent', 'value' => 1500, 'trigger' => 'event', 'sort_order' => 1],
                ],
                'late_fee_policy' => ['kind' => 'none'],
                'early_payment_discount' => ['kind' => 'none'],
                'active' => true,
            ]
        );

        // Studio plan: quarterly, 3-year, 20% possession
        PlanTemplate::updateOrCreate(
            ['code' => 'ZA-STU-QUARTERLY-12'],
            [
                'name' => 'Studio Apartment — 30% down + 12 quarterly + 20% possession',
                'description' => 'Standard studio plan.',
                'down_payment_bps' => 3000,
                'installment_count' => 12,
                'installment_frequency' => 'quarterly',
                'milestone_charges' => [
                    ['code' => 'possession', 'label' => 'Possession charges', 'type' => 'percent', 'value' => 2000, 'trigger' => 'event', 'sort_order' => 1],
                ],
                'late_fee_policy' => ['kind' => 'none'],
                'early_payment_discount' => ['kind' => 'none'],
                'active' => true,
            ]
        );

        // 2-Bed plan: quarterly, ~3-year, 30% possession
        PlanTemplate::updateOrCreate(
            ['code' => 'ZA-2BR-QUARTERLY-11'],
            [
                'name' => '2-Bed Apartment — 30% down + 11 quarterly + ~30% possession',
                'description' => 'Standard 2-bedroom plan.',
                'down_payment_bps' => 3000,
                'installment_count' => 11,
                'installment_frequency' => 'quarterly',
                'milestone_charges' => [
                    ['code' => 'possession', 'label' => 'Possession charges', 'type' => 'percent', 'value' => 3000, 'trigger' => 'event', 'sort_order' => 1],
                ],
                'late_fee_policy' => ['kind' => 'none'],
                'early_payment_discount' => ['kind' => 'none'],
                'active' => true,
            ]
        );

        // Cash plan: full payment, no installments, used for customers like Asad Ali
        PlanTemplate::updateOrCreate(
            ['code' => 'ZA-CASH'],
            [
                'name' => 'Cash plan — full payment (no installments)',
                'description' => 'Used for cash-plan customers whose schedules aren\'t recreated; their balance is set via opening adjustment.',
                'down_payment_bps' => 10000,
                'installment_count' => 0,
                'installment_frequency' => 'monthly',
                'milestone_charges' => [],
                'late_fee_policy' => ['kind' => 'none'],
                'early_payment_discount' => ['kind' => 'none'],
                'active' => true,
            ]
        );
    }
}
