<?php

namespace App\Console\Commands;

use App\Http\Controllers\StakingController;
use Illuminate\Console\Command;

class DistributeStakingRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staking:rewards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribute staking rewards';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ctl = app(StakingController::class);
        $resp = $ctl->reward_distribution();

        $this->info($resp->getContent());
        return self::SUCCESS;
    }
}
