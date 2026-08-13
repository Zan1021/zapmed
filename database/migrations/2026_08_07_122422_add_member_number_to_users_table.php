<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('member_number', 10)->unique()->nullable()->after('email');
        });

        // Generate member numbers for existing users
        $users = DB::table('users')->orderBy('id')->get();
        foreach ($users as $index => $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['member_number' => 'ZM-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('member_number');
        });
    }
};
