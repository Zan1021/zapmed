<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who charges a subscription: 'payfast_recurring' = PayFast bills it by itself (memberships, today's behaviour),
 * 'adhoc' = Zapmed charges the saved PayFast token on each due repeat (repeat orders). Only 'adhoc' is ever charged by us.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'charge_mode')) {
                $table->string('charge_mode', 20)->default('payfast_recurring')->after('repeat_cycle_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'charge_mode')) {
                $table->dropColumn('charge_mode');
            }
        });
    }
};