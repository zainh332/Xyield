<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_key' => 'required|string',
            'wallet_type_id' => 'required|integer',
            'blockchain_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (empty($request->public_key) || empty($request->wallet_type_id) || empty($request->blockchain_id)) {
            return response()->json(['status' => 'error', 'message' => 'Public Key or Wallet Type is missing or Blockchain Type missing']);
        }

        $wallet = Wallet::updateOrCreate(
            ['public_key' => $request->public_key],
            [
                'wallet_type_id' => $request->wallet_type_id,
                'blockchain_id' => $request->blockchain_id,
                'status' => 1
            ]
        );

        $token = $wallet->createToken('tokenglade')->plainTextToken;

        setcookie('public_key', $request->public_key, time() + (86400 * 30), "/");
        setcookie('wallet_type_id', $request->wallet_type_id, time() + (86400 * 30), "/");
        setcookie('blockchain_id', $request->blockchain_id, time() + (86400 * 30), "/");
        setcookie('accessToken', $token, time() + (86400 * 30), "/");

        return response()->json([
            'status' => 'success',
            'public' => $request->public_key,
            'token' => $token
        ]);
    }

    public function update_wallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'previous_public_key' => 'required|string',
            'current_public_key' => 'required|string',
            'wallet_type_id' => 'required|integer',
            'blockchain_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $previous_wallet = Wallet::where('public_key', $request->previous_public_key)->where('status', 1)->first();
        if ($previous_wallet) {
            $previous_wallet->status = 0;
            $previous_wallet->save();
        } else {
            return response()->json(['status' => 'error', 'message' => 'Previous Public Key is missing']);
        }

        $wallet = Wallet::updateOrCreate(
            ['public_key' => $request->current_public_key],
            [
                'wallet_type_id' => $request->wallet_type_id,
                'blockchain_id' => $request->blockchain_id,
                'status' => 1
            ]
        );

        $token = $wallet->createToken('xrush')->plainTextToken;

        setcookie('public_key', $request->current_public_key, time() + (86400 * 30), "/");
        setcookie('wallet_type_id', $request->wallet_type_id, time() + (86400 * 30), "/");
        setcookie('blockchain_id', $request->blockchain_id, time() + (86400 * 30), "/");
        setcookie('accessToken', $token, time() + (86400 * 30), "/");

        return response()->json([
            'status' => 'success',
            'public' => $request->current_public_key,
            'token' => $token
        ]);
    }


    public function disconnect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_key' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $public_wallet = $request->public_key;
        $wallet = Wallet::where('public_key', $public_wallet)->where('status', 1)->first();
        if ($wallet) {
            $wallet->status = 0;
            $wallet->save();
            return response()->json([
                'status' => 'success',
            ]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Wallet not found']);
        }
    }
}
