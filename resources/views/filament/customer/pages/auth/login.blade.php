<div class="he-auth-root">
<style>
    body:has(.he-auth-root) {
        overflow: hidden;
        background: #f8fafc;
    }

    .dark body:has(.he-auth-root),
    body.dark:has(.he-auth-root) {
        background: #020617;
    }

    .fi-simple-layout {
        height: 100svh !important;
        min-height: 100svh !important;
        overflow: hidden !important;
        padding: 0 !important;
        background: transparent !important;
        border-radius: 0 !important;
    }

    .dark .fi-simple-layout {
        background: transparent !important;
    }

    .fi-simple-main-ctn,
    .fi-simple-main {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        height: 100svh !important;
        min-height: 100svh !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    .he-auth-root {
        --he-primary: #2563eb;
        --he-primary-hover: #1d4ed8;
        --he-panel-bg: #ffffff;
        --he-panel-text: #0f172a;
        --he-muted: #64748b;
        --he-border: #e2e8f0;
        --he-input-bg: #ffffff;
        --he-placeholder: #94a3b8;
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        overflow: hidden;
        background: var(--he-panel-bg);
        color: var(--he-panel-text);
        border-radius: 0 !important;
    }

    .dark .he-auth-root {
        --he-panel-bg: #020617;
        --he-panel-text: #f8fafc;
        --he-muted: #94a3b8;
        --he-border: #334155;
        --he-input-bg: #0f172a;
        --he-placeholder: #64748b;
    }

    .he-auth-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(400px, .82fr);
        width: 100%;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        background: var(--he-panel-bg);
        border-radius: 0 !important;
    }

    .he-auth-brand {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 28px;
        min-width: 0;
        padding: 48px 56px;
        overflow: hidden;
        border-radius: 0 !important;
        background:
            linear-gradient(135deg, rgba(15, 70, 217, .96) 0%, rgba(37, 99, 235, .94) 38%, rgba(124, 58, 237, .9) 68%, rgba(236, 72, 153, .92) 100%),
            #1d4ed8;
        color: #ffffff;
    }

    .he-auth-brand::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(rgba(255, 255, 255, .11) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, .11) 1px, transparent 1px);
        background-size: 40px 40px;
        mask-image: linear-gradient(120deg, rgba(0, 0, 0, .76), transparent 72%);
        pointer-events: none;
    }

    .he-auth-brand > * {
        position: relative;
        z-index: 1;
    }

    .he-brand-mark,
    .he-mobile-logo {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .he-brand-mark img,
    .he-mobile-logo img {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        background: #ffffff;
        object-fit: contain;
        padding: 5px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .22);
    }

    .he-brand-name {
        color: #ffffff;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: 0;
    }

    .he-brand-caption {
        margin-top: 3px;
        color: rgba(255, 255, 255, .78);
        font-size: 12px;
        font-weight: 650;
        letter-spacing: 0;
    }

    .he-auth-copy {
        max-width: 560px;
    }

    .he-auth-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        padding: 7px 10px;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 999px;
        background: rgba(255, 255, 255, .13);
        color: rgba(255, 255, 255, .92);
        font-size: 12px;
        font-weight: 750;
        letter-spacing: 0;
    }

    .he-auth-kicker::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, .2);
    }

    .he-auth-title {
        max-width: 560px;
        margin: 0;
        color: #ffffff;
        font-size: 50px;
        font-weight: 850;
        line-height: 1;
        letter-spacing: 0;
    }

    .he-auth-text {
        max-width: 500px;
        margin: 18px 0 0;
        color: rgba(255, 255, 255, .86);
        font-size: 15px;
        line-height: 1.6;
    }

    .he-feature-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        max-width: 640px;
    }

    .he-feature {
        min-height: 86px;
        padding: 13px;
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 8px;
        background: rgba(255, 255, 255, .12);
        backdrop-filter: blur(10px);
    }

    .he-feature-index {
        color: rgba(255, 255, 255, .66);
        font-size: 11px;
        font-weight: 800;
        line-height: 1;
    }

    .he-feature-title {
        margin-top: 8px;
        color: #ffffff;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.22;
    }

    .he-feature-text {
        margin-top: 5px;
        color: rgba(255, 255, 255, .78);
        font-size: 12px;
        line-height: 1.42;
    }

    .he-auth-form-side {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        padding: 42px 44px;
        overflow: hidden;
        background: var(--he-panel-bg);
    }

    .he-auth-panel {
        width: min(100%, 420px);
    }

    .he-mobile-logo {
        display: none;
        margin-bottom: 24px;
    }

    .he-mobile-brand-name {
        color: var(--he-panel-text);
    }

    .he-mobile-brand-caption {
        color: var(--he-muted);
    }

    .he-form-eyebrow {
        color: var(--he-primary);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .he-form-title {
        margin: 10px 0 0;
        color: var(--he-panel-text);
        font-size: 29px;
        font-weight: 850;
        line-height: 1.16;
        letter-spacing: 0;
    }

    .he-form-subtitle {
        margin: 10px 0 24px;
        color: var(--he-muted);
        font-size: 14px;
        line-height: 1.55;
    }

    .he-login-card {
        padding: 0;
    }

    .he-login-card .fi-fo {
        gap: 16px;
    }

    .he-login-card .fi-fo-field-wrp-label,
    .he-login-card .fi-fo-field-wrp-label span,
    .he-login-card label {
        color: var(--he-panel-text) !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .he-login-card .fi-fo-field-wrp-label .fi-fo-field-wrp-required-mark {
        color: #ec4899 !important;
    }

    .he-login-card .fi-fo-field-wrp-error-message {
        color: #ef4444 !important;
    }

    .he-login-card .fi-input-wrp {
        border: 1px solid var(--he-border) !important;
        border-radius: 8px !important;
        background: var(--he-input-bg) !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05) !important;
    }

    .he-login-card .fi-input-wrp:focus-within {
        border-color: var(--he-primary) !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .14) !important;
    }

    .he-login-card .fi-input,
    .he-login-card input {
        color: var(--he-panel-text) !important;
        caret-color: var(--he-primary);
        background: transparent !important;
    }

    .he-login-card .fi-input::placeholder,
    .he-login-card input::placeholder {
        color: var(--he-placeholder) !important;
        opacity: 1;
    }

    .he-login-card .fi-icon-btn,
    .he-login-card .fi-input-wrp-icon {
        color: var(--he-muted) !important;
    }

    .he-login-card .fi-checkbox-input {
        border-color: var(--he-border) !important;
        background: var(--he-input-bg) !important;
    }

    .he-login-card .fi-checkbox-input:checked {
        border-color: var(--he-primary) !important;
        background-color: var(--he-primary) !important;
    }

    .he-login-card a {
        color: var(--he-primary) !important;
    }

    .he-login-card .fi-btn,
    .he-login-card .fi-btn.fi-color-primary {
        min-height: 44px;
        border: 0 !important;
        border-radius: 8px !important;
        background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
        color: #ffffff !important;
        font-weight: 800;
        box-shadow: 0 12px 24px rgba(37, 99, 235, .22) !important;
    }

    .he-login-card .fi-btn:hover,
    .he-login-card .fi-btn.fi-color-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #2563eb) !important;
    }

    .he-login-card .fi-btn-label {
        color: #ffffff !important;
    }

    .he-auth-support {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-top: 22px;
        padding-top: 16px;
        border-top: 1px solid var(--he-border);
        color: var(--he-muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .he-auth-support strong {
        display: block;
        color: var(--he-panel-text);
        font-size: 13px;
    }

    .he-status-pill {
        flex: none;
        padding: 6px 9px;
        border-radius: 999px;
        background: rgba(37, 99, 235, .1);
        color: var(--he-primary);
        font-size: 11px;
        font-weight: 800;
    }

    @media (max-width: 1280px) {
        .he-auth-brand {
            padding: 40px 44px;
        }

        .he-auth-title {
            font-size: 44px;
        }

        .he-auth-text {
            font-size: 14px;
        }
    }

    @media (max-height: 760px) and (min-width: 921px) {
        .he-auth-brand {
            padding: 30px 42px;
            gap: 20px;
        }

        .he-brand-mark img,
        .he-mobile-logo img {
            width: 40px;
            height: 40px;
        }

        .he-auth-title {
            font-size: 40px;
        }

        .he-auth-text {
            margin-top: 14px;
        }

        .he-feature {
            min-height: 78px;
            padding: 11px;
        }

        .he-auth-form-side {
            padding: 30px 38px;
        }
    }

    @media (max-width: 920px) {
        body:has(.he-auth-root) {
            overflow: auto;
        }

        .fi-simple-layout,
        .fi-simple-main-ctn,
        .fi-simple-main,
        .he-auth-root {
            height: auto !important;
            min-height: 100svh !important;
            overflow: auto !important;
        }

        .he-auth-shell {
            grid-template-columns: 1fr;
            height: auto;
            min-height: 100svh;
            overflow: visible;
        }

        .he-auth-brand {
            min-height: auto;
            padding: 30px 28px;
        }

        .he-auth-title {
            font-size: 38px;
        }

        .he-auth-form-side {
            align-items: flex-start;
            padding: 34px 24px;
            overflow: visible;
        }
    }

    @media (max-width: 680px) {
        .he-auth-brand {
            display: none;
        }

        .he-auth-form-side {
            min-height: 100svh;
            padding: 28px 20px;
        }

        .he-mobile-logo {
            display: flex;
        }

        .he-form-title {
            font-size: 26px;
        }

        .he-auth-support {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="he-auth-shell">
    <section class="he-auth-brand" aria-label="Hibiscus Efsya POS">
        <div class="he-brand-mark">
            <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya">
            <div>
                <div class="he-brand-name">Hibiscus Efsya POS</div>
                <div class="he-brand-caption">Customer Invoice & Transaction Portal</div>
            </div>
        </div>

        <div class="he-auth-copy">
            <h1 class="he-auth-title">Portal Pelanggan Terintegrasi.</h1>
            <p class="he-auth-text">
                Akses riwayat transaksi, invoice, dan detail pembelian Anda dengan mudah. Pantau status pesanan dan tagihan Anda secara real-time kapan pun dan di mana pun.
            </p>
        </div>

        <div class="he-feature-grid" aria-label="Fitur utama">
            <div class="he-feature">
                <div class="he-feature-index">01</div>
                <div class="he-feature-title">Real-time sales control</div>
                <div class="he-feature-text">Invoice, payment, and approval status stay visible across every team role.</div>
            </div>
            <div class="he-feature">
                <div class="he-feature-index">02</div>
                <div class="he-feature-title">Inventory visibility</div>
                <div class="he-feature-text">Track warehouse stock, batch information, and goods receipt without switching tools.</div>
            </div>
            <div class="he-feature">
                <div class="he-feature-index">03</div>
                <div class="he-feature-title">Field visit workflow</div>
                <div class="he-feature-text">Capture customer visits, coordinates, products, and notes in one operational flow.</div>
            </div>
            <div class="he-feature">
                <div class="he-feature-index">04</div>
                <div class="he-feature-title">Thermal-ready documents</div>
                <div class="he-feature-text">Print invoices, receipts, and product cards for fast retail and field execution.</div>
            </div>
        </div>
    </section>

    <section class="he-auth-form-side" aria-label="Login form">
        <div class="he-auth-panel">
            <div class="he-mobile-logo">
                <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya">
                <div>
                    <div class="he-brand-name he-mobile-brand-name">Hibiscus Efsya POS</div>
                    <div class="he-brand-caption he-mobile-brand-caption">Secure customer access</div>
                </div>
            </div>

            <div class="he-form-eyebrow">Secure access</div>
            <h2 class="he-form-title">Portal Pelanggan</h2>
            <p class="he-form-subtitle">Masuk menggunakan Nomor Telepon dan PIN Anda untuk mengakses riwayat transaksi.</p>

            <div class="he-login-card">
                {{ $this->content }}
            </div>

            <div class="he-auth-support">
                <div>
                    <strong>Belum punya PIN?</strong>
                    Silakan hubungi Sales atau Admin kami untuk mendaftarkan nomor telepon Anda.
                </div>
                <div class="he-status-pill">Protected</div>
            </div>
        </div>
    </section>
</div>

<x-filament-actions::modals />
</div>
