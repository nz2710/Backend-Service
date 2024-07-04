<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

class UpdatePartnerBonus extends Command
{
    protected $signature = 'partners:update-bonus';
    protected $description = 'Update partner bonus from partner_monthly_stats';

    public function handle()
    {
        $this->info('Updating partner bonuses...');

        Partner::query()
            ->select('partners.id')
            ->selectRaw('COALESCE(SUM(partner_monthly_stats.bonus), 0) as total_bonus')
            ->leftJoin('partner_monthly_stats', 'partners.id', '=', 'partner_monthly_stats.partner_id')
            ->groupBy('partners.id')
            ->chunk(100, function ($partners) {
                foreach ($partners as $partner) {
                    DB::table('partners')
                        ->where('id', $partner->id)
                        ->update(['bonus' => $partner->total_bonus]);
                }
                $this->info('Processed ' . $partner->id);
            });

        $this->info('Partner bonuses updated successfully.');
    }
}
