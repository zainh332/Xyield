<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('transaction_type')->nullable();
            $table->unsignedBigInteger('staking_id')->index()->nullable();
            $table->unsignedBigInteger('staking_reward_id')->index()->nullable();
            $table->longText('unsigned_xdr')->nullable();
            $table->longText('signed_xdr')->nullable();
            $table->string('transaction_id')->nullable();
            $table->boolean('staking_status_id')->index()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
