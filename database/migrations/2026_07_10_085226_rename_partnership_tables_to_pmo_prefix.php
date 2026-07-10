<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    private const TABLE_MAP = [
        'contacts' => 'pmo_contacts',
        'contact_positions' => 'pmo_contact_positions',
        'opds' => 'pmo_opds',
        'opd_urusan' => 'pmo_opd_urusan',
        'urusans' => 'pmo_urusans',
        'divisions' => 'pmo_divisions',
        'service_centers' => 'pmo_service_centers',
        'product_categories' => 'pmo_product_categories',
        'outputs' => 'pmo_outputs',
        'pipeline_profiles' => 'pmo_pipeline_profiles',
        'pipeline_stages' => 'pmo_pipeline_stages',
        'cards' => 'pmo_cards',
        'employee_regions' => 'pmo_employee_regions',
    ];

    public function up(): void
    {
        foreach (self::TABLE_MAP as $from => $to) {
            Schema::rename($from, $to);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLE_MAP as $from => $to) {
            Schema::rename($to, $from);
        }
    }
};
