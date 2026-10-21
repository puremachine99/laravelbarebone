<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The pastel "candy" theme became "cinnamoroll" (App\Enums\UserTheme). users.theme is cast to that enum, so a stored
 * value that no longer exists would break every page for that user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('theme', 'candy')->update(['theme' => 'cinnamoroll']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('theme', 'cinnamoroll')->update(['theme' => 'candy']);
    }
};
