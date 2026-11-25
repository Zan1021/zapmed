<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coupon code typed at checkout takes money off a consultation payment. The payment keeps what the patient would have paid, the code
 * and the discount, so the books always show where the money went. Medication payments never take a coupon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('description');
            $table->unsignedInteger('discount_cents')->default(0)->after('coupon_code');
            $table->unsignedInteger('original_amount')->nullable()->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount_cents', 'original_amount']);
        });
    }
};