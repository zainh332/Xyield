<?php

namespace App\Http\Controllers;

use App\Models\Staking;
use App\Models\StakingReward;
use App\Models\Wallet;
use Illuminate\Http\Request;

class GlobalController extends Controller
{
    protected string $rpcUrl;
    protected string $issuer;
    protected string $tokenCode;

    public function __construct()
    {
        $this->rpcUrl    = rtrim(config('xrpl.rpc_url'), '/');
        $this->issuer = (string) config('xrpl.issuer');
        $this->tokenCode = (string) config('xrpl.tokenCode');
        if (empty($this->rpcUrl)) {
            abort(500, 'XRPL_RPC_URL not configured.');
        }
    }

    public function dashboard_data()
    {
        $stakers = Staking::whereNotNull('transaction_id')
            ->whereIn('staking_status_id', [1, 2])
            ->count();

        $reward_transactions = StakingReward::select('amount', 'transaction_id', 'created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $rewards  = StakingReward::sum('amount');

        return response()->json([
            'stakers' => $stakers,
            'reward_transactions' => $reward_transactions,
            'rewards' => $rewards,
        ]);
    }

    public function getTransactions(Request $request)
    {
        $data = $request->validate([
            'public_key' => ['required', 'string', 'regex:/^r[1-9A-HJ-NP-Za-km-z]{25,}$/'],
        ]);

        $publicKey = $request->public_key;

        $transactions = Staking::query()
            ->where('wallet_id', function ($q) use ($publicKey) {
                $q->select('id')
                    ->from('wallets')
                    ->where('public_key', $publicKey)
                    ->where('transaction_id','!=', null)
                    ->limit(1);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id as stake_id', 'transaction_id', 'amount', 'created_at', 'staking_status_id', 'is_withdrawn']);

        return response()->json([
            'status'       => 'success',
            'transactions' => $transactions,
        ]);
    }

    public function fetch_balance(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'regex:/^r[1-9A-HJ-NP-Za-km-z]{25,}$/'],
        ]);

        $res = \Http::post($this->rpcUrl, [
            'method' => 'account_lines',
            'params' => [['account' => $data['from'], 'ledger_index' => 'validated', 'peer' => $this->issuer]]
        ])->json();


        $lines = data_get($res, 'result.lines', []);
        foreach ($lines as $line) {
            if (strtoupper($line['currency'] ?? '') === $this->tokenCode) {
                return response()->json([
                    'balance' => $line['balance']
                ]);
            }
        }
        return false;
    }
}
