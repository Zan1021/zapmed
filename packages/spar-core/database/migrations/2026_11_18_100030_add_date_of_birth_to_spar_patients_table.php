<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date of birth from the SPAR member extract. Stored encrypted (PHI), as a
 * Y-m-d string, so it needs a text column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('spar_patients', 'date_of_birth')) {
            Schema::table('spar_patients', function (Blueprint $table) {
                $table->text('date_of_birth')->nullable()->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('spar_patients', 'date_of_birth')) {
            Schema::table('spar_patients', function (Blueprint $table) {
                $table->dropColumn('date_of_birth');
            });
        }
    }
};
