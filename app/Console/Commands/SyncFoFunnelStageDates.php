<?php

namespace App\Console\Commands;

use App\Models\FoFunnel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-fo-funnel-stage-dates')]
#[Description('Backfill tgl_won/tgl_closing for existing FoFunnel records whose stage was set before those date columns existed, so Monthly Conversion/Achievement and Management FO reports (which filter by those dates) pick them up')]
class SyncFoFunnelStageDates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $wonCount = 0;

        FoFunnel::where('stage', 'won')->whereNull('tgl_won')->each(function (FoFunnel $funnel) use (&$wonCount) {
            $funnel->update(['tgl_won' => $funnel->updated_at->toDateString()]);
            $wonCount++;
        });

        $closingCount = 0;

        FoFunnel::whereIn('stage', FoFunnel::CLOSED_STAGES)->whereNull('tgl_closing')->each(function (FoFunnel $funnel) use (&$closingCount) {
            $funnel->update(['tgl_closing' => $funnel->updated_at->toDateString()]);
            $closingCount++;
        });

        $this->info("Backfilled tgl_won for {$wonCount} funnel(s) and tgl_closing for {$closingCount} funnel(s).");
    }
}
