<?php

namespace App\Domains\Accounting\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Especes',
            self::MobileMoney => 'Mobile money',
            self::BankTransfer => 'Virement',
            self::Cheque => 'Cheque',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
