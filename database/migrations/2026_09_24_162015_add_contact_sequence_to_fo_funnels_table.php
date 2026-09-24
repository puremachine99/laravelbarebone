<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->unsignedInteger('contact_sequence')->default(1)->after('fo_contact_id');
        });

        // Backfill: freeze each existing lead's occurrence number for its contact (1st, 2nd, ...)
        // in creation order, so numbering already assigned before this migration doesn't reshuffle
        // later just because an older lead for the same contact gets deleted.
        DB::table('fo_funnels')
            ->select('id', 'fo_contact_id')
            ->whereNotNull('fo_contact_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('fo_contact_id')
            ->each(function ($rows) {
                $sequence = 0;

                foreach ($rows as $row) {
                    $sequence++;

                    if ($sequence > 1) {
                        DB::table('fo_funnels')->where('id', $row->id)->update(['contact_sequence' => $sequence]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->dropColumn('contact_sequence');
        });
    }
};
