<?php

namespace App\Http\Controllers;

use App\Models\Staking;
use App\Models\StakingReward;
use App\Models\Transaction;
use App\Models\Wallet;
use Hardcastle\XRPL_PHP\Models\Transaction\TransactionTypes\BaseTransaction;
use Hardcastle\XRPL_PHP\Wallet\Wallet as WalletWallet;
use Hardcastle\XRPL_PHP\Models\Transaction\TransactionTypes\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StakingController extends Controller
{
    protected string $rpcUrl;
    protected string $network;
    protected string $mainWallet;
    protected string $mainWalletSeed;
    protected ?int $destTag;
    protected ?string $memo;
    protected string $xummKey;
    protected string $xummSecret;
    protected string $xummURL;
    protected string $issuer;
    protected string $tokenCode;
    private $sdk, $minAmount;
    private $asset, $apy;

    public function __construct()
    {
        $this->rpcUrl    = rtrim(config('xrpl.rpc_url'), '/');
        $this->network   = (string) config('xrpl.network');
        $this->mainWallet = (string) config('xrpl.main_wallet');
        $this->mainWalletSeed = (string) config('xrpl.main_wallet_seed');
        $this->issuer = (string) config('xrpl.issuer');
        $this->tokenCode = (string) config('xrpl.tokenCode');
        $this->destTag   = config('xrpl.dest_tag') !== null && config('xrpl.dest_tag') !== ''
            ? (int) config('xrpl.dest_tag') : null;
        $this->memo      = config('xrpl.memo') ?: null;

        $this->xummKey   = (string) config('xrpl.xumm.key');
        $this->xummSecret = (string) config('xrpl.xumm.secret');
        $this->xummURL = (string) config('xrpl.xumm.redirect_url');

        // Optional safety checks
        if (empty($this->mainWallet)) {
            abort(500, 'XRPL_MAIN_WALLET not configured.');
        }
        if (empty($this->rpcUrl)) {
            abort(500, 'XRPL_RPC_URL not configured.');
        }

        $this->minAmount = 1000;
        $this->asset = "XY";
        $this->apy = 36.6;
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'from'   => ['required', 'string', 'regex:/^r[1-9A-HJ-NP-Za-km-z]{25,}$/'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $fromAddress = $data['from'];
        $toAddress   = $this->mainWallet;
        if (!$toAddress) {
            return response()->json(['error' => 'Destination wallet not configured'], 500);
        }

        $wallet_id = $fromAddress ? Wallet::where('public_key', $fromAddress)->value('id') : null;

        if (!$wallet_id) {
            return response()->json(['status' => 'error', 'message' => 'Wallet not found!']);
        }

        $feeRes = Http::post($this->rpcUrl, [
            'method' => 'fee',
            'params' => [['ledger_index' => 'current']],
        ])->json();
        $feeDrops = data_get($feeRes, 'result.drops.open_ledger_fee')
            ?? data_get($feeRes, 'result.drops.minimum_fee')
            ?? '12';
        $feeDrops = (string) $feeDrops;

        // Get current sequence for sender
        $aiRes = Http::post($this->rpcUrl, [
            'method' => 'account_info',
            'params' => [[
                'account'      => $fromAddress,
                'ledger_index' => 'current',
                'strict'       => true,
            ]],
        ])->json();

        $sequence = (int) data_get($aiRes, 'result.account_data.Sequence');
        if (!$sequence) {
            return response()->json([
                'error'   => 'Account not found or not activated on XRPL',
                'details' => $aiRes,
            ], 422);
        }

        // Compute LastLedgerSequence window
        $ledgerRes    = Http::post($this->rpcUrl, ['method' => 'ledger_current', 'params' => [[]]])->json();
        $currentIndex = (int) data_get($ledgerRes, 'result.ledger_current_index', 0);
        $lastLedger   = $currentIndex + 12;

        $issuer      = $this->issuer;
        if (empty($issuer)) {
            return response()->json(['error' => 'XRPL_ISSUER not configured'], 500);
        }

        // Optional: ensure the sender has a trust line to issuer for XYIELD
        if (!$this->hasTrustLine($fromAddress, $issuer, $this->tokenCode)) {
            return response()->json([
                'error' => 'Sender has no trust line for XYIELD',
                'message'  => 'Create trust line to the issuer before sending.'
            ], 422);
        }

        // Issued currency amount (NOTE: NOT in drops; decimal string)
        $issuedAmount = [
            'currency' => $this->tokenCode,
            'issuer'   => $issuer,
            'value'    => $this->formatIssuedValue($data['amount']),
        ];

        // Build unsigned Payment for issued currency
        $tx = [
            'TransactionType'    => 'Payment',
            'Account'            => $fromAddress,
            'Destination'        => $toAddress,
            'Amount'             => $issuedAmount,
            'Fee'                => (string)$feeDrops,
            'Sequence'           => $sequence,
            'LastLedgerSequence' => $lastLedger,
        ];

        if ($this->destTag !== null) {
            $tx['DestinationTag'] = (int) $this->destTag;
        }

        Log::info('XRPL start tx built', ['tx_data' => $tx]);

        $existing_staking = Staking::where('wallet_id', $wallet_id)
            ->where('is_withdrawn', false)
            ->whereNotNull('transaction_id')
            ->latest()
            ->first();

        if ($existing_staking) {
            $newTotal = (float)$existing_staking->amount + (float)$request->amount;

            $existing_staking->amount = $newTotal;
            $existing_staking->staking_status_id    = 2; //topped up
            $existing_staking->save();

            $stakingId = $existing_staking->id;

            $this->addTransactionRecord($existing_staking->id, null, null, null, null, 2);
        } else {
            $startTotal = (float)$request->amount;
            $new_stake = new Staking();
            $new_stake->wallet_id = $wallet_id;
            $new_stake->apy = $this->apy;
            $new_stake->amount = $startTotal;
            $new_stake->staking_status_id = 1;
            $new_stake->save();

            $stakingId = $new_stake->id;

            $this->addTransactionRecord($new_stake->id, null, null, null, null, 1);
        }

        // Return unsigned tx for the frontend wallet to sign
        return response()->json([
            'network'           => $this->network,
            'expires_ledger'    => $lastLedger,
            'txjson'            => $tx,
            'staking_id'     => $stakingId,
        ]);
    }

    public function createPayload(Request $request)
    {
        $data = $request->validate([
            'txjson' => ['required', 'array'],
        ]);

        $payload = [
            'txjson'  => $data['txjson'],
            'options' => [
                'submit' => false,
                'expire' => 300,
            ],
            'custom_meta' => [
                'instruction' => 'XYIELD Staking Payment',
            ],
        ];

        $res = \Http::withHeaders([
            'X-API-Key'    => config('xrpl.xumm.key') ?? env('XUMM_API_KEY'),
            'X-API-Secret' => config('xrpl.xumm.secret') ?? env('XUMM_API_SECRET'),
        ])->post('https://xumm.app/api/v1/platform/payload', $payload);

        if (!$res->ok()) {
            return response()->json(['error' => 'Failed to create payload', 'details' => $res->json()], 500);
        }

        $body = $res->json();
        return response()->json([
            'uuid' => data_get($body, 'uuid'),
            'next' => data_get($body, 'next'),
            'refs' => data_get($body, 'refs'),
        ]);
    }

    public function payloadStatus(string $uuid)
    {
        $res = \Http::withHeaders([
            'X-API-Key'    => config('xrpl.xumm.key') ?? env('XUMM_API_KEY'),
            'X-API-Secret' => config('xrpl.xumm.secret') ?? env('XUMM_API_SECRET'),
        ])->get("https://xumm.app/api/v1/platform/payload/{$uuid}");

        if (!$res->ok()) {
            return response()->json(['error' => 'Failed to query payload', 'details' => $res->json()], 500);
        }

        $body = $res->json();

        $signed  = (bool) data_get($body, 'meta.signed', false);
        $txid    = data_get($body, 'response.txid');
        $txBlob  = data_get($body, 'response.hex');

        return response()->json([
            'signed' => $signed,
            'txid'   => $txid,
            'tx_blob' => $txBlob,
            'meta'   => data_get($body, 'meta'),
        ]);
    }

    public function submitSigned(Request $request)
    {
        $data = $request->validate([
            'tx_blob' => ['required', 'string'],
            'staking_id' => ['required', 'integer', 'exists:stakings,id']
        ]);

        $rpc = rtrim(config('xrpl.rpc_url') ?? env('XRPL_RPC_URL'), '/');
        $submit = \Http::post($rpc, [
            'method' => 'submit',
            'params' => [['tx_blob' => $data['tx_blob']]],
        ])->json();

        $engine = data_get($submit, 'result.engine_result');
        $txid   = data_get($submit, 'result.tx_json.hash');

        // Treat tesSUCCESS (final) and terQUEUED (queued) as acceptable "success" for saving IDs.
        $ok = in_array($engine, ['tesSUCCESS', 'terQUEUED'], true);

        $staking = Staking::find($data['staking_id']);
        $statusForTx = ($staking && (int)$staking->staking_status_id === 2) ? 2 : 1;

        if ($ok && $txid) {
            Staking::whereKey($data['staking_id'])->update([
                'transaction_id' => $txid,
                'updated_at'     => now(),
            ]);

            $this->addTransactionRecord(
                $data['staking_id'],
                null,
                null,
                $data['tx_blob'],
                $txid,
                $statusForTx
            );
        }

        return response()->json([
            'status' => data_get($submit, 'result.engine_result'),
            'txid'   => data_get($submit, 'result.tx_json.hash'),
            'raw'    => $submit,
        ]);
    }

    protected function hasTrustLine(string $account, string $issuer, string $currencyHex): bool
    {
        $res = \Http::post($this->rpcUrl, [
            'method' => 'account_lines',
            'params' => [['account' => $account, 'ledger_index' => 'validated', 'peer' => $issuer]]
        ])->json();

        $lines = data_get($res, 'result.lines', []);
        foreach ($lines as $line) {
            if (strtoupper($line['currency'] ?? '') === $currencyHex) {
                return true;
            }
        }
        
        return false;
    }

    protected function asciiCurrencyToHex(string $code): string
    {
        // Up to 20 bytes ASCII, right-padded with zeros, then hex-encoded (40 hex chars total)
        $bytes = substr($code, 0, 20);
        $hex   = strtoupper(bin2hex($bytes));
        return str_pad($hex, 40, '0');
    }

    protected function formatIssuedValue($amount): string
    {
        $n = (float) $amount;
        return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.') ?: '0';
    }

    public function unstake(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_key' => [
                'required',
                'string',
                'exists:wallets,public_key'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $wallet = Wallet::where('public_key', $request->public_key)->first();
        if (!$wallet) {
            return response()->json(['status' => 'error', 'message' => 'Wallet not found.'], 404);
        }

        $active_staking_wallet = Staking::query()
            ->where('wallet_id', $wallet->id)
            ->where('staking_status_id', '<>', 4)
            ->where('is_withdrawn', false)
            ->whereNotNull('transaction_id')
            ->first();

        if (!$active_staking_wallet) {
            return response()->json(['status' => 'error', 'message' => 'Already stopped staking or no active stake.'], 409);
        }

        // Ensure the destination can receive XYIELD (trust line to your issuer)
        if (!$this->hasTrustLine($request->public_key, $this->issuer, $this->tokenCode)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Destination wallet has no trust line for ' . $this->tokenCode . '. Please add trust line first.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $result = $this->submitIssuedPayment(
                destination: $request->public_key,
                value: $this->formatIssuedValue($active_staking_wallet->amount),
                destTag: $this->destTag,
                memo: 'stop staking'
            );

            if (! $result['ok']) {
                DB::rollBack();
                // You can tailor HTTP status; default to 500 if none
                $status = $result['status'] ?? 500;
                return response()->json([
                    'status'  => 'error',
                    'message' => $result['error'] ?? 'XRPL submission failed',
                    'details' => $result['context'] ?? null,
                ], $status);
            }

            // Success
            $txHash = $result['hash'] ?? null;

            $active_staking_wallet->is_withdrawn = true;
            $active_staking_wallet->transaction_id = $txHash;
            $active_staking_wallet->staking_status_id = 4; //unstaked
            $active_staking_wallet->save();

            $this->addTransactionRecord($active_staking_wallet->id, null, null, null, $txHash, $active_staking_wallet->staking_status_id);

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Successfully stopped staking and ' . $active_staking_wallet->amount . ' ' . $this->asset . ' tokens are sent back to your wallet',
                'txid'    => $txHash,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Stop Staking Error: ' . $th->getMessage(), ['trace' => $th->getTrace()]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred while processing the transaction.']);
        }
    }

    // Job distributing staking reward
    public function reward_distribution()
    {
        try {
            $invests = Staking::query()
                ->with(['user:id,public_key'])
                ->whereNotNull('transaction_id')
                ->where('amount', '>=', $this->minAmount)
                ->where('is_withdrawn', false)
                ->where('staking_status_id', '<>', 4)
                ->where('updated_at', '<=', now()->subHours(24))
                ->get();

            if ($invests->isEmpty()) {
                return response()->json(['processed' => 0]);
            }

            $processed = 0;

            foreach ($invests as $invest) {
                try {
                    $since = $invest->updated_at ?? $invest->created_at;
                    $days  = max(1, $since->diffInDays(now()));

                    $result = $this->reward($invest, $days);

                    if ($result) {
                        $reward = StakingReward::create([
                            'staking_id'     => $invest->id,
                            'amount'         => $result['amount'],
                            'transaction_id' => $result['tx'],
                        ]);

                        $this->addTransactionRecord(
                            $invest->id,
                            $reward->id,
                            $result->unsignedXdr ?? null,
                            $result->signedXdr   ?? null,
                            $result['tx'],
                            3, //Reward Distributed
                            ['days' => $days, 'apy' => (float)$invest->apy]
                        );

                        $invest->updated_at = now();
                        $invest->save();

                        $processed++;
                    } else {
                        Log::warning('staking.reward_distribution.skipped_or_failed', [
                            'staking_id' => $invest->id,
                            'user_id'    => $invest->user_id,
                            'reason'     => 'reward() returned null (see reward() logs for details)',
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('staking.reward_distribution.item_exception', [
                        'staking_id' => $invest->id,
                        'user_id'    => $invest->user_id,
                        'message'    => $e->getMessage(),
                        'file'       => $e->getFile(),
                        'line'       => $e->getLine(),
                    ]);
                }
            }

            return response()->json(['processed' => $processed]);
        } catch (\Throwable $e) {
            Log::critical('staking.reward_distribution.fatal', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Reward distribution failed. Check logs.'
            ], 500);
        }
    }

    // Sending staking reward tokens to the wallet from staking reward wallet
    private function reward(Staking $invest, int $days = 1)
    {
        $apy = (float) $invest->apy;

        if ($apy <= 0 || $days <= 0) {
            return null;
        }

        $rewardAmount = round(
            (float)$invest->amount * ($apy / 100.0) * ($days / 365.0),
            7
        );

        if ($rewardAmount <= 0) {
            return null;
        }

        try {
            $toPk = $invest->user?->public_key;
            if (!$toPk) {
                return null;
            }

            $result = $this->submitIssuedPayment(
                destination: $toPk,
                value: $this->formatIssuedValue($rewardAmount),
                destTag: $this->destTag,
                memo: 'staking reward'
            );

            if (! $result['ok']) {
                $status = $result['status'] ?? 500;
                return response()->json([
                    'status'  => 'error',
                    'message' => $result['error'] ?? 'XRPL submission failed',
                    'details' => $result['context'] ?? null,
                ], $status);
            }

            $txHash = $result['hash'] ?? null;

            return [
                'ok'     => true,
                'amount' => $rewardAmount,
                'tx'     => $txHash,
            ];
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function addTransactionRecord($staking_id, $staking_reward_id, $unsigned_xdr, $signed_xdr, $transaction_id, $staking_status_id)
    {
        $transaction = new Transaction();
        $transaction->staking_id = $staking_id;
        $transaction->staking_reward_id = $staking_reward_id;
        $transaction->unsigned_xdr = $unsigned_xdr;
        $transaction->signed_xdr = $signed_xdr;
        $transaction->transaction_id = $transaction_id;
        $transaction->staking_status_id = $staking_status_id;
        $transaction->save();

        return $transaction;
    }

    private function submitIssuedPayment(string $destination, string $value, ?int $destTag = null, ?string $memo = null): array
    {
        try {
            // Fee
            $feeRes = Http::post($this->rpcUrl, [
                'method' => 'fee',
                'params' => [['ledger_index' => 'current']],
            ]);

            if (! $feeRes->ok()) {
                Log::error('XRPL fee fetch failed', ['status' => $feeRes->status(), 'body' => $feeRes->body()]);
                return ['ok' => false, 'error' => 'Failed to fetch fee', 'status' => $feeRes->status()];
            }

            $feeJson  = $feeRes->json();
            $feeDrops = (string) (
                data_get($feeJson, 'result.drops.open_ledger_fee')
                ?? data_get($feeJson, 'result.drops.minimum_fee')
                ?? '12'
            );

            // account_info for sender (main wallet)
            $aiRes = Http::post($this->rpcUrl, [
                'method' => 'account_info',
                'params' => [[
                    'account'      => $this->mainWallet,
                    'ledger_index' => 'current',
                    'strict'       => true,
                ]],
            ]);

            if (! $aiRes->ok()) {
                Log::error('XRPL account_info fetch failed', ['status' => $aiRes->status(), 'body' => $aiRes->body()]);
                return ['ok' => false, 'error' => 'Failed to fetch account info', 'status' => $aiRes->status()];
            }

            $aiJson   = $aiRes->json();
            $sequence = (int) data_get($aiJson, 'result.account_data.Sequence', 0);
            if ($sequence <= 0) {
                Log::error('Invalid sequence fetched', ['response' => $aiJson]);
                return ['ok' => false, 'error' => 'Invalid account sequence', 'status' => 422, 'context' => $aiJson];
            }

            // Current ledger
            $ledgerRes = Http::post($this->rpcUrl, [
                'method' => 'ledger_current',
                'params' => [],
            ]);

            if (! $ledgerRes->ok()) {
                Log::error('XRPL ledger_current fetch failed', ['status' => $ledgerRes->status(), 'body' => $ledgerRes->body()]);
                return ['ok' => false, 'error' => 'Failed to fetch ledger index', 'status' => $ledgerRes->status()];
            }

            $ledgerJson   = $ledgerRes->json();
            $currentIndex = (int) data_get($ledgerJson, 'result.ledger_current_index', 0);
            if ($currentIndex <= 0) {
                Log::error('Invalid ledger index fetched', ['response' => $ledgerJson]);
                return ['ok' => false, 'error' => 'Invalid ledger index', 'status' => 502, 'context' => $ledgerJson];
            }

            $lastLedger = $currentIndex + 20;

            // Build issued amount + tx
            $issuedAmount = [
                'currency' => $this->tokenCode,
                'issuer'   => $this->issuer,
                'value'    => $value, // already formatted
            ];

            $memoHex  = strtoupper(bin2hex($memo));

            $tx = [
                'TransactionType'    => 'Payment',
                'Account'            => $this->mainWallet,
                'Destination'        => $destination,
                'Amount'             => $issuedAmount,
                'Fee'                => $feeDrops,
                'Sequence'           => $sequence,
                'LastLedgerSequence' => $lastLedger,
                'Memos' => [[
                    'Memo' => ['MemoData' => $memoHex]
                ]],
            ];

            if ($destTag !== null) {
                $tx['DestinationTag'] = (int) $destTag;
            }

            Log::info('XRPL payment tx built', ['tx_data' => $tx]);

            // Sign locally
            $wallet    = WalletWallet::fromSeed($this->mainWalletSeed);
            $paymentTx = new Payment($tx);
            $signed    = $wallet->sign($paymentTx);

            $txBlob = $signed['tx_blob'] ?? null;
            if (empty($txBlob)) {
                Log::error('Local XRPL signing error: no tx_blob returned', ['signed' => $signed]);
                return ['ok' => false, 'error' => 'Signing failed', 'status' => 500, 'context' => $signed];
            }

            // Submit
            $submitRes = Http::post($this->rpcUrl, [
                'method' => 'submit',
                'params' => [['tx_blob' => $txBlob]],
            ]);

            if (! $submitRes->ok()) {
                Log::error('XRPL submit HTTP failed', ['body' => $submitRes->body()]);
                return ['ok' => false, 'error' => 'Transaction submit HTTP failed', 'status' => $submitRes->status()];
            }

            $submitJson   = $submitRes->json();
            $engineResult = data_get($submitJson, 'result.engine_result');
            $txHash       = data_get($submitJson, 'result.tx_json.hash');

            if ($engineResult !== 'tesSUCCESS' && $engineResult !== 'terQUEUED') {
                Log::error('XRPL transaction failed', ['engine_result' => $engineResult, 'details' => $submitJson]);
                return [
                    'ok'     => false,
                    'error'  => 'Transaction submission failed',
                    'status' => 500,
                    'context' => $submitJson,
                ];
            }

            Log::info('XRPL transaction accepted', ['hash' => $txHash, 'engine' => $engineResult]);

            return [
                'ok'            => true,
                'hash'          => $txHash,
                'engine_result' => $engineResult,
                'tx_json'       => data_get($submitJson, 'result.tx_json'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Unexpected error during XRPL submit', 'status' => 500];
        }
    }
}
