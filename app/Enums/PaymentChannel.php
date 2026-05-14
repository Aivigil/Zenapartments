<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case OnlineGateway = 'online_gateway';
    case ForeignWire = 'foreign_wire';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::OnlineGateway => 'Online Gateway',
            self::ForeignWire => 'Foreign Wire',
        };
    }
}
