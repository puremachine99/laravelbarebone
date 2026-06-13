<?php

namespace App\Console\Commands;

use App\Actions\PartnershipContactTransformer;
use App\Models\Contact;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('app:sync-partnership-contacts')]
#[Description('Sync and sanitize contacts from the external Partnership App database')]
class SyncPartnershipContacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PartnershipContactTransformer $transformer)
    {
        $connection = 'mysql_partnership';

        // Check if database connection is configured
        if (! config("database.connections.{$connection}")) {
            $this->error("Database connection '{$connection}' is not configured in config/database.php");

            return Command::FAILURE;
        }

        $this->info('Checking connection and tables...');

        try {
            // Test connection
            DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            $this->error('Failed to connect to external database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Detect table name dynamically (kontaks, kontak, or contacts)
        $table = 'kontaks';
        try {
            if (! Schema::connection($connection)->hasTable($table)) {
                if (Schema::connection($connection)->hasTable('kontak')) {
                    $table = 'kontak';
                } elseif (Schema::connection($connection)->hasTable('contacts')) {
                    $table = 'contacts';
                } else {
                    $this->error("Could not find contacts table (tried 'kontaks', 'kontak', 'contacts') in external database.");

                    return Command::FAILURE;
                }
            }
        } catch (\Exception $e) {
            // Fallback to query directly if Schema check fails due to driver/permissions
            $table = 'kontak';
        }

        $this->info("Extracting data from table '{$table}'...");

        try {
            $query = DB::connection($connection)->table($table)
                ->leftJoin('users', 'users.id', '=', $table.'.kontak_pic')
                ->select($table.'.*', 'users.nama as kontak_pic_name', 'users.ukw as kontak_pic_ukw');
            $total = $query->count();
            $this->info("Found {$total} contacts to process.");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $successCount = 0;
            $failedCount = 0;

            $query->orderBy($table.'.id')->chunk(100, function ($rows) use ($transformer, $bar, &$successCount, &$failedCount) {
                foreach ($rows as $row) {
                    try {
                        $transformed = $transformer->transform($row);

                        Contact::updateOrCreate(
                            ['partnership_contact_id' => $row->id],
                            $transformed
                        );

                        // Sync PIC Employee's Region/Pivot
                        $picEmployeeId = $transformed['pic_employee_id'] ?? null;
                        $ukw = $row->kontak_pic_ukw ?? null;

                        if ($picEmployeeId && $ukw) {
                            $regionExists = DB::table('regions')->where('id', $ukw)->exists();
                            if ($regionExists) {
                                // Update/insert into employee_regions pivot table
                                DB::table('employee_regions')->updateOrInsert(
                                    ['employee_id' => $picEmployeeId, 'region_id' => $ukw],
                                    []
                                );

                                // Also update employees.region_id directly to keep it in sync
                                DB::table('employees')
                                    ->where('id', $picEmployeeId)
                                    ->update(['region_id' => $ukw]);
                            }
                        }

                        $successCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        // Log error to laravel.log but keep processing other rows
                        logger()->error("ETL Sync Error for external contact ID {$row->id}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("Sync completed! Success: {$successCount}, Failed/Skipped: {$failedCount}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('An error occurred during ETL sync: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
