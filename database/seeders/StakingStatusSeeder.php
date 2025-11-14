<?php

namespace Database\Seeders;

use App\Models\StakingStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StakingStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StakingStatus::truncate();

        $names = [
            'Active',
            'Topped Up',
            'Reward Distributed',
            'Unstaked'
        ];

        foreach ($names as $name) {
            StakingStatus::create(['name' => $name]);
        }
    }
}
