<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "glass" (Liquid Glass) theme was replaced by "organic" (App\Enums\UserTheme). users.theme is cast to that enum,
 * so a stored value that no longer exists would break every page for that user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('theme', 'glass')->update(['theme' => 'organic']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('theme', 'organic')->update(['theme' => 'glass']);
    }
};
