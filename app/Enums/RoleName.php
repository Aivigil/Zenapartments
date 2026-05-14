<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case FinanceAdmin = 'finance_admin';
    case FinanceOperator = 'finance_operator';
    case Sales = 'sales';
    case CustomerService = 'customer_service';
    case Client = 'client';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::FinanceAdmin => 'Finance Admin',
            self::FinanceOperator => 'Finance Operator',
            self::Sales => 'Sales',
            self::CustomerService => 'Customer Service',
            self::Client => 'Client',
            self::Auditor => 'Auditor',
        };
    }

    public static function staffRoles(): array
    {
        return [
            self::SuperAdmin->value,
            self::FinanceAdmin->value,
            self::FinanceOperator->value,
            self::Sales->value,
            self::CustomerService->value,
            self::Auditor->value,
        ];
    }
}
