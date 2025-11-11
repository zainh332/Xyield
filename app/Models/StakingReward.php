<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StakingReward extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'     => 'decimal:7',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function staking()
    {
        return $this->belongsTo(Staking::class, 'staking_id');
    }
}
