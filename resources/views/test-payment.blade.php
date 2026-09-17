<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakong KHQR Real Payment Test - RoomDz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <style>
        :root {
            --bg-base: #090d16;
            --card-bg: rgba(18, 24, 38, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --bakong-red: #e02424;
            --bakong-red-glow: rgba(224, 36, 36, 0.35);
            --brand-primary: #6366f1;
            --brand-cyan: #06b6d4;
            --brand-green: #10b981;
            --brand-amber: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-faint: #475569;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: radial-gradient(ellipse 80% 80% at 50% -20%, rgba(99, 102, 241, 0.15), rgba(9, 13, 22, 1) 70%), var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            padding: 32px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        .container {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        @media (min-width: 860px) {
            .container {
                grid-template-columns: 380px 1fr;
                align-items: start;
            }
        }

        /* Header */
        .header-bar {
            grid-column: 1 / -1;
            text-align: center;
            margin-bottom: 8px;
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #cbd5e1;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .brand-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--brand-green);
            box-shadow: 0 0 10px var(--brand-green);
            animation: pulse-dot 2s infinite ease-in-out;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        .header-title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 60%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        .header-desc {
            font-size: 14px;
            color: var(--text-muted);
            max-width: 580px;
            margin: 0 auto;
        }

        /* Glass Panel */
        .glass-panel {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        /* QR Card */
        .qr-card {
            text-align: center;
            position: relative;
        }

        /* Official KHQR Banner */
        .khqr-badge-banner {
            background: linear-gradient(135deg, #e02424 0%, #b91c1c 100%);
            color: #fff;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 1px;
            padding: 8px 16px;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 15px var(--bakong-red-glow);
        }

        .khqr-qr-frame {
            background: #ffffff;
            border-radius: 0 0 16px 16px;
            padding: 18px;
            margin: 0 auto 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            position: relative;
            max-width: 280px;
        }

        .khqr-qr-frame canvas {
            display: block;
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 1/1;
        }

        .expired-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.92);
            border-radius: 0 0 16px 16px;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #f87171;
            font-weight: 700;
            font-size: 14px;
            z-index: 10;
        }

        .expired-overlay.show { display: flex; }

        /* Amount & Receiver */
        .amount-display {
            margin-bottom: 16px;
        }

        .amount-main {
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .amount-main .curr-symbol {
            color: var(--brand-cyan);
            font-size: 24px;
        }

        .receiver-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.25);
            color: #a5b4fc;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            margin-top: 4px;
        }

        /* Status Banner */
        .status-box {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .status-box.pending {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #fbbf24;
        }

        .status-box.checking {
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.25);
            color: #22d3ee;
        }

        .status-box.success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
        }

        .status-box.expired {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .spin-icon {
            display: inline-block;
            animation: spin 1.5s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        /* Action Buttons */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn {
            appearance: none;
            border: none;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        }
        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-simulate {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .btn-simulate:hover {
            background: rgba(16, 185, 129, 0.2);
        }

        /* Controls / Form Panel */
        .form-section-title {
            font-size: 15px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 10px 14px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        /* Quick Amount Pills */
        .pill-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }

        .pill-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: #cbd5e1;
            padding: 8px 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }
        .pill-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .pill-btn.active {
            background: rgba(99, 102, 241, 0.2);
            border-color: var(--brand-primary);
            color: #ffffff;
        }

        /* Currency & Type Toggles */
        .toggle-group {
            display: flex;
            background: rgba(15, 23, 42, 0.6);
            padding: 4px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            gap: 4px;
        }

        .toggle-btn {
            flex: 1;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            color: var(--text-muted);
            cursor: pointer;
            border: none;
            background: transparent;
            transition: all 0.2s;
            text-decoration: none;
        }
        .toggle-btn.active {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        /* Tech Details Accordion */
        .tech-box {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .tech-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
        }
        .tech-label { color: var(--text-muted); }
        .tech-val {
            color: #e2e8f0;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Success Card Popup */
        .success-banner {
            display: none;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.3) 100%);
            border: 1px solid rgba(16, 185, 129, 0.5);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            text-align: left;
            animation: fadeIn 0.4s ease;
        }
        .success-banner.show { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .success-banner h3 {
            font-size: 16px;
            color: #34d399;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .instructions-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 14px 16px;
            margin-top: 16px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .instructions-card ol {
            padding-left: 18px;
            margin-top: 6px;
        }
        .instructions-card li { margin-bottom: 4px; }
        .instructions-card strong { color: #f1f5f9; }
    </style>
</head>
<body>

<div class="container">
    <!-- Top Header -->
    <header class="header-bar">
        <div class="brand-pill">
            <span class="dot"></span>
            Bakong Open API & KHQR Standard
        </div>
        <h1 class="header-title">Bakong Real Payment Tester</h1>
        <p class="header-desc">
            Generate live NBC EMVCo KHQR payloads. Scan with real Bakong App or any Cambodian mobile banking app to test actual money transfers.
        </p>
    </header>

    <!-- LEFT: QR Display & Live Status -->
    <section class="glass-panel qr-card">
        <!-- Success Celebration Banner -->
        <div class="success-banner" id="successBanner">
            <h3>🎉 Payment Verified!</h3>
            <p style="font-size: 13px; color: #e2e8f0; margin-bottom: 4px;">
                Received <strong id="paidAmount" style="color:#34d399;"></strong> into Bakong account.
            </p>
            <p style="font-size: 11px; color: rgba(255,255,255,0.7); font-family: 'JetBrains Mono', monospace; word-break: break-all;">
                Hash: <span id="paidHash">-</span>
            </p>
        </div>

        <!-- Official KHQR Header -->
        <div style="max-width: 280px; margin: 0 auto;">
            <div class="khqr-badge-banner">
                <span>🇰🇭 KHQR</span>
                <span style="font-size: 10px; opacity: 0.85; font-weight: 600;">• BAKONG</span>
            </div>
            <div class="khqr-qr-frame">
                <canvas id="qrCanvas"></canvas>
                <div class="expired-overlay" id="expiredOverlay">
                    <span style="font-size: 28px;">⏳</span>
                    <span>QR Code Expired</span>
                    <button class="btn btn-primary" onclick="regenerateQR()" style="padding: 6px 12px; font-size: 11px;">
                        Regenerate
                    </button>
                </div>
            </div>
        </div>

        <!-- Amount & Receiver Details -->
        <div class="amount-display">
            <div class="amount-main">
                <span class="curr-symbol">{{ $currency === 'KHR' ? '៛' : '$' }}</span>
                <span>{{ $amount }}</span>
                <span style="font-size: 14px; font-weight: 700; color: var(--text-muted); margin-left: 4px;">{{ $currency }}</span>
            </div>
            <div>
                <span class="receiver-chip">
                    👤 {{ $accountId }}
                </span>
            </div>
        </div>

        <!-- Status & Expiry Bar -->
        <div class="status-box pending" id="statusBox">
            <div class="status-indicator">
                <span id="statusSpinner" class="spin-icon">⏳</span>
                <span id="statusText">Waiting for scan...</span>
            </div>
            @if($type === 'dynamic')
            <div style="font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 12px;" id="timerDisplay">
                {{ sprintf('%d:00', $expiryMinutes) }}
            </div>
            @else
            <div style="font-size: 11px; font-weight: 600; opacity: 0.8;">
                Static (No Expiry)
            </div>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <button class="btn btn-primary" onclick="checkStatusManual()">
                <span>🔍</span> Check Status Now
            </button>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button class="btn btn-secondary" onclick="downloadQrImage()">
                    <span>📥</span> Download QR
                </button>
                <button class="btn btn-secondary" onclick="copyRawQr()">
                    <span>📋</span> Copy KHQR
                </button>
            </div>
            <button class="btn btn-simulate" onclick="simulateSuccess()">
                <span>🧪</span> Simulate Paid (Dev Mode)
            </button>
        </div>

        <!-- Technical Metadata -->
        <div class="tech-box">
            <div class="tech-row">
                <span class="tech-label">Bill Number</span>
                <span class="tech-val">{{ $billNumber }}</span>
            </div>
            <div class="tech-row">
                <span class="tech-label">MD5 Hash</span>
                <span class="tech-val" title="{{ $md5 }}">{{ $md5 }}</span>
            </div>
            <div class="tech-row">
                <span class="tech-label">Merchant Name</span>
                <span class="tech-val">{{ $merchantName }}</span>
            </div>
            <div class="tech-row">
                <span class="tech-label">Payment ID</span>
                <span class="tech-val">#{{ $paymentId }}</span>
            </div>
        </div>
    </section>

    <!-- RIGHT: Parameters & Configuration Controls -->
    <section class="glass-panel">
        <div class="form-section-title">
            <span>⚙️ Configure Test Parameters</span>
            <span style="font-size: 11px; color: var(--brand-cyan); font-weight: 600;">Real Money Ready</span>
        </div>

        <form id="qrForm" method="GET" action="/test-payment">
            <!-- QR Mode Toggle -->
            <div class="form-group">
                <label class="form-label">QR Type</label>
                <div class="toggle-group">
                    <a href="javascript:void(0)" onclick="setParam('type', 'dynamic')" class="toggle-btn {{ $type === 'dynamic' ? 'active' : '' }}">
                        ⚡ Dynamic (With Amount & Expiry)
                    </a>
                    <a href="javascript:void(0)" onclick="setParam('type', 'static')" class="toggle-btn {{ $type === 'static' ? 'active' : '' }}">
                        ♾️ Static (Payer Enters Amount)
                    </a>
                </div>
                <input type="hidden" name="type" id="inputType" value="{{ $type }}">
            </div>

            <!-- Currency Toggle -->
            <div class="form-group">
                <label class="form-label">Currency</label>
                <div class="toggle-group">
                    <button type="button" onclick="setCurrency('USD')" class="toggle-btn {{ $currency === 'USD' ? 'active' : '' }}">
                        💵 USD ($)
                    </button>
                    <button type="button" onclick="setCurrency('KHR')" class="toggle-btn {{ $currency === 'KHR' ? 'active' : '' }}">
                        🇰🇭 KHR (៛ Riel)
                    </button>
                </div>
                <input type="hidden" name="currency" id="inputCurrency" value="{{ $currency }}">
            </div>

            <!-- Amount Section -->
            @if($type === 'dynamic')
            <div class="form-group">
                <label class="form-label">Quick Amount</label>
                @if($currency === 'USD')
                <div class="pill-grid">
                    <button type="button" class="pill-btn {{ $rawAmount == 0.01 ? 'active' : '' }}" onclick="setAmount(0.01)">$0.01 (Min)</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 0.10 ? 'active' : '' }}" onclick="setAmount(0.10)">$0.10</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 1.00 ? 'active' : '' }}" onclick="setAmount(1.00)">$1.00</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 5.00 ? 'active' : '' }}" onclick="setAmount(5.00)">$5.00</button>
                </div>
                @else
                <div class="pill-grid">
                    <button type="button" class="pill-btn {{ $rawAmount == 100 ? 'active' : '' }}" onclick="setAmount(100)">100 ៛</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 500 ? 'active' : '' }}" onclick="setAmount(500)">500 ៛</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 1000 ? 'active' : '' }}" onclick="setAmount(1000)">1,000 ៛</button>
                    <button type="button" class="pill-btn {{ $rawAmount == 4000 ? 'active' : '' }}" onclick="setAmount(4000)">4,000 ៛</button>
                </div>
                @endif
                <input type="number" step="any" name="amount" id="inputAmount" class="form-input" value="{{ $rawAmount }}" placeholder="Enter custom amount">
            </div>
            @endif

            <!-- Receiver Bakong ID -->
            <div class="form-group">
                <label class="form-label">
                    Receiver Bakong Account ID
                    <span style="color:var(--brand-amber); font-size:10px;">(Must exist in Bakong)</span>
                </label>
                <input type="text" name="account_id" id="inputAccountId" class="form-input" value="{{ $accountId }}" placeholder="e.g. mean_chandara@bkrt or phone@aba" required>
                <div style="font-size: 11px; color: var(--text-faint); margin-top: 4px;">
                    💡 Funds paid during this test will go directly to this Bakong account.
                </div>
            </div>

            <!-- Merchant Name -->
            <div class="form-group">
                <label class="form-label">Merchant Name (Shown in Bank App)</label>
                <input type="text" name="merchant_name" id="inputMerchantName" class="form-input" value="{{ $merchantName }}" maxlength="25" placeholder="e.g. RoomDz">
            </div>

            <!-- Expiry Duration -->
            @if($type === 'dynamic')
            <div class="form-group">
                <label class="form-label">Expiration (Minutes)</label>
                <input type="number" min="1" max="60" name="expiry" id="inputExpiry" class="form-input" value="{{ $expiryMinutes }}">
            </div>
            @endif

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px;">
                🔄 Generate & Apply New KHQR
            </button>
        </form>

        <!-- Instructions Guide -->
        <div class="instructions-card">
            <strong>📱 How to Test Real Payment:</strong>
            <ol>
                <li>Open your <strong>Bakong App</strong>, <strong>ABA Mobile</strong>, <strong>Wing Bank</strong>, <strong>ACLEDA</strong>, or any Cambodian banking app.</li>
                <li>Tap <strong>Scan QR</strong> and point camera at the QR code on the left.</li>
                <li>Verify that the merchant name shows <strong>"{{ $merchantName }}"</strong> and amount shows <strong>{{ $amount }} {{ $currency }}</strong>.</li>
                <li>Confirm payment. The funds transfer in real time via the National Bank of Cambodia network!</li>
                <li>This page will automatically detect the payment and show the confirmation.</li>
            </ol>
        </div>
    </section>
</div>

<script>
    const qrString = @json($qrString);
    const paymentId = @json($paymentId);
    const expiryMinutes = @json($expiryMinutes);
    const isDynamic = @json($type === 'dynamic');
    const currency = @json($currency);

    // Render Crisp KHQR Code
    const qr = new QRious({
        element: document.getElementById('qrCanvas'),
        value: qrString,
        size: 500,
        level: 'M',
        background: '#ffffff',
        foreground: '#000000',
        padding: 0
    });

    // Copy KHQR String
    function copyRawQr() {
        navigator.clipboard.writeText(qrString).then(() => {
            alert('Copied raw KHQR payload to clipboard!');
        }).catch(() => {
            prompt('Copy KHQR String:', qrString);
        });
    }

    // Download QR Image
    function downloadQrImage() {
        const canvas = document.getElementById('qrCanvas');
        const link = document.createElement('a');
        link.download = `KHQR-${paymentId}-${currency}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    // UI Form Helpers
    function setParam(key, val) {
        document.getElementById('inputType').value = val;
        document.getElementById('qrForm').submit();
    }

    function setCurrency(curr) {
        document.getElementById('inputCurrency').value = curr;
        if (curr === 'KHR') {
            document.getElementById('inputAmount').value = 500;
        } else {
            document.getElementById('inputAmount').value = 0.01;
        }
        document.getElementById('qrForm').submit();
    }

    function setAmount(amt) {
        document.getElementById('inputAmount').value = amt;
        document.getElementById('qrForm').submit();
    }

    function regenerateQR() {
        location.reload();
    }

    // Live Polling for Payment Status
    let pollingInterval = null;
    let isCompleted = false;

    function startStatusPolling() {
        if (isCompleted) return;

        pollingInterval = setInterval(async () => {
            if (isCompleted) {
                clearInterval(pollingInterval);
                return;
            }
            await checkStatus(false);
        }, 3000);
    }

    async function checkStatus(isManual = false) {
        if (isCompleted) return;

        const statusBox = document.getElementById('statusBox');
        const statusText = document.getElementById('statusText');
        const statusSpinner = document.getElementById('statusSpinner');

        if (isManual) {
            statusText.textContent = 'Checking Bakong API...';
            statusSpinner.textContent = '🔍';
            statusBox.className = 'status-box checking';
        }

        try {
            const res = await fetch(`/api/payments/${paymentId}/status`);
            const data = await res.json();

            if (data.is_paid || data.status === 'completed') {
                isCompleted = true;
                clearInterval(pollingInterval);

                // Update UI to success
                statusBox.className = 'status-box success';
                statusText.textContent = 'Payment Completed!';
                statusSpinner.textContent = '✅';

                document.getElementById('successBanner').classList.add('show');
                document.getElementById('paidAmount').textContent = `${data.data?.amount ?? ''} ${data.data?.currency ?? currency}`;
                document.getElementById('paidHash').textContent = data.data?.bakong_hash ?? 'Verified via NBC Bakong';

                // Confetti celebration
                try {
                    confetti({
                        particleCount: 120,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                } catch (e) {}

                return;
            }

            if (data.status === 'expired') {
                isCompleted = true;
                clearInterval(pollingInterval);
                statusBox.className = 'status-box expired';
                statusText.textContent = 'QR Expired';
                statusSpinner.textContent = '⏰';
                document.getElementById('expiredOverlay').classList.add('show');
                return;
            }

            if (isManual) {
                setTimeout(() => {
                    if (!isCompleted) {
                        statusBox.className = 'status-box pending';
                        statusText.textContent = 'Waiting for scan...';
                        statusSpinner.textContent = '⏳';
                    }
                }, 1200);
            }
        } catch (e) {
            console.warn('Status check error:', e);
            if (isManual) {
                statusBox.className = 'status-box pending';
                statusText.textContent = 'Waiting for scan...';
                statusSpinner.textContent = '⏳';
            }
        }
    }

    function checkStatusManual() {
        checkStatus(true);
    }

    // Simulate Success for Dev Testing
    async function simulateSuccess() {
        try {
            const res = await fetch(`/api/payments/${paymentId}/simulate-success`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                checkStatus(true);
            }
        } catch (e) {
            alert('Failed to simulate success: ' + e.message);
        }
    }

    // Countdown Timer for Dynamic QR
    if (isDynamic) {
        let remainingSeconds = expiryMinutes * 60;
        const timerDisplay = document.getElementById('timerDisplay');
        const expiredOverlay = document.getElementById('expiredOverlay');
        const statusBox = document.getElementById('statusBox');
        const statusText = document.getElementById('statusText');
        const statusSpinner = document.getElementById('statusSpinner');

        const timerTick = setInterval(() => {
            if (isCompleted) {
                clearInterval(timerTick);
                return;
            }

            if (remainingSeconds <= 0) {
                clearInterval(timerTick);
                clearInterval(pollingInterval);
                if (timerDisplay) timerDisplay.textContent = 'Expired';
                expiredOverlay.classList.add('show');
                statusBox.className = 'status-box expired';
                statusText.textContent = 'QR Expired';
                statusSpinner.textContent = '⏰';
                return;
            }

            remainingSeconds--;
            const mins = Math.floor(remainingSeconds / 60);
            const secs = remainingSeconds % 60;
            if (timerDisplay) {
                timerDisplay.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            }
        }, 1000);
    }

    // Start auto polling immediately
    startStatusPolling();
</script>

</body>
</html>
