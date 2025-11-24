<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>XYield</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ asset('images/Logo.png') }}">

    {{-- Tailwind CDN for quick start (move to Vite in prod) --}}
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="https://xumm.app/assets/cdn/xumm.min.js" defer></script>
    <script src="https://unpkg.com/xrpl@2.12.0/build/xrpl-latest-min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Sweetalert JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0b0f1a]/70 backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/Logo.png') }}" alt="XYield"
                    class="h-9 w-9 rounded-full ring-2 ring-white/10" />
                {{-- <span class="font-semibold tracking-wide text-white/90">XYield</span> --}}
            </div>

            <nav class="hidden md:flex items-center gap-6 text-sm text-white/80">
                <a href="#" class="hover:text-white">Home</a>
                <a href="#" class="hover:text-white">Whitepaper</a>
            </nav>

            <div class="relative flex items-center gap-3">
                <button id="btn-network"
                    class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg btn-outline">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                    <span id="networkLabel">Network: Public</span>
                </button>

                <div class="relative">
                    <button id="btn-connect" class="px-4 py-2 rounded-xl btn-primary">
                        Connect Wallet
                    </button>

                    <!-- you can remove this if not needed -->
                    <div id="wallet-dropdown"
                        class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg z-50">
                        <div class="px-4 py-2 text-sm text-gray-700 border-b">
                            <span id="wallet-short"></span>
                        </div>
                        <button id="btn-disconnect"
                            class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50">
                            Disconnect
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="relative">
        <div class="absolute inset-0 opacity-30 blur-3xl" style="background:var(--grad-1);"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 sm:py-14 relative">
            <div class="grid md:grid-cols-2 gap-8 items-center">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold leading-tight">
                        Stake <span class="grad-text">XYield</span> — Earn Daily Rewards
                    </h1>
                    <p class="mt-3 text-white/70">Lock your tokens and earn fixed yield with transparent 24h payouts.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <span class="pill px-3 py-1.5 rounded-full text-sm">Fixed 36.6% APY</span>
                        <span class="pill px-3 py-1.5 rounded-full text-sm">0.1% daily • 3% monthly</span>
                        <span class="pill px-3 py-1.5 rounded-full text-sm">24h Payouts</span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="card rounded-2xl p-5 ring-grad">
                        <div class="text-xs uppercase tracking-wide text-white/60">Supply</div>
                        <div id="total-supply" class="mt-1 text-2xl font-semibold">—</div>
                        <div class="mt-3 divider"></div>
                        <div class="mt-3 text-xs text-white/60">Total Supply</div>
                    </div>
                    <div class="card rounded-2xl p-5">
                        <div class="text-xs uppercase tracking-wide text-white/60">Stakers</div>
                        <div id="stat-stakers" class="mt-1 text-2xl font-semibold">—</div>
                        <div class="mt-3 divider"></div>
                        <div class="mt-3 text-xs text-white/60">Active wallets</div>
                    </div>
                    <div class="card rounded-2xl p-5">
                        <div class="text-xs uppercase tracking-wide text-white/60">Rewards</div>
                        <div id="stat-24h" class="mt-1 text-2xl font-semibold">—</div>
                        <div class="mt-3 divider"></div>
                        <div class="mt-3 text-xs text-white/60">Distributed</div>
                    </div>
                    <div class="card rounded-2xl p-5">
                        <div class="text-xs uppercase tracking-wide text-white/60">Your Balance</div>
                        <div id="stat-balance" class="mt-1 text-2xl font-semibold">—</div>
                        <div class="mt-3 divider"></div>
                        <div class="mt-3 text-xs text-white/60">Available to stake</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full grow">
        <div class="grid lg:grid-cols-3 gap-8 pb-16">

            <!-- Stake Form -->
            <section class="lg:col-span-2">
                <div class="rounded-2xl card ring-grad p-6">
                    <h2 class="text-xl font-semibold">Stake XYield</h2>
                    <p class="mt-1 text-sm text-white/70">Yield is fixed at <b>36.6% APY</b> (≈ <b>3%</b> monthly,
                        <b>0.1%</b> daily). Rewards are paid each 24h cycle.
                    </p>

                    <form id="stake-form" class="mt-6 space-y-5">
                        <div>
                            <label class="block text-sm text-white/80">Amount to Stake</label>
                            <div class="mt-1 flex gap-2">
                                <input id="amount" type="number" step="0.000001" min="0"
                                    class="w-full rounded-xl px-3 py-2 input" placeholder="0.00" required>
                                <button type="button" id="btn-max"
                                    class="px-3 rounded-lg btn-outline text-sm">MAX</button>
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-white/80">Estimated Daily (0.1%)</label>
                                <input id="est-daily" class="mt-1 w-full rounded-xl px-3 py-2 input" readonly
                                    value="0.000000" />
                            </div>
                            <div>
                                <label class="block text-sm text-white/80">Estimated Monthly (3%)</label>
                                <input id="est-month" class="mt-1 w-full rounded-xl px-3 py-2 input" readonly
                                    value="0.000000" />
                            </div>
                            <div>
                                <label class="block text-sm text-white/80">Estimated Yearly (36.6%)</label>
                                <input id="est-year" class="mt-1 w-full rounded-xl px-3 py-2 input" readonly
                                    value="0.000000" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl btn-primary">Stake</button>
                            <span id="form-msg" class="text-sm text-white/70"></span>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Sidebar -->
            <aside class="space-y-6">
                <div class="rounded-2xl card p-5">
                    <h3 class="font-semibold">APY & Payouts</h3>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        <li>• Fixed yield: <b>36.6% APY</b> (≈ <b>3% monthly</b>, <b>0.1% daily</b>).</li>
                        <li>• Rewards accrue continuously and are paid every <b>24h</b>.</li>
                        <li>• Adding more stake updates your base for the next 24h window.</li>
                    </ul>
                </div>

                {{-- <div class="rounded-2xl card p-5">
                    <h3 class="font-semibold">Your Positions</h3>
                    <div id="positions" class="mt-3 space-y-3 text-sm">
                        <div class="text-white/60">No active stakes yet.</div>
                    </div>
                </div> --}}
            </aside>
        </div>


        <section id="user-positions-section" class="pb-16 hidden">
            <div class="rounded-2xl card p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Your Position</h3>
                    <div class="text-sm text-white/60">
                        Showing last <span id="tx-count">0</span>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-[720px] w-full text-left">
                        <thead class="text-sm text-white/70">
                            <tr class="border-b border-white/10">
                                <th class="py-3 pr-3">Date</th>
                                <th class="py-3 pr-3">Tx Hash</th>
                                <th class="py-3 pr-3">Status</th>
                                <th class="py-3 pr-3">Amount</th>
                                <th class="py-3 pr-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="user-transactions" class="text-sm"><!-- JS injects rows --></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Recent Rewards -->
        <section class="pb-16">
            <div class="rounded-2xl card p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Recent Rewards</h3>
                    <div class="text-sm text-white/60">Showing last 10 payouts</div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-[720px] w-full text-left">
                        <thead class="text-sm text-white/70">
                            <tr class="border-b border-white/10">
                                <th class="py-3 pr-3">Date</th>
                                <th class="py-3 pr-3">Tx Hash</th>
                                <th class="py-3 pr-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="rewards-body" class="text-sm"><!-- JS injects rows --></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="pb-24">
            <div class="rounded-2xl card p-6">
                <h3 class="text-lg font-semibold">FAQ</h3>
                <div class="mt-4 grid md:grid-cols-2 gap-6 text-white/80">
                    <div>
                        <div class="font-medium">When do I get my first reward?</div>
                        <p class="mt-1 text-white/60">Within the next 24h payout after staking.</p>
                    </div>
                    <div>
                        <div class="font-medium">Can I add more later?</div>
                        <p class="mt-1 text-white/60">Yes. New amounts start earning from the following 24h cycle.</p>
                    </div>
                    <div>
                        <div class="font-medium">Is there a fee?</div>
                        <p class="mt-1 text-white/60">Only standard network fees. No platform fee on staking.</p>
                    </div>
                    <div>
                        <div class="font-medium">How are yields shown?</div>
                        <p class="mt-1 text-white/60">Estimates shown here are simple-rate (non-compounding) for
                            clarity.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-white/10">
        <div
            class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 text-sm text-white/60 flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/Logo.png') }}" class="h-7 w-7 rounded-full ring-1 ring-white/10"
                    alt="">
                <span>© {{ date('Y') }} XYield. All rights reserved.</span>
            </div>
            <div class="flex gap-5">
                <a href="#" class="hover:text-white">Terms</a>
                <a href="#" class="hover:text-white">Privacy</a>
                <a href="#" class="hover:text-white">Contact</a>
            </div>
        </div>
    </footer>

    <!-- Page JS -->
    <script>
        const $ = (s, r = document) => r.querySelector(s);
        const fmt = (n, d = 2) => Number(n ?? 0).toLocaleString(undefined, {
            minimumFractionDigits: d,
            maximumFractionDigits: d
        });
        const state = {
            connected: false,
            address: null,
            balance: 0,
            totalSupply: 10000000,
            stakers: 0,
            rewards: 0
        };
        const RATES = {
            daily: 0.001,
            monthly: 0.03,
            yearly: 0.366
        }; // 0.1%, 3%, 36.6%

        const ui = {
            totalSupply: $('#total-supply'),
            stakers: $('#stat-stakers'),
            r24: $('#stat-24h'),
            bal: $('#stat-balance'),
            walletInfo: $('#wallet-info'),
            walletAddr: $('#wallet-address span'),
            formMsg: $('#form-msg'),
            rewardsBody: $('#rewards-body'),
            amount: $('#amount'),
            estDaily: $('#est-daily'),
            estMonth: $('#est-month'),
            estYear: $('#est-year')
        };

        const publicKey = state.address || localStorage.getItem('xrpl_account');

        function renderStats() {
            if (ui.totalSupply) ui.totalSupply.textContent = fmt(state.totalSupply, 0);
            if (ui.stakers) ui.stakers.textContent = fmt(state.stakers, 0);
            if (ui.r24) ui.r24.textContent = fmt(state.rewards);
            if (ui.bal) ui.bal.textContent = state.connected ? fmt(state.balance) : '—';
        }

        async function loadDashboardData() {
            try {
                const res = await fetch('/dashboard_data', {
                    credentials: 'include'
                });
                const data = await res.json();

                state.stakers = Number(data.stakers ?? 0);
                state.rewards = Number(data.rewards ?? 0);

                // render table with the correct array key
                renderRewardRows(Array.isArray(data.reward_transactions) ? data.reward_transactions : []);

                // re-render the stat cards AFTER state is updated
                renderStats();
            } catch (e) {
                console.error('Error loading dashboard data:', e);
            }
        }

        async function loadBalance() {
            try {
                const res = await fetch('/fetch_balance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        from: publicKey,
                    })
                });
                const data = await res.json();

                state.balance = Number(data.balance ?? 0);

                // re-render the stat cards AFTER state is updated
                renderStats();
            } catch (e) {
                console.error('Error loading dashboard data:', e);
            }
        }

        function renderRewardRows(list = []) {
            if (!ui.rewardsBody) return;
            const rows = list.map(r => {
                const txId = r.transaction_id || '';
                // Explorer base URL for the live network:
                const explorerUrl = `https://livenet.xrpl.org/transactions/${txId}`;
                return `
                    <tr class="border-b border-white/10">
                    <td class="py-3 pr-3 text-white/80">${new Date(r.created_at).toLocaleString()}</td>
                    <td class="py-3 pr-3 font-mono text-white/70">
                        <a href="${explorerUrl}" target="_blank" rel="noopener noreferrer">
                        View Transaction
                        </a>
                    </td>
                    <td class="py-3 pr-3">${fmt(r.amount)}</td>
                    </tr>
                `;
            }).join('');
            ui.rewardsBody.innerHTML = rows;
        }

        function updateEstimate() {
            const amt = Number(ui.amount.value || 0);
            ui.estDaily.value = fmt(amt * RATES.daily, 6);
            ui.estMonth.value = fmt(amt * RATES.monthly, 6);
            ui.estYear.value = fmt(amt * RATES.yearly, 6);
        }

        const XAMAN_API_KEY = '8e39c96e-8a2b-4a99-a3f3-383f14480d66';
        const XAMAN_REDIRECT = 'https://xyield.vip/';

        let xumm = null;

        function ensureXumm() {
            if (xumm) return xumm;
            if (typeof window.Xumm !== 'function') {
                console.error(
                    '[xumm] SDK not loaded. Check the <script src="https://xumm.app/assets/cdn/xumm.min.js"> tag order.'
                );
                return null;
            }
            xumm = new Xumm(XAMAN_API_KEY, {
                redirectUrl: XAMAN_REDIRECT
            });
            xumm.on('ready', () => console.log('[xumm] ready'));
            xumm.on('error', e => console.error('[xumm] error', e));
            xumm.on('logout', () => {
                console.log('[xumm] logged out');
                if (ui.walletInfo) ui.walletInfo.classList.add('hidden');
                if (ui.walletAddr) ui.walletAddr.textContent = '...';
                state.connected = false;
                state.address = null;
                renderStats();
            });
            return xumm;
        }

        function shortAddr(a) {
            return a ? a.slice(0, 6) + '…' + a.slice(-6) : '…';
        }

        const btnConnect = document.getElementById('btn-connect');
        const walletShort = document.getElementById('wallet-short');
        const dropdown = document.getElementById('wallet-dropdown');

        document.addEventListener('DOMContentLoaded', () => {
            const saved = localStorage.getItem('xrpl_account');
            if (saved) {
                state.address = saved;
                state.connected = true;
                btnConnect.textContent = 'Disconnect';
                if (walletShort) walletShort.textContent = shortAddr(saved);
                ui.walletInfo?.classList.remove('hidden');
                if (ui.walletAddr) ui.walletAddr.textContent = saved;
            }
        });

        // main button click
        btnConnect.addEventListener('click', async () => {
            if (!state.connected) {
                await connectWallet();
            } else {
                await disconnectWallet();
            }
        });

        async function connectWallet() {
            btnConnect.textContent = 'Connecting';
            try {
                const client = ensureXumm();
                await client.authorize();
                const publicKey = await client.user.account;

                state.address = publicKey;
                state.connected = true;

                ui.walletInfo?.classList.remove('hidden');
                if (ui.walletAddr) ui.walletAddr.textContent = publicKey;

                btnConnect.textContent = 'Disconnect';
                if (walletShort) walletShort.textContent = shortAddr(publicKey);

                localStorage.setItem('xrpl_account', publicKey);

                const res = await fetch('/wallet/connect', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        public_key: publicKey,
                        wallet_type_id: 1,
                        blockchain_id: 1
                    }),
                    credentials: 'include'
                });

                const data = await res.json();
                if (data.status === 'success') {
                    localStorage.setItem('accessToken', data.token);
                    // window.location.reload();
                    loadBalance();
                    loadUserTransactions(publicKey);
                } else {
                    console.warn('wallet not saved', data);
                }

                dropdown?.classList.add('hidden');
                renderStats();
            } catch (e) {
                console.error('[xumm] connect error', e);
                // Swal.fire({
                //     title: 'Error',
                //     text: 'Wallet connection failed.',
                //     icon: 'error',
                //     confirmButtonText: 'OK'
                // });
            }
        }

        async function disconnectWallet() {
            if (publicKey) {
                try {
                    await fetch('/wallet/disconnect', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            public_key: publicKey
                        }),
                        credentials: 'include'
                    });
                } catch (err) {
                    console.warn('backend disconnect failed', err);
                }
            }

            if (typeof xumm !== 'undefined' && xumm) {
                xumm.logout?.();
            }

            state.connected = false;
            state.address = null;
            ui.walletInfo?.classList.add('hidden');
            ui.walletAddr && (ui.walletAddr.textContent = '...');
            btnConnect.textContent = 'Connect Wallet';
            dropdown?.classList.add('hidden');
            localStorage.removeItem('xrpl_account');
            localStorage.removeItem('accessToken');

            renderStats();
        }

        $('#btn-copy')?.addEventListener('click', () => {
            const text = state.address ?? '';
            if (!text) return;
            navigator.clipboard.writeText(text);
        });

        $('#btn-max').addEventListener('click', () => {
            if (!state.connected) return;
            ui.amount.value = state.balance;
            updateEstimate();
        });

        $('#stake-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!state.connected) {
                Swal.fire({
                    title: 'Connect wallet first',
                    icon: 'warning'
                });
                return;
            }
            const amt = Number(ui.amount.value || 0);
            if (amt <= 0) {
                Swal.fire({
                    title: 'Enter a valid amount',
                    icon: 'error'
                });
                return;
            }

            try {
                Swal.fire({
                    title: 'Preparing...',
                    text: `Preparing transaction for ${fmt(amt,2)} XYield`,
                    icon: 'info',
                    showConfirmButton: false,
                    timer: null
                });

                // 1) Ask backend to build unsigned tx
                const startRes = await fetch('/staking/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        from: state.address,
                        amount: amt
                    })
                });
                if (!startRes.ok) throw new Error('start failed');
                const { txjson, staking_id } = await startRes.json();
                if (!staking_id) throw new Error('Missing staking_id from start response');

                // 2) Create Xaman payload on backend
                const payloadRes = await fetch('/staking/payload', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        txjson
                    })
                });
                if (!payloadRes.ok) throw new Error('payload creation failed');
                const payload = await payloadRes.json();

                // 3) Open Xaman for signing (mobile deep link / new tab). Also show QR for desktop if you want.
                // Deep link / Always URL:
                const signLink = payload?.next?.always;
                if (signLink) window.open(signLink, '_blank'); // or set window.location = signLink

                // 4) Poll payload status until signed (or timeout)
                Swal.fire({
                    title: 'Waiting for signature…',
                    text: 'Please complete signing in Xumm',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                const uuid = payload.uuid;
                const started = Date.now();
                let signedBlob = null,
                    txid = null;

                while (Date.now() - started < 180000) { // 3 minute timeout
                    await new Promise(r => setTimeout(r, 2000));
                    const statusRes = await fetch(`/staking/payload/${uuid}`, {
                        credentials: 'include'
                    });
                    const status = await statusRes.json();
                    if (status.signed) {
                        signedBlob = status.tx_blob; // present because we used submit:false
                        txid = status.txid || null;
                        break;
                    }
                    if (status.resolved === true && status.signed === false) {
                        // user rejected the request
                        Swal.fire({
                            title: 'Signature rejected',
                            text: 'You rejected the sign request.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                }

                if (!signedBlob) {
                    Swal.fire({
                        title: 'Signature not received',
                        text: 'Timed out or cancelled.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Submitting transaction…',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                const submitRes = await fetch('/staking/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        tx_blob: signedBlob,
                        staking_id: staking_id
                    })
                });
                const submitData = await submitRes.json();

                if (submitData.status === 'tesSUCCESS') {
                    const explorerUrl = `https://livenet.xrpl.org/transactions/${txId}`;
                    Swal.fire({
                        title: 'Success!',
                        html: `Stake submitted. <a href="${explorerUrl}" target="_blank" rel="noopener">View transaction</a>`,
                        icon: 'success',
                        confirmButtonText: 'Done'
                    });
                } else {
                    Swal.fire({
                        title: 'Broadcast result',
                        text: submitData.status || 'unknown',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }

            } catch (err) {
                console.error(err);
                Swal.fire({
                    title: 'Error',
                    text: 'Preparing or submitting transaction failed.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });

        async function handleUnstake(stakeId) {
            if (!publicKey) {
                Swal.fire({
                    title: 'Error',
                    text: 'Wallet address not found.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            Swal.fire({
                title: 'Submitting unstake request…',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false
            });

            try {
                const res = await fetch('/staking/unstake', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                            'content') || ''
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        public_key: publicKey,
                        stake_id: stakeId
                    })
                });

                const data = await res.json();

                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Unstake successfully',
                        icon: 'success',
                        confirmButtonText: 'Done'
                    });
                    // Refresh the transactions list so the table updates (button removed, etc.)
                    await loadUserTransactions(publicKey);
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: 'Unstake failed.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }

            } catch (err) {
                console.error('Unstake request error', err);
                Swal.fire({
                    title: 'Error',
                    text: 'Unexpected error during unstake.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        }

        async function loadUserTransactions(publicKey) {
            const section = document.getElementById('user-positions-section');
            const countEl = document.getElementById('tx-count');
            const tbody = document.getElementById('user-transactions');

            try {
                const res = await fetch(`/transactions?public_key=${encodeURIComponent(publicKey)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'include'
                });
                const data = await res.json();
                if (!data || data.status !== 'success' || !Array.isArray(data.transactions)) {
                    console.error('Failed to fetch transactions', data);
                    if (tbody) tbody.innerHTML = '';
                    if (section) section.classList.add('hidden');
                    if (countEl) countEl.textContent = '0';
                    return;
                }

                const list = data.transactions || [];

                if (list.length > 0) {
                    if (countEl) countEl.textContent = String(Math.min(list.length,
                    10));
                    if (section) section.classList.remove('hidden');
                    renderTransactionRows(list);
                } else {
                    if (tbody) tbody.innerHTML = '';
                    if (countEl) countEl.textContent = '0';
                    if (section) section.classList.add('hidden');
                }

            } catch (err) {
                console.error('Error loading transactions', err);
                const section = document.getElementById('user-positions-section');
                const tbody = document.getElementById('user-transactions');
                const countEl = document.getElementById('tx-count');

                if (tbody) tbody.innerHTML = '';
                if (countEl) countEl.textContent = '0';
                if (section) section.classList.add('hidden');
            }
        }

        function renderTransactionRows(list = []) {
            const tbody = document.querySelector('#user-transactions');
            if (!tbody) return;

            const rows = list.map(item => {
                const date = item.created_at ? new Date(item.created_at).toLocaleString() : '-';
                const txId = item.transaction_id || '';
                const explorerUrl = txId ? `https://livenet.xrpl.org/transactions/${txId}` : '';
                const hashDisplay = txId ?
                    `<a href="${explorerUrl}" target="_blank" rel="noopener noreferrer">View Transaction</a>` :
                    '-';

                const amount = (item.amount ?? '-') + '';
                const statusId = Number(item.staking_status_id);
                const withdrawn = Number(item.is_withdrawn);
                const isActive = (statusId === 1 && withdrawn === 0);
                const statusText = isActive ? 'Active' : 'Inactive';

                const actionHtml = isActive ?
                    `<button class="unstake-btn px-5 py-2.5 rounded-xl btn-primary" data-stake-id="${item.stake_id}">Unstake</button>` :
                    '';

                return `
                <tr class="border-b border-white/10">
                    <td class="py-3 pr-3 text-white/80">${date}</td>
                    <td class="py-3 pr-3 font-mono text-white/70">${hashDisplay}</td>
                    <td class="py-3 pr-3">${statusText}</td>
                    <td class="py-3 pr-3">${amount}</td>
                    <td class="py-3 pr-3">${actionHtml}</td>
                </tr>
                `;
            }).join('');

            tbody.innerHTML = rows;

            // Attach event listeners
            document.querySelectorAll('#user-transactions .unstake-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const stakeId = btn.getAttribute('data-stake-id');
                    await handleUnstake(stakeId);
                });
            });
        }
        ui.amount.addEventListener('input', updateEstimate);

        // Init
        renderStats();
        loadDashboardData();
        updateEstimate();
        loadBalance();
        loadUserTransactions(publicKey);
    </script>
</body>

</html>
