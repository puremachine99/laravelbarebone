<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "desk" theme became "espresso" (App\Enums\UserTheme). users.theme is cast to that enum, so a stored value that
 * no longer exists would break every page for that user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('theme', 'desk')->update(['theme' => 'espresso']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('theme', 'espresso')->update(['theme' => 'desk']);
    }
};
