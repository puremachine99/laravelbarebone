<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The short-lived "material" (Material Expressive), "cyber" (Cyber Tech) and "ledger" (Ledger) themes were replaced by
 * "mono" (App\Enums\UserTheme). users.theme is cast to that enum, so a stored value that no longer exists would break
 * every page for that user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereIn('theme', ['material', 'cyber', 'ledger'])->update(['theme' => 'mono']);
    }

    /**
     * Reverse the migrations. The old themes are gone from the enum, so this only puts back the most recent one.
     */
    public function down(): void
    {
        DB::table('users')->where('theme', 'mono')->update(['theme' => 'ledger']);
    }
};
