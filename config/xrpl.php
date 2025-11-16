<?php
return [
    // XRPL network
    'rpc_url'     => env('XRPL_RPC_URL', 'https://s.altnet.rippletest.net:51234'),
    'network'     => env('XRPL_NETWORK', 'testnet'),

    // Your pool wallet
    'main_wallet' => env('XRPL_MAIN_WALLET', ''),  
    'main_wallet_seed' => env('XRPL_MAIN_WALLET_SEED', ''),   
    'dest_tag'    => env('XRPL_DEST_TAG', null),    
    'memo'        => env('XYIELD_MEMO', 'XYIELD STAKING'),
    'issuer'        => env('XRPL_ISSUER', ''),
    'tokenCode'        => env('XRPL_TOKEN_CODE', ''),

    // Xaman / XUMM platform (server-side only)
    'xumm' => [
        'key'    => env('XUMM_API_KEY', ''),
        'secret' => env('XUMM_API_SECRET', ''),
        'redirect_url' => env('REDIRECT_URL', ''),
    ],
];
