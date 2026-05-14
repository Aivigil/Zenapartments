<?php

namespace Database\Seeders;

use App\Models\PlanTemplate;
use Illuminate\Database\Seeder;

class PlanTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        PlanTemplate::firstOrCreate(
            ['code' => 'PLAN-36M-25DP'],
            [
                'name' => '3-Year Plan — 25% down payment, 36 monthly installments',
                'description' => '25% down at booking, balance over 36 equal monthly installments. Possession charge at month 36.',
                'down_payment_bps' => 2500,
                'installment_count' => 36,
                'installment_frequency' => 'monthly',
                'milestone_charges' => [
                    ['code' => 'confirmation', 'label' => 'Confirmation charges', 'type' => 'percent', 'value' => 500, 'trigger' => 'event', 'sort_order' => 1],
                    ['code' => 'possession',   'label' => 'Possession charges',   'type' => 'percent', 'value' => 500, 'trigger' => 'event', 'sort_order' => 2],
                ],
                'late_fee_policy' => [
                    'kind' => 'flat',
                    'grace_days' => 7,
                    'value_minor' => 5000 * 100, // PKR 5,000
                    'cap_minor' => 50000 * 100,
                ],
                'early_payment_discount' => ['kind' => 'none'],
                'active' => true,
            ]
        );

        PlanTemplate::firstOrCreate(
            ['code' => 'PLAN-60M-20DP'],
            [
                'name' => '5-Year Plan — 20% down payment, 60 monthly installments',
                'description' => '20% down at booking, balance over 60 equal monthly installments.',
                'down_payment_bps' => 2000,
                'installment_count' => 60,
                'installment_frequency' => 'monthly',
                'milestone_charges' => [
                    ['code' => 'confirmation', 'label' => 'Confirmation charges', 'type' => 'percent', 'value' => 500, 'trigger' => 'event', 'sort_order' => 1],
                    ['code' => 'possession',   'label' => 'Possession charges',   'type' => 'percent', 'value' => 500, 'trigger' => 'event', 'sort_order' => 2],
                ],
                'late_fee_policy' => [
                    'kind' => 'flat',
                    'grace_days' => 7,
                    'value_minor' => 5000 * 100,
                    'cap_minor' => 50000 * 100,
                ],
                'active' => true,
            ]
        );

        PlanTemplate::firstOrCreate(
            ['code' => 'PLAN-CASH'],
            [
                'name' => 'Cash plan — full payment within 30 days',
                'description' => 'Single-payment plan with optional early-payment discount.',
                'down_payment_bps' => 10000,
                'installment_count' => 0,
                'installment_frequency' => 'monthly',
                'milestone_charges' => [],
                'late_fee_policy' => ['kind' => 'none'],
                'early_payment_discount' => ['kind' => 'percent', 'value_bps' => 500],
                'active' => true,
            ]
        );
    }
}
