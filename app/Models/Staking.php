<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staking extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function scopeForPublicKey(Builder $q, string $publicKey): Builder
    {
        return $q->whereHas('user', fn ($uq) => $uq->where('public_key', $publicKey));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_withdrawn', false);
    }

    public function scopeMinAmount(Builder $q, float $min): Builder
    {
        return $q->where('amount', '>=', $min);
    }

    public function rewards()
    {
        return $this->hasMany(StakingReward::class, 'staking_id');
    }

    public function status()
    {
        return $this->belongsTo(StakingStatus::class, 'staking_status_id');
    }
}
