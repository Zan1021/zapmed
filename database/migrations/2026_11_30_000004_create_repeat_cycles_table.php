<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repeat cycles Operations can set (Craig's guide 6.4): how long a pack lasts, when the FIRST repeat falls (a 28 day pack
 * first repeats on day 23, a five day delivery buffer) and whether it is a "same-day" cycle (no buffer, for scripts such as a
 * four-tablet monthly pack). The seeded rows are the examples in the guide. The 21, 24, 45 and 60 day first-repeat days follow
 * the same five day buffer: PROVISIONAL until Craig confirms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repeat_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('description', 120);
            $table->unsignedSmallInteger('days');               // how long a pack lasts
            $table->unsignedSmallInteger('first_repeat_days');  // days after the prescription until the first repeat
            $table->boolean('same_day')->default(false);        // repeats on the same day each cycle, no delivery buffer
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['days', 'is_enabled']);
        });

        $now = now();
        foreach ([
            ['28 day pack (weight-loss pens)', 28, 23, false],
            ['30 day pack', 30, 25, false],
            ['21 day pack', 21, 16, false],
            ['24 day pack', 24, 19, false],
            ['45 day pack', 45, 40, false],
            ['60 day pack', 60, 55, false],
            ['Same-day monthly script', 30, 30, true],
        ] as [$description, $days, $first, $same]) {
            DB::table('repeat_cycles')->insert([
                'description' => $description, 'days' => $days, 'first_repeat_days' => $first, 'same_day' => $same,
                'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repeat_cycles');
    }
};
