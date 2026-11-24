<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderTracking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Courier tracking on an order (Craig's guide 6.2). Operations record it; the patient sees the newest update on My Orders.
 *
 *  - Every update is a new row (history kept). The link is optional: with a url_template configured it is made from the number.
 *  - Only http(s) links are accepted (a link is shown to the patient).
 *  - The tracking is also written on the order's audit trail.
 */
class OrderTrackingService
{
    public function __construct(private readonly OrderControls $controls)
    {
    }

    public function record(User $ops, Order $order, string $trackingNumber, string $status, ?string $courier = null, ?string $url = null, ?string $pharmacyReference = null, ?string $trackedAt = null, string $source = 'manual'): OrderTracking
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $number = trim($trackingNumber);
        if ($number === '' || mb_strlen($number) > 60 || ! preg_match('/^[A-Za-z0-9\-_\/ ]+$/', $number)) {
            throw ValidationException::withMessages(['tracking_number' => 'Enter the tracking number (letters, numbers and dashes, up to 60).']);
        }
        if (! array_key_exists($status, config('operations.tracking.statuses'))) {
            throw ValidationException::withMessages(['status' => 'Choose one of the listed statuses.']);
        }
        $courier = trim((string) $courier) ?: config('operations.tracking.default_courier');
        if (mb_strlen($courier) > 60) {
            throw ValidationException::withMessages(['courier' => 'The courier name is too long.']);
        }

        $link = trim((string) $url);
        if ($link === '' && ($template = config('operations.tracking.url_template'))) {
            $link = str_replace('{number}', rawurlencode($number), $template);
        }
        if ($link !== '' && (mb_strlen($link) > 500 || ! filter_var($link, FILTER_VALIDATE_URL) || ! in_array(parse_url($link, PHP_URL_SCHEME), ['http', 'https'], true))) {
            throw ValidationException::withMessages(['url' => 'Enter a full web address that starts with https://.']);
        }

        $when = now();
        if ($trackedAt !== null && trim($trackedAt) !== '') {
            try {
                $when = \Carbon\CarbonImmutable::parse($trackedAt, config('doctor.time.timezone'))->utc();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['tracked_at' => 'Enter the date and time of the update.']);
            }
            if ($when->isFuture()) {
                throw ValidationException::withMessages(['tracked_at' => 'The update time cannot be in the future.']);
            }
        }

        $row = OrderTracking::create([
            'order_id' => $order->id,
            'courier' => $courier,
            'tracking_number' => $number,
            'status' => $status,
            'url' => $link !== '' ? $link : null,
            'pharmacy_reference' => $pharmacyReference ? mb_substr(trim($pharmacyReference), 0, 60) : ($order->pharmacy_script_ref ?: null),
            'source' => in_array($source, ['manual', 'dps'], true) ? $source : 'manual',
            'tracked_at' => $when,
            'created_by' => $ops->id,
        ]);

        $this->controls->audit($order, $ops, 'tracking', sprintf('Tracking %s: %s (%s).', $number, config("operations.tracking.statuses.{$status}"), $courier), ['tracking_id' => $row->id]);

        return $row;
    }

    /** What the patient and Operations see: the newest update. */
    public function latest(Order $order): ?OrderTracking
    {
        return OrderTracking::where('order_id', $order->id)->orderByDesc('tracked_at')->orderByDesc('id')->first();
    }
}
