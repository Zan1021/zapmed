<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the order state machine RULES as data (order_status_transitions + order_rxhub_event_map)
 * from config/orders.php. Idempotent (upsert) so it can run repeatedly. See specs/contro-rebuild §4.
 */
class OrderStatusTransitionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (config('orders.transitions', []) as [$from, $to, $trigger]) {
            DB::table('order_status_transitions')->updateOrInsert(
                ['from_status' => $from, 'to_status' => $to, 'trigger_type' => $trigger],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        foreach (config('orders.rxhub_event_map', []) as $code => $status) {
            DB::table('order_rxhub_event_map')->updateOrInsert(
                ['event_code' => $code],
                ['to_status' => $status, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }
}
