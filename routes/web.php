<?php

use App\Http\Controllers\GlobalController;
use App\Http\Controllers\StakingController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('staking');
});
Route::get('dashboard_data', [GlobalController::class, 'dashboard_data']);
Route::post('fetch_balance', [GlobalController::class, 'fetch_balance']);
Route::get('transactions', [GlobalController::class, 'getTransactions']);

Route::prefix('wallet')->group(function () {
    Route::post('connect', [WalletController::class, 'store'])->name('wallet.connect');
    Route::post('update', [WalletController::class, 'update'])->name('wallet.update');
    Route::post('disconnect', [WalletController::class, 'disconnect'])->name('wallet.disconnect');
});

Route::prefix('staking')->group(function () {
    Route::post('start', [StakingController::class, 'start'])->name('staking.start');
    Route::post('payload', [StakingController::class, 'createPayload'])->name('staking.payload');
    Route::get('payload/{uuid}', [StakingController::class, 'payloadStatus'])->name('staking.payload.status');
    Route::post('submit', [StakingController::class, 'submitSigned'])->name('staking.submit');
    Route::post('unstake', [StakingController::class, 'unstake'])->name('staking.unstake');
});
