<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case EWallet = 'e_wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Chuyển khoản',
            self::Cash => 'Tiền mặt',
            self::Card => 'Thẻ tín dụng',
            self::EWallet => 'Ví điện tử',
            self::Other => 'Khác',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
