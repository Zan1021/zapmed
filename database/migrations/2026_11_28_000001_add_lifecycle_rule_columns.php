<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Craig's build guide, section 3.2: pause and cancel record WHO did it (patient, operations or the system), and a
 * repeat order remembers its pack length so the first repeat can land on day (pack - 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_pause_events', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_pause_events', 'initiator')) {
                $table->string('initiator', 12)->default('operations')->after('paused_reason');   // patient | operations | system
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'cancelled_initiator')) {
                $table->string('cancelled_initiator', 12)->nullable()->after('cancellation_reason');
            }
            if (! Schema::hasColumn('subscriptions', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_initiator')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'repeat_cycle_days')) {
                $table->unsignedSmallInteger('repeat_cycle_days')->nullable()->after('completed_cycles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancelled_initiator', 'repeat_cycle_days'] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('subscription_pause_events', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_pause_events', 'initiator')) {
                $table->dropColumn('initiator');
            }
        });
    }
};
