<x-app-layout>
    <x-slot name="title">SMS Blast Dashboard</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SMS Blast</h2>
            <div id="connectionStatus" class="flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-500 text-sm">
                <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                Idle
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ session('error') }}</div>
            @endif

            {{-- SMS Permission Check --}}
            <div id="permissionAlert" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-lg text-sm" style="display: none;">
                <strong>SMS Permission Required</strong> - Please grant SMS permission to send messages.
                <button onclick="requestSmsPermission()" class="ml-2 underline font-semibold">Grant Permission</button>
            </div>

            {{-- Tab Navigation --}}
            <div class="flex mb-4 bg-white rounded-xl shadow-sm border overflow-hidden">
                <button id="tabDashboard" onclick="switchTab('dashboard')"
                    class="flex-1 py-3 text-sm font-semibold text-center transition-all bg-indigo-600 text-white">
                    Dashboard
                </button>
                <button id="tabSettings" onclick="switchTab('settings')"
                    class="flex-1 py-3 text-sm font-semibold text-center transition-all bg-white text-gray-500 hover:bg-gray-50">
                    Settings
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- DASHBOARD TAB --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div id="panelDashboard">

                {{-- Stats Cards --}}
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                        <div id="statPending" class="text-3xl font-bold text-blue-600">{{ (int)($assignedStats->pending ?? 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Pending</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                        <div id="statSent" class="text-3xl font-bold text-green-600">{{ (int)($assignedStats->sent ?? 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Sent</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                        <div id="statFailed" class="text-3xl font-bold text-red-600">{{ (int)($assignedStats->failed ?? 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Failed</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                        <div id="statTotal" class="text-3xl font-bold text-gray-700">{{ (int)($assignedStats->total ?? 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total Assigned</div>
                    </div>
                </div>

                {{-- Progress Bar --}}
                @php
                    $total = (int)($assignedStats->total ?? 0);
                    $done = (int)($assignedStats->sent ?? 0) + (int)($assignedStats->failed ?? 0);
                    $pct = $total > 0 ? round($done / $total * 100) : 0;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>Progress</span>
                        <span id="progressText">{{ $done }} / {{ $total }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div id="progressBar" class="h-full rounded-full bg-gradient-to-r from-green-500 to-blue-500 transition-all duration-500" style="width: {{ $pct }}%;"></div>
                    </div>
                    <div class="text-center text-sm text-gray-500 mt-1" id="progressPct">{{ $pct }}%</div>
                </div>

                {{-- Active Campaigns --}}
                @if($campaigns->count() > 0)
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Active Campaigns</h3>
                    @foreach($campaigns as $campaign)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b' : '' }}">
                        <div>
                            <div class="font-medium text-gray-900 text-sm">{{ $campaign->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $campaign->sendLogs()->where('assigned_to', $user->id)->where('status', 'queued')->count() }} pending
                            </div>
                        </div>
                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">Sending</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="bg-white rounded-xl shadow-sm border p-8 mb-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-500 text-sm">No active campaigns assigned to you.</p>
                    <p class="text-gray-400 text-xs mt-1">Waiting for campaigns to be started by your manager...</p>
                </div>
                @endif

                {{-- Control Buttons --}}
                <div class="space-y-3 mb-6">
                    <button id="btnStart" onclick="startBlast()"
                        class="w-full py-4 rounded-xl text-white font-bold text-lg transition-all shadow-sm
                        {{ ($assignedStats->pending ?? 0) > 0 ? 'bg-green-500 hover:bg-green-600 active:scale-[0.98]' : 'bg-gray-300 cursor-not-allowed' }}"
                        {{ ($assignedStats->pending ?? 0) == 0 ? 'disabled' : '' }}>
                        &#9654; Start Sending
                    </button>

                    <div class="grid grid-cols-2 gap-3" id="activeControls" style="display: none;">
                        <button id="btnPause" onclick="pauseBlast()"
                            class="py-3 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white font-bold text-base active:scale-[0.98] transition-all shadow-sm">
                            &#9646;&#9646; Pause
                        </button>
                        <button id="btnStop" onclick="stopBlast()"
                            class="py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold text-base active:scale-[0.98] transition-all shadow-sm">
                            &#9632; Stop
                        </button>
                    </div>
                </div>

                {{-- Live Log --}}
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Live Log</h3>
                    <div id="liveLog" class="h-48 overflow-y-auto text-xs font-mono bg-gray-50 rounded-lg p-3 space-y-1">
                        <div class="text-gray-400">Ready. Press Start to begin sending.</div>
                    </div>
                </div>

            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- SETTINGS TAB --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            <div id="panelSettings" style="display: none;">

                {{-- Send Mode --}}
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Send Mode</h3>
                    <p class="text-xs text-gray-400 mb-3">Controls whether to start sending automatically or manually.</p>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all hover:bg-gray-50" id="labelModeManual">
                            <input type="radio" name="sendMode" value="manual" checked class="mt-0.5 accent-indigo-600" onchange="saveSetting('sendMode', 'manual')">
                            <div>
                                <div class="text-sm font-medium text-gray-800">Manual</div>
                                <div class="text-xs text-gray-500">Press "Start Sending" to begin. Full control.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all hover:bg-gray-50" id="labelModeAuto">
                            <input type="radio" name="sendMode" value="auto" class="mt-0.5 accent-indigo-600" onchange="saveSetting('sendMode', 'auto')">
                            <div>
                                <div class="text-sm font-medium text-gray-800">Auto</div>
                                <div class="text-xs text-gray-500">Automatically starts sending when pending messages are detected. Polls every 30s.</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Send Confirmation --}}
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Send Confirmation</h3>
                    <p class="text-xs text-gray-400 mb-3">How strictly to verify each SMS was sent before moving to the next.</p>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all hover:bg-gray-50">
                            <input type="radio" name="confirmMode" value="0" checked class="mt-0.5 accent-indigo-600" onchange="saveSetting('confirmMode', '0')">
                            <div>
                                <div class="text-sm font-medium text-gray-800">Fire & Forget</div>
                                <div class="text-xs text-gray-500">Fastest. Queues SMS and moves on immediately. No confirmation if it actually sent.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all hover:bg-gray-50">
                            <input type="radio" name="confirmMode" value="1" class="mt-0.5 accent-indigo-600" onchange="saveSetting('confirmMode', '1')">
                            <div>
                                <div class="text-sm font-medium text-gray-800">Confirm Sending</div>
                                <div class="text-xs text-gray-500">Medium. Waits up to 5s to confirm the SMS is being sent. Moves on if sending or sent.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all hover:bg-gray-50">
                            <input type="radio" name="confirmMode" value="2" class="mt-0.5 accent-indigo-600" onchange="saveSetting('confirmMode', '2')">
                            <div>
                                <div class="text-sm font-medium text-gray-800">Confirm Sent</div>
                                <div class="text-xs text-gray-500">Slowest but most accurate. Waits up to 15s for full confirmation before next message.</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Delay Between SMS --}}
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Delay Between SMS (seconds)</h3>
                    <p class="text-xs text-gray-400 mb-3">Wait time between each SMS send. Higher = safer for SIM, lower = faster.</p>
                    <input type="range" id="throttleDelay" min="3" max="60" value="10" class="w-full accent-indigo-600"
                        oninput="document.getElementById('throttleValue').textContent = this.value + 's'; saveSetting('throttleDelay', this.value);">
                    <div class="text-center text-sm text-gray-500 mt-1" id="throttleValue">10s</div>
                </div>

                {{-- Batch Size --}}
                <div class="bg-white rounded-xl shadow-sm border p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Batch Size</h3>
                    <p class="text-xs text-gray-400 mb-3">How many messages to pull from server per batch.</p>
                    <input type="range" id="batchSize" min="1" max="20" value="5" class="w-full accent-indigo-600"
                        oninput="document.getElementById('batchValue').textContent = this.value; saveSetting('batchSize', this.value);">
                    <div class="text-center text-sm text-gray-500 mt-1" id="batchValue">5</div>
                </div>

                {{-- Settings Info --}}
                <div class="bg-gray-50 rounded-xl border border-dashed p-4 text-xs text-gray-400 text-center">
                    Settings are saved locally on this device.
                </div>

            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        // ─── State ──────────────────────────────────────────────
        let isBlasting = false;
        let isPaused = false;
        let blastAborted = false;
        let sentCount = 0;
        let failedCount = 0;
        let autoModeInterval = null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // ─── Settings (localStorage) ────────────────────────────
        function saveSetting(key, value) {
            localStorage.setItem('blast_' + key, value);
        }
        function getSetting(key, defaultVal) {
            return localStorage.getItem('blast_' + key) || defaultVal;
        }
        function loadSettings() {
            let mode = getSetting('sendMode', 'manual');
            document.querySelector('input[name="sendMode"][value="' + mode + '"]').checked = true;

            let confirm = getSetting('confirmMode', '0');
            document.querySelector('input[name="confirmMode"][value="' + confirm + '"]').checked = true;

            let delay = getSetting('throttleDelay', '10');
            document.getElementById('throttleDelay').value = delay;
            document.getElementById('throttleValue').textContent = delay + 's';

            let batch = getSetting('batchSize', '5');
            document.getElementById('batchSize').value = batch;
            document.getElementById('batchValue').textContent = batch;

            // Start auto mode if enabled
            if (mode === 'auto') {
                startAutoMode();
            }
        }

        // ─── Tab Switching ──────────────────────────────────────
        function switchTab(tab) {
            if (tab === 'dashboard') {
                document.getElementById('panelDashboard').style.display = 'block';
                document.getElementById('panelSettings').style.display = 'none';
                document.getElementById('tabDashboard').className = 'flex-1 py-3 text-sm font-semibold text-center transition-all bg-indigo-600 text-white';
                document.getElementById('tabSettings').className = 'flex-1 py-3 text-sm font-semibold text-center transition-all bg-white text-gray-500 hover:bg-gray-50';
            } else {
                document.getElementById('panelDashboard').style.display = 'none';
                document.getElementById('panelSettings').style.display = 'block';
                document.getElementById('tabSettings').className = 'flex-1 py-3 text-sm font-semibold text-center transition-all bg-indigo-600 text-white';
                document.getElementById('tabDashboard').className = 'flex-1 py-3 text-sm font-semibold text-center transition-all bg-white text-gray-500 hover:bg-gray-50';
            }
        }

        // ─── Auto Mode ──────────────────────────────────────────
        function startAutoMode() {
            if (autoModeInterval) return;
            addLog('Auto mode enabled. Polling for messages every 30s...');
            updateConnectionStatus('auto');
            autoModeCheck(); // Check immediately
            autoModeInterval = setInterval(autoModeCheck, 30000);
        }

        function stopAutoMode() {
            if (autoModeInterval) {
                clearInterval(autoModeInterval);
                autoModeInterval = null;
            }
        }

        async function autoModeCheck() {
            if (isBlasting) return; // Already sending, skip
            try {
                let resp = await fetch('/sms/blast-status');
                let data = await resp.json();
                updateStatsUI(data);
                if (data.pending > 0) {
                    addLog('Auto mode: ' + data.pending + ' pending messages detected. Starting...');
                    startBlast();
                }
            } catch (e) {
                // Silent fail for auto check
            }
        }

        // ─── Main Blast Flow ────────────────────────────────────

        async function startBlast() {
            if (isBlasting) return;

            if (!window.TeleSMS || !window.TeleSMS.sendSmsMessage) {
                addLog('Error: Android bridge not available. Open this page in the TeleSMS app.');
                return;
            }

            isBlasting = true;
            isPaused = false;
            blastAborted = false;

            let throttle = parseInt(document.getElementById('throttleDelay').value) || 10;
            let confirmMode = parseInt(getSetting('confirmMode', '0'));
            let batchSize = parseInt(document.getElementById('batchSize').value) || 5;

            document.getElementById('btnStart').style.display = 'none';
            document.getElementById('activeControls').style.display = 'grid';
            updateConnectionStatus('sending');

            let modeNames = ['Fire & Forget', 'Confirm Sending', 'Confirm Sent'];
            addLog('Starting SMS blast (throttle: ' + throttle + 's, mode: ' + modeNames[confirmMode] + ', batch: ' + batchSize + ')...');

            await blastLoop(throttle, confirmMode, batchSize);
        }

        async function blastLoop(throttle, confirmMode, batchSize) {
            while (isBlasting && !blastAborted) {
                while (isPaused && isBlasting) {
                    await sleep(1000);
                }
                if (!isBlasting || blastAborted) break;

                try {
                    addLog('Pulling messages from server...');
                    let pullResponse = await fetch('/sms/blast-pull', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ batch_size: batchSize })
                    });

                    if (!pullResponse.ok) {
                        addLog('Error: Server returned ' + pullResponse.status + '. Retrying in 10s...');
                        await sleep(10000);
                        continue;
                    }

                    let pullData = await pullResponse.json();
                    let messages = pullData.messages || [];

                    if (messages.length === 0) {
                        addLog('No more messages in queue.');
                        refreshStatus();

                        // If auto mode, just stop blasting and let auto poll restart later
                        if (getSetting('sendMode', 'manual') === 'auto') {
                            addLog('Auto mode: Waiting for new messages...');
                            isBlasting = false;
                            document.getElementById('btnStart').style.display = 'block';
                            document.getElementById('btnStart').innerHTML = '&#9654; Start Sending';
                            document.getElementById('btnStart').disabled = false;
                            document.getElementById('btnStart').className = 'w-full py-4 rounded-xl text-white font-bold text-lg transition-all shadow-sm bg-green-500 hover:bg-green-600 active:scale-[0.98]';
                            document.getElementById('btnStart').onclick = startBlast;
                            document.getElementById('activeControls').style.display = 'none';
                            updateConnectionStatus('auto');
                            break;
                        }

                        // Manual mode: wait and recheck
                        addLog('Checking again in 15s...');
                        await sleep(15000);
                        let recheck = await fetch('/sms/blast-pull', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ batch_size: 1 })
                        });
                        let recheckData = await recheck.json();
                        if (!recheckData.messages || recheckData.messages.length === 0) {
                            addLog('All messages processed! Blast complete.');
                            stopBlast();
                            break;
                        }
                        continue;
                    }

                    addLog('Pulled ' + messages.length + ' messages. Sending...');

                    for (let i = 0; i < messages.length; i++) {
                        if (!isBlasting || blastAborted) break;
                        while (isPaused && isBlasting) {
                            await sleep(1000);
                        }
                        if (!isBlasting || blastAborted) break;

                        let msg = messages[i];
                        let phone = msg.phone_number;
                        let body = msg.message;
                        let logId = msg.log_id;

                        addLog('Sending to ' + phone + '...');

                        // Call JS bridge with the selected confirmation mode
                        let result = 'error:bridge not available';
                        try {
                            if (confirmMode > 0 && window.TeleSMS.sendSmsWithMode) {
                                result = window.TeleSMS.sendSmsWithMode(phone, body, confirmMode);
                            } else {
                                result = window.TeleSMS.sendSmsMessage(phone, body);
                            }
                        } catch (e) {
                            result = 'error:' + e.message;
                        }

                        let status = (result === 'ok') ? 'sent' : 'failed';
                        let errorMsg = (result !== 'ok') ? result.replace('error:', '') : null;

                        if (status === 'sent') {
                            sentCount++;
                            addLog('Sent to ' + phone + ' ✓');
                        } else {
                            failedCount++;
                            addLog('Failed to ' + phone + ': ' + errorMsg);
                        }

                        // Report result back to server
                        try {
                            await fetch('/sms/blast-report', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    results: [{
                                        log_id: logId,
                                        status: status,
                                        error_message: errorMsg
                                    }]
                                })
                            });
                        } catch (e) {
                            addLog('Warning: Failed to report result: ' + e.message);
                        }

                        updateLocalStats();

                        if (i < messages.length - 1) {
                            addLog('Waiting ' + throttle + 's...');
                            await sleep(throttle * 1000);
                        }
                    }

                    refreshStatus();
                    await sleep(2000);

                } catch (err) {
                    addLog('Error in blast loop: ' + err.message);
                    await sleep(10000);
                }
            }
        }

        function pauseBlast() {
            isPaused = true;
            updateConnectionStatus('paused');
            addLog('SMS blast paused.');
            document.getElementById('btnStart').style.display = 'block';
            document.getElementById('btnStart').innerHTML = '&#9654; Resume Sending';
            document.getElementById('btnStart').disabled = false;
            document.getElementById('btnStart').className = 'w-full py-4 rounded-xl text-white font-bold text-lg transition-all shadow-sm bg-green-500 hover:bg-green-600 active:scale-[0.98]';
            document.getElementById('btnStart').onclick = resumeBlast;
            document.getElementById('activeControls').style.display = 'none';
        }

        function resumeBlast() {
            isPaused = false;
            updateConnectionStatus('sending');
            addLog('SMS blast resumed.');
            document.getElementById('btnStart').style.display = 'none';
            document.getElementById('activeControls').style.display = 'grid';
            document.getElementById('btnStart').onclick = startBlast;
        }

        function stopBlast() {
            isBlasting = false;
            blastAborted = true;
            isPaused = false;
            updateConnectionStatus(getSetting('sendMode', 'manual') === 'auto' ? 'auto' : 'idle');
            addLog('SMS blast stopped. Sent: ' + sentCount + ', Failed: ' + failedCount);
            document.getElementById('btnStart').style.display = 'block';
            document.getElementById('btnStart').innerHTML = '&#9654; Start Sending';
            document.getElementById('btnStart').disabled = false;
            document.getElementById('btnStart').className = 'w-full py-4 rounded-xl text-white font-bold text-lg transition-all shadow-sm bg-green-500 hover:bg-green-600 active:scale-[0.98]';
            document.getElementById('btnStart').onclick = startBlast;
            document.getElementById('activeControls').style.display = 'none';
        }

        // ─── Helpers ────────────────────────────────────────────

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function updateLocalStats() {
            document.getElementById('statSent').textContent = sentCount;
            document.getElementById('statFailed').textContent = failedCount;
            let pending = parseInt(document.getElementById('statPending').textContent) || 0;
            if (pending > 0) pending--;
            document.getElementById('statPending').textContent = pending;
            let total = parseInt(document.getElementById('statTotal').textContent) || 0;
            let done = sentCount + failedCount;
            let pct = total > 0 ? Math.round(done / total * 100) : 0;
            document.getElementById('progressText').textContent = done + ' / ' + total;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressPct').textContent = pct + '%';
        }

        function updateStatsUI(data) {
            document.getElementById('statPending').textContent = data.pending;
            document.getElementById('statSent').textContent = data.sent;
            document.getElementById('statFailed').textContent = data.failed;
            document.getElementById('statTotal').textContent = data.total;
            sentCount = data.sent;
            failedCount = data.failed;
            let done = data.sent + data.failed;
            let pct = data.total > 0 ? Math.round(done / data.total * 100) : 0;
            document.getElementById('progressText').textContent = done + ' / ' + data.total;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressPct').textContent = pct + '%';
        }

        function refreshStatus() {
            fetch('/sms/blast-status')
            .then(r => r.json())
            .then(data => {
                updateStatsUI(data);
                if (data.pending === 0 && isBlasting) {
                    stopBlast();
                    addLog('All messages processed! Blast complete.');
                }
            })
            .catch(err => {
                addLog('Warning: Status refresh failed: ' + err.message);
            });
        }

        function updateConnectionStatus(status) {
            const el = document.getElementById('connectionStatus');
            if (status === 'sending') {
                el.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse inline-block"></span> Sending...';
                el.className = 'flex items-center gap-2 px-3 py-1 rounded-full bg-green-50 text-green-700 text-sm';
            } else if (status === 'paused') {
                el.innerHTML = '<span class="w-2 h-2 rounded-full bg-yellow-500 inline-block"></span> Paused';
                el.className = 'flex items-center gap-2 px-3 py-1 rounded-full bg-yellow-50 text-yellow-700 text-sm';
            } else if (status === 'auto') {
                el.innerHTML = '<span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse inline-block"></span> Auto';
                el.className = 'flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm';
            } else {
                el.innerHTML = '<span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span> Idle';
                el.className = 'flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-500 text-sm';
            }
        }

        function addLog(msg) {
            const log = document.getElementById('liveLog');
            const time = new Date().toLocaleTimeString('en-PH', { hour12: false });
            const div = document.createElement('div');
            div.textContent = '[' + time + '] ' + msg;
            if (msg.includes('Warning')) div.className = 'text-yellow-600';
            else if (msg.includes('complete') || msg.includes('Sent') || msg.includes('✓')) div.className = 'text-green-600';
            else if (msg.includes('Failed') || msg.includes('Error')) div.className = 'text-red-600';
            else if (msg.includes('Auto mode')) div.className = 'text-blue-600';
            else div.className = 'text-gray-600';
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function requestSmsPermission() {
            if (window.TeleSMS && window.TeleSMS.requestPermissions) {
                window.TeleSMS.requestPermissions();
            }
        }

        // ─── Init ───────────────────────────────────────────────
        loadSettings();

        // Auto-refresh stats every 30s when idle (and not in auto mode)
        setInterval(function() {
            if (!isBlasting && getSetting('sendMode', 'manual') !== 'auto') {
                refreshStatus();
            }
        }, 30000);

        // Watch for send mode changes
        document.querySelectorAll('input[name="sendMode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'auto') {
                    startAutoMode();
                } else {
                    stopAutoMode();
                    updateConnectionStatus('idle');
                }
            });
        });

    </script>
    @endpush
</x-app-layout>
