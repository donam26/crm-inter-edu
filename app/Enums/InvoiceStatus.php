<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Issued => 'Đã phát hành',
            self::PartiallyPaid => 'Thanh toán một phần',
            self::Paid => 'Đã thanh toán',
            self::Overdue => 'Quá hạn',
            self::Void => 'Đã huỷ',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Issued => 'primary',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Void => 'danger',
        };
    }

    /**
     * Hoá đơn còn trong vòng đời thu tiền (chưa Paid và chưa Void).
     */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::Issued,
            self::PartiallyPaid,
            self::Overdue,
        ], true);
    }

    /**
     * Đã chốt (đã thu đủ hoặc huỷ): không cho ghi nhận thêm payment.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Void], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
