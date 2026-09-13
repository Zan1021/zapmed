<?php

namespace App\Enums;

/**
 * Finance revenue entry kind — verbatim parity with finance_revenue_kind_enum.
 *
 * cash_collected  — payment captured at gateway (PayFast 2xx)
 * recognised      — service period elapsed (earned)
 * pipeline        — expected but not yet earned
 * refund/chargeback/discount — NEGATIVE amounts (money out / not realised)
 */
enum RevenueKind: string
{
    case CashCollected = 'cash_collected';
    case Recognised = 'recognised';
    case Pipeline = 'pipeline';
    case Refund = 'refund';
    case Chargeback = 'chargeback';
    case Discount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::CashCollected => 'Cash collected',
            self::Recognised => 'Recognised',
            self::Pipeline => 'Pipeline',
            self::Refund => 'Refund',
            self::Chargeback => 'Chargeback',
            self::Discount => 'Discount',
        };
    }

    /** Kinds whose amounts are expected to be negative (money out / reversed). */
    public function isNegative(): bool
    {
        return in_array($this, [self::Refund, self::Chargeback, self::Discount], true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
