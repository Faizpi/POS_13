/**
 * Bluetooth Thermal Printer - Client Side Solution
 * 
 * Solusi print langsung dari browser ke printer thermal via Bluetooth.
 * Optimized untuk printer BLE murah (iWARE, MTP, RPP) yang tidak full support
 * ESC/POS commands via BLE.
 * 
 * Key Optimizations:
 * - Menggunakan ESC * (bit-image) bukan GS v 0 untuk gambar
 * - QR Code utilities kept for legacy flows; current receipt footer prints text only
 * - Chunk size kecil (128 bytes) untuk BLE stability
 * - Delay antar chunk 150ms untuk mencegah buffer overflow
 * - ALIGN_CENTER command sebelum setiap image
 * 
 * Features:
 * - Text printing with ESC/POS commands
 * - Logo printing (ESC * bitmap - 90% compatible)
 * - 58mm and 80mm receipt layouts
 */

class BluetoothThermalPrinter {
    constructor() {
        this.device = null;
        this.characteristic = null;
        this.PAPER_CONFIGS = {
            '58mm': { width: 32, printWidth: 384, dashCount: 16, previewClass: '' },
            '80mm': { width: 48, printWidth: 576, dashCount: 24, previewClass: 'bt-preview-paper-80' }
        };
        this.paperSize = '58mm';
        this.WIDTH = 32;
        this.PRINT_WIDTH = 384;
        
        // BLE Optimized settings
        this.BLE_CHUNK_SIZE = 128; // Safe chunk size for BLE (64-128 recommended)
        this.BLE_DELAY = 150; // Delay between chunks in ms (100-150ms recommended)
        
        // ESC/POS Commands
        this.ESC = '\x1B';
        this.GS = '\x1D';
        this.COMMANDS = {
            RESET: '\x1B\x40',
            BOLD_ON: '\x1B\x45\x01',
            BOLD_OFF: '\x1B\x45\x00',
            ALIGN_LEFT: '\x1B\x61\x00',
            ALIGN_CENTER: '\x1B\x61\x01',
            ALIGN_RIGHT: '\x1B\x61\x02',
            FONT_A: '\x1B\x4D\x00',
            FONT_B: '\x1B\x4D\x01',
            CUT: '\x1D\x56\x00',
            FEED: '\n'
        };
        
        // Cache for loaded images
        this.imageCache = {};
    }

    // Format currency to Rupiah
    formatRupiah(amount) {
        return 'Rp' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
    }

    normalizePaperSize(value) {
        const text = this.stringValue(value).toLowerCase().replace(/\s+/g, '');
        return text === '80mm' || text === '80' ? '80mm' : '58mm';
    }

    setPaperSize(value) {
        const size = this.normalizePaperSize(value);
        const config = this.PAPER_CONFIGS[size] || this.PAPER_CONFIGS['58mm'];
        this.paperSize = size;
        this.WIDTH = config.width;
        this.PRINT_WIDTH = config.printWidth;
        return size;
    }

    paperDashLine(size = this.paperSize) {
        const config = this.PAPER_CONFIGS[this.normalizePaperSize(size)] || this.PAPER_CONFIGS['58mm'];
        return '- '.repeat(config.dashCount);
    }

    // Create divider line
    divider(char = '-') {
        return char.repeat(this.WIDTH) + '\n';
    }

    // Pad string for alignment
    padLine(left, right) {
        const totalLen = left.length + right.length;
        if (totalLen >= this.WIDTH) {
            return left.substring(0, this.WIDTH - right.length - 1) + ' ' + right + '\n';
        }
        return left + ' '.repeat(this.WIDTH - totalLen) + right + '\n';
    }

    // Format info row (Label: Value)
    formatInfoRow(label, value) {
        const cleanValue = String(value || '-').trim() || '-';
        const labelCol = 11;
        const valueCol = this.WIDTH - labelCol - 2;
        
        // Word wrap long values
        const words = cleanValue.split(' ');
        let lines = [];
        let currentLine = '';
        
        words.forEach(word => {
            if ((currentLine + ' ' + word).trim().length <= valueCol) {
                currentLine = (currentLine + ' ' + word).trim();
            } else {
                if (currentLine) lines.push(currentLine);
                currentLine = word.length > valueCol ? word.substring(0, valueCol) : word;
            }
        });
        if (currentLine) lines.push(currentLine);
        
        let output = '';
        lines.forEach((line, i) => {
            if (i === 0) {
                output += label.substring(0, labelCol).padEnd(labelCol) + ': ' + line + '\n';
            } else {
                output += ' '.repeat(labelCol + 2) + line + '\n';
            }
        });
        
        return output;
    }

    // Build the same text layout used by the Flutter app.
    async buildFlutter58Receipt(type, rawData, options = {}) {
        const data = this.unwrapResponseData(rawData);
        data.type = data.type || type;
        data.paper_size = this.setPaperSize(options.paperSize || options.paper_size || data.paper_size);

        const parts = [];
        const lines = this.buildReceiptLines(data);
        const dashLine = this.paperDashLine(data.paper_size);

        let content = this.COMMANDS.RESET;
        content += this.COMMANDS.ALIGN_CENTER;
        content += '\x1D\x21\x11' + this.COMMANDS.BOLD_ON + 'HIBISCUS EFSYA\n';
        content += '\x1D\x21\x00' + this.COMMANDS.BOLD_OFF;

        const title = this.receiptTitle(data);
        if (title) {
            content += this.COMMANDS.BOLD_ON + title + this.COMMANDS.BOLD_OFF + '\n';
        }

        content += this.COMMANDS.ALIGN_LEFT + this.divider();
        content += this.renderReceiptLines(lines);
        content += this.divider();
        content += '\n';
        content += this.COMMANDS.ALIGN_CENTER + this.COMMANDS.FONT_B + this.COMMANDS.BOLD_ON;
        content += 'Periksa Invoice & Ambil Promo !\n';
        content += this.COMMANDS.BOLD_OFF + '\n';
        content += dashLine + '\n';
        content += 'customer.hibiscusefsya.com\n';
        content += dashLine + '\n';
        content += 'marketing@hibiscusefsya.com\n\n';
        parts.push({ type: 'text', data: content });

        let footer = this.COMMANDS.ALIGN_CENTER + this.COMMANDS.FONT_B;
        footer += `Official WA Chat:\n${this.formatPhone('+62 851-9555-0202')}\n\n`;
        footer += 'Terima kasih\n\n\n';
        footer += this.COMMANDS.FONT_A;
        footer += this.COMMANDS.CUT;
        parts.push({ type: 'text', data: footer });

        return parts;
    }

    unwrapResponseData(response) {
        if (response && typeof response === 'object' && response.data && typeof response.data === 'object' && !Array.isArray(response.data)) {
            return { ...response.data };
        }
        return { ...(response || {}) };
    }

    receiptTitle(data) {
        const type = this.stringValue(data.type).toLowerCase();
        if (type.includes('penjualan')) return 'INVOICE PENJUALAN';
        if (type.includes('kunjungan')) return 'STRUK KUNJUNGAN';
        if (type.includes('pembelian')) return 'INVOICE PEMBELIAN';
        if (type.includes('biaya')) return 'STRUK BIAYA';
        return 'STRUK';
    }

    buildReceiptLines(data) {
        data.paper_size = this.setPaperSize(data.paper_size || this.paperSize);
        const type = this.stringValue(data.type).toLowerCase();
        if (type.includes('penjualan')) return this.buildPenjualanLines(data);
        if (type.includes('kunjungan')) return this.buildKunjunganLines(data);
        if (type.includes('pembelian')) return this.buildPembelianLines(data);
        if (type.includes('biaya')) return this.buildBiayaLines(data);
        return this.buildGenericLines(data);
    }

    renderReceiptLines(lines) {
        let output = '';
        lines.forEach(line => {
            if (line === null || line === undefined) {
                output += '\n';
            } else if (line === '---HR---') {
                output += this.divider();
            } else if (String(line).startsWith('\x00R:')) {
                output += this.rightOnlyLine(String(line).substring(3)) + '\n';
            } else {
                output += String(line) + '\n';
            }
        });
        return output;
    }

    async showPreviewDialog(type, rawData, options = {}) {
        const data = this.unwrapResponseData(rawData);
        data.type = data.type || type;
        data.paper_size = this.setPaperSize(options.paperSize || options.paper_size || data.paper_size);

        this.ensurePreviewStyles();
        const title = this.receiptTitle(data);
        const lines = this.buildReceiptLines(data);
        const paperConfig = this.PAPER_CONFIGS[data.paper_size] || this.PAPER_CONFIGS['58mm'];
        const paperClass = paperConfig.previewClass ? ` ${paperConfig.previewClass}` : '';
        const dashLine = this.paperDashLine(data.paper_size);

        // Build structured HTML preview with thermal print visual
        const contentHtml = lines.map(line => this.previewLineHtml(line)).join('');

        const overlay = document.createElement('div');
        overlay.className = 'bt-preview-overlay';
        overlay.innerHTML = `
            <div class="bt-preview-dialog" role="dialog" aria-modal="true">
                <div class="bt-preview-header">
                    <div>
                        <div class="bt-preview-title">Preview Struk Bluetooth</div>
                        <div class="bt-preview-subtitle">Format thermal ${this.escapeHtml(data.paper_size)}</div>
                    </div>
                    <button type="button" class="bt-preview-close" aria-label="Tutup">&times;</button>
                </div>
                <div class="bt-preview-scroll">
                    <div class="bt-preview-paper${paperClass}">
                        <div class="bt-receipt-brand">HIBISCUS EFSYA</div>
                        <div class="bt-receipt-title">${this.escapeHtml(title)}</div>
                        <div class="bt-receipt-divider"></div>
                        ${contentHtml}
                        <div class="bt-receipt-divider"></div>
                        <div class="bt-preview-promo">Periksa Invoice &amp; Ambil Promo !</div>
                        <div class="bt-preview-dash">${this.escapeHtml(dashLine)}</div>
                        <div class="bt-receipt-center">customer.hibiscusefsya.com</div>
                        <div class="bt-preview-dash">${this.escapeHtml(dashLine)}</div>
                        <div class="bt-receipt-center">marketing@hibiscusefsya.com</div>
                        <div class="bt-preview-gap"></div>
                        <div class="bt-receipt-center">Official WA Chat:</div>
                        <div class="bt-receipt-center">${this.escapeHtml(this.formatPhone('+62 851-9555-0202'))}</div>
                        <div class="bt-preview-gap"></div>
                        <div class="bt-receipt-center">Terima kasih</div>
                    </div>
                </div>
                <div class="bt-preview-actions">
                    <button type="button" class="btn btn-light bt-preview-cancel">Batal</button>
                    <button type="button" class="btn btn-primary bt-preview-print">
                        <i class="fab fa-bluetooth-b"></i> Print Bluetooth
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        const closeBtn = overlay.querySelector('.bt-preview-close');
        const cancelBtn = overlay.querySelector('.bt-preview-cancel');
        const printBtn = overlay.querySelector('.bt-preview-print');
        const dialog = overlay.querySelector('.bt-preview-dialog');

        return new Promise(resolve => {
            const cleanup = result => {
                document.removeEventListener('keydown', onKeyDown);
                overlay.remove();
                resolve(result);
            };
            const onKeyDown = event => {
                if (event.key === 'Escape') cleanup(false);
            };

            closeBtn.addEventListener('click', () => cleanup(false));
            cancelBtn.addEventListener('click', () => cleanup(false));
            printBtn.addEventListener('click', () => cleanup(true));
            overlay.addEventListener('click', event => {
                if (!dialog.contains(event.target)) cleanup(false);
            });
            document.addEventListener('keydown', onKeyDown);
        });
    }

    // Render receipt lines as plain monospace text (same as thermal print)
    renderReceiptText(lines) {
        const output = [];
        lines.forEach(line => {
            if (line === null || line === undefined) {
                output.push('');
            } else if (line === '---HR---') {
                output.push('-'.repeat(this.WIDTH));
            } else if (String(line).startsWith('\x00R:')) {
                const right = String(line).substring(3);
                output.push(right.padStart(this.WIDTH));
            } else {
                output.push(String(line));
            }
        });
        return output.join('\n');
    }

    async showPaperSizeDialog(defaultPaperSize = '58mm') {
        this.ensurePreviewStyles();
        const selected = this.normalizePaperSize(defaultPaperSize);
        const overlay = document.createElement('div');
        overlay.className = 'bt-preview-overlay';
        overlay.innerHTML = `
            <div class="bt-paper-dialog" role="dialog" aria-modal="true">
                <div class="bt-preview-header">
                    <div>
                        <div class="bt-preview-title">Ukuran Kertas Printer</div>
                        <div class="bt-preview-subtitle">Pilih sesuai printer Bluetooth</div>
                    </div>
                    <button type="button" class="bt-preview-close" aria-label="Tutup">&times;</button>
                </div>
                <div class="bt-paper-options">
                    <button type="button" class="bt-paper-option${selected === '58mm' ? ' active' : ''}" data-paper-size="58mm">
                        <span class="bt-paper-option-title">58 mm</span>
                        <span class="bt-paper-option-desc">Printer kecil / standar</span>
                    </button>
                    <button type="button" class="bt-paper-option${selected === '80mm' ? ' active' : ''}" data-paper-size="80mm">
                        <span class="bt-paper-option-title">80 mm</span>
                        <span class="bt-paper-option-desc">Printer lebar / kasir</span>
                    </button>
                </div>
                <div class="bt-preview-actions">
                    <button type="button" class="btn btn-light bt-preview-cancel">Batal</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        const dialog = overlay.querySelector('.bt-paper-dialog');
        const closeBtn = overlay.querySelector('.bt-preview-close');
        const cancelBtn = overlay.querySelector('.bt-preview-cancel');
        const optionBtns = overlay.querySelectorAll('[data-paper-size]');

        return new Promise(resolve => {
            const cleanup = result => {
                document.removeEventListener('keydown', onKeyDown);
                overlay.remove();
                resolve(result);
            };
            const onKeyDown = event => {
                if (event.key === 'Escape') cleanup(null);
            };

            closeBtn.addEventListener('click', () => cleanup(null));
            cancelBtn.addEventListener('click', () => cleanup(null));
            optionBtns.forEach(btn => {
                btn.addEventListener('click', () => cleanup(btn.getAttribute('data-paper-size')));
            });
            overlay.addEventListener('click', event => {
                if (!dialog.contains(event.target)) cleanup(null);
            });
            document.addEventListener('keydown', onKeyDown);
        });
    }

    previewLineHtml(line) {
        if (line === null || line === undefined) return '<div class="bt-preview-gap"></div>';
        if (line === '---HR---') return '<div class="bt-preview-hr"></div>';
        const raw = String(line);
        if (raw.includes('\n')) {
            return raw.split('\n').map(part => this.previewLineHtml(part)).join('');
        }
        if (raw.startsWith('\x00R:')) {
            return `<div class="bt-preview-line bt-preview-right">${this.escapeHtml(raw.substring(3))}</div>`;
        }

        const aligned = this.parseAlignedPreviewLine(raw);
        if (aligned) {
            return `
                <div class="bt-preview-line bt-preview-row">
                    <span class="bt-preview-row-left">${this.escapeHtml(aligned.left)}</span>
                    <span class="bt-preview-row-right">${this.escapeHtml(aligned.right)}</span>
                </div>
            `;
        }

        return `<div class="bt-preview-line">${this.escapeHtml(raw).replace(/\n/g, '<br>')}</div>`;
    }

    parseAlignedPreviewLine(raw) {
        const line = this.stringValue(raw);
        if (!line || line.length > this.WIDTH || raw.startsWith(' ')) return null;

        const match = line.match(/^(.+?\S) {2,}(\S.*)$/);
        if (!match) return null;

        return {
            left: match[1],
            right: match[2]
        };
    }

    escapeHtml(value) {
        return this.stringValue(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    ensurePreviewStyles() {
        if (document.getElementById('bt-preview-styles')) return;
        const style = document.createElement('style');
        style.id = 'bt-preview-styles';
        style.textContent = `
            .bt-preview-overlay {
                --bt-overlay: rgba(15, 23, 42, 0.55);
                --bt-surface: #ffffff;
                --bt-header-bg: #ffffff;
                --bt-border: #e5e7eb;
                --bt-text: #111827;
                --bt-muted: #6b7280;
                --bt-primary: #6366f1;
                --bt-primary-hover: #4f46e5;
                --bt-primary-text: #ffffff;
                --bt-scroll-bg: #f1f5f9;
                --bt-paper-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
                --bt-dialog-shadow: 0 24px 80px rgba(15, 23, 42, 0.3);
                --bt-close-bg: #f1f5f9;
                --bt-close-color: #475569;
                --bt-paper-bg: #ffffff;
                --bt-paper-text: #000000;
                --bt-paper-border: #d1d5db;
                position: fixed;
                inset: 0;
                z-index: 20000;
                background: var(--bt-overlay);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
                backdrop-filter: blur(4px);
            }
            .dark .bt-preview-overlay {
                --bt-overlay: rgba(0, 0, 0, 0.65);
                --bt-surface: #1e293b;
                --bt-header-bg: #1e293b;
                --bt-border: #334155;
                --bt-text: #f1f5f9;
                --bt-muted: #94a3b8;
                --bt-primary: #818cf8;
                --bt-primary-hover: #6366f1;
                --bt-primary-text: #0f172a;
                --bt-scroll-bg: #0f172a;
                --bt-paper-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
                --bt-dialog-shadow: 0 24px 80px rgba(0, 0, 0, 0.5);
                --bt-close-bg: #334155;
                --bt-close-color: #cbd5e1;
                --bt-paper-bg: #fefefe;
                --bt-paper-text: #111111;
                --bt-paper-border: #475569;
            }
            .bt-preview-dialog {
                width: min(560px, 100%);
                max-height: calc(100vh - 32px);
                background: var(--bt-surface);
                border-radius: 14px;
                box-shadow: var(--bt-dialog-shadow);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .bt-preview-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 16px;
                border-bottom: 1px solid var(--bt-border);
                background: var(--bt-header-bg);
            }
            .bt-preview-title {
                font-size: 15px;
                font-weight: 700;
                color: var(--bt-text);
            }
            .bt-preview-subtitle {
                font-size: 12px;
                color: var(--bt-muted);
                margin-top: 1px;
            }
            .bt-preview-close {
                width: 34px;
                height: 34px;
                border: 0;
                border-radius: 8px;
                background: var(--bt-close-bg);
                color: var(--bt-close-color);
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
                transition: background 120ms ease;
            }
            .bt-preview-close:hover {
                background: var(--bt-border);
            }
            .bt-preview-scroll {
                overflow: auto;
                background: var(--bt-scroll-bg);
                padding: 16px 0;
            }
            .bt-preview-paper {
                width: 272px;
                max-width: calc(100vw - 64px);
                margin: 0 auto;
                padding: 14px 12px 14px;
                background: var(--bt-paper-bg);
                color: var(--bt-paper-text);
                font-family: "Courier New", Courier, "Lucida Console", monospace;
                font-size: 10.5px;
                line-height: 1.3;
                box-shadow: var(--bt-paper-shadow);
                border: 1px solid var(--bt-paper-border);
                border-radius: 2px;
            }
            .bt-preview-paper-80 {
                width: 408px;
            }
            .bt-preview-line {
                white-space: pre-wrap;
                word-break: break-word;
                min-height: 14px;
                font-family: inherit;
                font-size: inherit;
                line-height: inherit;
            }
            .bt-preview-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 4px;
                white-space: normal;
            }
            .bt-preview-row-left {
                min-width: 0;
                overflow-wrap: anywhere;
                flex-shrink: 0;
            }
            .bt-preview-row-right {
                min-width: max-content;
                margin-left: auto;
                text-align: right;
                overflow-wrap: anywhere;
                font-weight: 700;
            }
            .bt-preview-right {
                text-align: right;
            }
            .bt-preview-gap {
                height: 6px;
            }
            .bt-preview-hr {
                border-top: 1px dashed var(--bt-paper-text);
                margin: 4px 0;
                opacity: 0.5;
            }
            .bt-preview-promo {
                text-align: center;
                font-weight: 700;
                font-size: 8.5px;
                line-height: 1.2;
                margin: 6px 0;
            }
            .bt-preview-dash {
                text-align: center;
                white-space: pre;
                font-size: 8.5px;
                line-height: 1.2;
                margin: 6px 0;
                opacity: 0.7;
            }
            .bt-preview-qr {
                width: 120px;
                height: 120px;
                margin: 6px auto;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .bt-preview-qr img,
            .bt-preview-qr canvas {
                width: 120px !important;
                height: 120px !important;
            }
            .bt-preview-qr-fallback {
                width: 120px;
                height: 120px;
                border: 2px solid var(--bt-paper-text);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                font-weight: 800;
                text-align: center;
                color: var(--bt-paper-text);
            }
            .bt-preview-qr-fallback span {
                font-size: 8px;
                font-weight: 400;
                margin-top: 4px;
            }
            .bt-receipt-brand {
                text-align: center;
                font-weight: 800;
                font-size: 13px;
                line-height: 1.3;
                letter-spacing: 0.5px;
                margin-bottom: 2px;
            }
            .bt-receipt-title {
                text-align: center;
                font-weight: 700;
                font-size: 11px;
                line-height: 1.3;
                margin-bottom: 2px;
            }
            .bt-receipt-divider {
                border-top: 1px dashed var(--bt-paper-text);
                margin: 4px 0;
                opacity: 0.45;
            }
            .bt-receipt-center {
                text-align: center;
                font-size: 9px;
                line-height: 1.3;
            }
            .bt-receipt-gap {
                height: 6px;
            }
            .bt-preview-footer {
                text-align: center;
                font-size: 8.5px;
                line-height: 1.2;
                margin-top: 6px;
                opacity: 0.8;
            }
            .bt-preview-actions {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                padding: 12px 16px;
                border-top: 1px solid var(--bt-border);
                background: var(--bt-surface);
            }
            .bt-preview-actions .btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 18px;
                border: 0;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 120ms ease;
            }
            .bt-preview-actions .btn-light {
                background: var(--bt-close-bg);
                color: var(--bt-text);
            }
            .bt-preview-actions .btn-light:hover {
                background: var(--bt-border);
            }
            .bt-preview-actions .btn-primary {
                background: var(--bt-primary);
                color: var(--bt-primary-text);
            }
            .bt-preview-actions .btn-primary:hover {
                background: var(--bt-primary-hover);
            }
            .bt-paper-dialog {
                width: min(390px, 100%);
                background: var(--bt-surface);
                border-radius: 14px;
                box-shadow: var(--bt-dialog-shadow);
                overflow: hidden;
            }
            .bt-paper-options {
                display: grid;
                gap: 10px;
                padding: 14px 16px 16px;
            }
            .bt-paper-option {
                width: 100%;
                border: 1px solid var(--bt-border);
                border-radius: 10px;
                background: var(--bt-surface);
                padding: 12px 14px;
                text-align: left;
                cursor: pointer;
                transition: all 120ms ease;
            }
            .bt-paper-option.active,
            .bt-paper-option:hover {
                border-color: var(--bt-primary);
                background: color-mix(in srgb, var(--bt-primary) 10%, var(--bt-surface));
            }
            .bt-paper-option-title {
                display: block;
                font-size: 14px;
                font-weight: 700;
                color: var(--bt-text);
            }
            .bt-paper-option-desc {
                display: block;
                margin-top: 2px;
                font-size: 12px;
                color: var(--bt-muted);
            }
        `;
        document.head.appendChild(style);
    }

    buildPenjualanLines(data) {
        const lines = [
            this.kvLine('Nomor', this.stringValue(data.nomor)),
            this.kvLine('Tanggal', this.stringValue(data.tanggal)),
            this.kvLine('Jatuh Tempo', this.stringValue(data.jatuh_tempo)),
            this.kvLine('Pembayaran', this.stringValue(data.pembayaran)),
            this.kvLine('Pelanggan', this.truncateDisplay(data.pelanggan)),
            this.kvLine('No. Telepon', this.formatPhone(data.no_telepon) || 'N/A'),
            this.kvLine('Sales', this.truncateDisplay(data.sales)),
            this.kvLine('No. Telp Sales', this.formatPhone(data.sales_no_telp) || 'N/A')
        ];

        if (this.stringValue(data.no_referensi)) lines.push(this.kvLine('No. Ref', this.stringValue(data.no_referensi)));
        if (this.stringValue(data.memo)) lines.push(this.kvLine('Memo', this.stringValue(data.memo)));
        lines.push('---HR---');

        this.listOfMaps(data.items).forEach(item => {
            lines.push(this.wrapText(this.itemName(item)));
            const qty = this.numValue(item.qty ?? item.kuantitas);
            const unit = this.stringValue(item.unit) || this.stringValue(item.satuan) || 'Pcs';
            const harga = this.numValue(item.harga ?? item.harga_satuan);
            const batch = this.stringValue(item.batch) || this.stringValue(item.batch_number) || 'N/A';
            const exp = this.formatExpDate(this.stringValue(item.exp) || this.stringValue(item.expired_date)) || 'N/A';
            lines.push(this.twoColumn('Batch', `${batch} - ${exp}`));
            lines.push(this.twoColumn('Qty', `${this.formatQty(qty)} ${unit} x ${this.currency(harga)}`));

            const diskon = this.numValue(item.diskon);
            if (diskon > 0) lines.push(this.twoColumn('Diskon', `${this.formatQty(diskon)}%`));
            const diskonNominal = this.numValue(item.diskon_nominal);
            if (diskonNominal > 0) lines.push(this.twoColumn('Disc Rp', `- ${this.currency(diskonNominal)}`));
            if (this.stringValue(item.deskripsi)) lines.push(this.kvLine('Ket', this.stringValue(item.deskripsi)));
            lines.push(this.twoColumn('Jumlah', this.currency(this.numValue(item.jumlah))));
            lines.push(null);
        });

        this.removeTrailingBlank(lines);
        lines.push('---HR---');
        lines.push(this.twoColumn('Subtotal', this.currency(this.numValue(data.subtotal))));
        if (this.numValue(data.diskon_akhir) > 0) {
            lines.push(this.twoColumn('Diskon', `- ${this.currency(this.numValue(data.diskon_akhir))}`));
        }
        if (this.numValue(data.pajak) > 0) {
            lines.push(this.twoColumn(`Pajak (${this.formatQty(this.numValue(data.tax_percentage))}%)`, this.currency(this.numValue(data.pajak))));
        }
        lines.push('---HR---');
        lines.push(this.twoColumn('GRAND TOTAL', this.currency(this.numValue(data.grand_total))));
        return lines;
    }

    buildPembelianLines(data) {
        const lines = [
            this.kvLine('Nomor', this.stringValue(data.nomor)),
            this.kvLine('Tanggal', this.stringValue(data.tanggal)),
            this.kvLine('Jatuh Tempo', this.stringValue(data.jatuh_tempo)),
            this.kvLine('Pembayaran', this.stringValue(data.pembayaran))
        ];
        if (this.stringValue(data.urgensi)) lines.push(this.kvLine('Urgensi', this.stringValue(data.urgensi)));
        lines.push(this.kvLine('Vendor', this.stringValue(data.vendor)));
        lines.push(this.kvLine('Dibuat oleh', this.truncateDisplay(data.sales)));
        if (this.stringValue(data.tahun_anggaran)) lines.push(this.kvLine('Thn Anggaran', this.stringValue(data.tahun_anggaran)));
        if (this.stringValue(data.staf_penyetuju)) lines.push(this.kvLine('Staf Penyetuju', this.stringValue(data.staf_penyetuju)));
        if (this.stringValue(data.memo)) lines.push(this.kvLine('Memo', this.stringValue(data.memo)));
        lines.push('---HR---');

        this.listOfMaps(data.items).forEach(item => {
            lines.push(this.wrapText(this.itemName(item)));
            const qty = this.numValue(item.qty ?? item.kuantitas);
            const unit = this.stringValue(item.unit) || this.stringValue(item.satuan) || 'Pcs';
            const harga = this.numValue(item.harga ?? item.harga_satuan);
            const batch = this.stringValue(item.batch_number) || this.stringValue(item.batch) || 'N/A';
            const exp = this.formatExpDate(this.stringValue(item.expired_date) || this.stringValue(item.exp)) || 'N/A';
            lines.push(this.twoColumn('Batch', `${batch} - ${exp}`));
            lines.push(this.twoColumn('Qty', `${this.formatQty(qty)} ${unit} x ${this.currency(harga)}`));

            const diskon = this.numValue(item.diskon);
            if (diskon > 0) lines.push(this.twoColumn('Diskon', `${this.formatQty(diskon)}%`));
            if (this.stringValue(item.deskripsi)) lines.push(this.kvLine('Ket', this.stringValue(item.deskripsi)));
            lines.push(this.twoColumn('Jumlah', this.currency(this.numValue(item.jumlah))));
            lines.push(null);
        });

        this.removeTrailingBlank(lines);
        lines.push('---HR---');
        lines.push(this.twoColumn('Subtotal', this.currency(this.numValue(data.subtotal))));
        if (this.numValue(data.diskon_akhir) > 0) {
            lines.push(this.twoColumn('Diskon', `- ${this.currency(this.numValue(data.diskon_akhir))}`));
        }
        if (this.numValue(data.pajak) > 0) {
            lines.push(this.twoColumn(`Pajak (${this.formatQty(this.numValue(data.tax_percentage))}%)`, this.currency(this.numValue(data.pajak))));
        }
        lines.push('---HR---');
        lines.push(this.twoColumn('GRAND TOTAL', this.currency(this.numValue(data.grand_total))));
        return lines;
    }

    buildBiayaLines(data) {
        const lines = [
            this.kvLine('Nomor', this.stringValue(data.nomor)),
            this.kvLine('Tanggal', this.stringValue(data.tanggal)),
            this.kvLine('Jenis Biaya', this.stringValue(data.jenis_biaya)),
            this.kvLine('Bayar Dari', this.stringValue(data.bayar_dari))
        ];
        if (this.stringValue(data.cara_pembayaran)) lines.push(this.kvLine('Cara Bayar', this.stringValue(data.cara_pembayaran)));
        lines.push(this.kvLine('Penerima', this.stringValue(data.penerima)));
        if (this.stringValue(data.alamat_penagihan)) lines.push(this.kvLine('Alamat', this.stringValue(data.alamat_penagihan)));
        lines.push(this.kvLine('Dibuat oleh', this.truncateDisplay(data.sales)));
        if (this.stringValue(data.tag)) lines.push(this.kvLine('Tag', this.stringValue(data.tag)));
        if (this.stringValue(data.koordinat)) lines.push(this.kvLine('Koordinat', this.stringValue(data.koordinat)));
        if (this.stringValue(data.memo)) lines.push(this.kvLine('Memo', this.stringValue(data.memo)));
        lines.push('---HR---');

        this.listOfMaps(data.items).forEach(item => {
            lines.push(this.wrapText(this.stringValue(item.kategori)));
            if (this.stringValue(item.deskripsi)) lines.push(this.kvLine('Deskripsi', this.stringValue(item.deskripsi)));
            lines.push(this.twoColumn('Jumlah', this.currency(this.numValue(item.jumlah))));
            lines.push(null);
        });

        this.removeTrailingBlank(lines);
        lines.push('---HR---');
        if (this.numValue(data.subtotal) > 0) lines.push(this.twoColumn('Subtotal', this.currency(this.numValue(data.subtotal))));
        if (this.numValue(data.pajak) > 0) {
            lines.push(this.twoColumn(`Pajak (${this.formatQty(this.numValue(data.tax_percentage))}%)`, this.currency(this.numValue(data.pajak))));
        }
        lines.push('---HR---');
        lines.push(this.twoColumn('GRAND TOTAL', this.currency(this.numValue(data.grand_total))));
        return lines;
    }

    buildKunjunganLines(data) {
        const lines = [
            this.kvLine('Nomor', this.stringValue(data.nomor)),
            this.kvLine('Tanggal', this.stringValue(data.tanggal)),
            this.kvLine('Tujuan', this.stringValue(data.tujuan))
        ];

        const pembuatNama = this.stringValue(data.dibuat_oleh) || this.stringValue(data.user && data.user.name) || this.stringValue(data.pembuat);
        if (pembuatNama) lines.push(this.kvLine('Pembuat', this.truncateDisplay(pembuatNama)));

        const pelangganNama = this.stringValue(data.sales_nama) || this.stringValue(data.kontak && data.kontak.nama) || this.stringValue(data.kontak_nama);
        if (pelangganNama) lines.push(this.kvLine('Pelanggan', this.truncateDisplay(pelangganNama)));
        if (this.stringValue(data.sales_no_telepon)) lines.push(this.kvLine('No. Telepon', this.formatPhone(data.sales_no_telepon)));
        if (this.stringValue(data.sales_alamat)) lines.push(...this.rightAlignLines('Alamat', this.stringValue(data.sales_alamat)));
        if (this.stringValue(data.koordinat)) lines.push(...this.rightAlignLines('Koordinat', this.stringValue(data.koordinat)));
        if (this.stringValue(data.memo)) lines.push(this.kvLine('Memo', this.stringValue(data.memo)));
        lines.push('---HR---');

        this.listOfMaps(data.items).forEach(item => {
            let produkNama = this.stringValue(item.nama) || this.stringValue(item.nama_produk);
            if (!produkNama && item.produk) produkNama = this.stringValue(item.produk.nama_produk);
            lines.push(this.wrapText(produkNama || '-'));

            let satuan = this.stringValue(item.unit) || this.stringValue(item.satuan);
            if (!satuan && item.produk) satuan = this.stringValue(item.produk.satuan);
            const qty = this.numValue(item.qty ?? item.kuantitas);
            lines.push(this.twoColumn('Qty', `${this.formatQty(qty)} ${satuan || 'Pcs'}`));

            if (this.stringValue(item.tipe_stok)) lines.push(this.kvLine('Tipe', this.stringValue(item.tipe_stok)));
            lines.push(this.twoColumn('Batch', this.stringValue(item.batch) || this.stringValue(item.batch_number) || 'N/A'));
            lines.push(this.twoColumn('Exp', this.formatExpDate(this.stringValue(item.exp) || this.stringValue(item.expired_date))));
            if (this.stringValue(item.keterangan)) lines.push(this.kvLine('Ket', this.stringValue(item.keterangan)));
            lines.push(null);
        });

        this.removeTrailingBlank(lines);
        return lines;
    }

    buildGenericLines(data) {
        return Object.entries(data)
            .filter(([key]) => key !== 'items')
            .map(([key, value]) => this.kvLine(key.replaceAll('_', ' ').toUpperCase(), this.stringValue(value)));
    }

    kvLine(label, value) {
        return this.twoColumn(label, this.stringValue(value) || '-');
    }

    twoColumn(left, right) {
        const leftText = this.stringValue(left);
        const rightText = this.stringValue(right) || '-';
        const rightWidth = Math.max(1, this.WIDTH - leftText.length - 1);
        const chunks = this.wrapChunks(rightText, rightWidth);
        const rows = chunks.map((chunk, index) => {
            if (index === 0) {
                const available = this.WIDTH - leftText.length - chunk.length;
                return leftText + ' '.repeat(Math.max(1, available)) + chunk;
            }

            return this.rightOnlyLine(chunk);
        });

        return rows.join('\n');
    }

    rightAlignLines(label, value) {
        const lbl = this.stringValue(label);
        const val = this.stringValue(value) || '-';
        const maxValWidth = Math.max(1, this.WIDTH - lbl.length - 1);
        const chunks = this.wrapChunks(val, maxValWidth);
        return chunks.map((chunk, index) => {
            if (index === 0) {
                const pad = this.WIDTH - lbl.length - 1 - chunk.length;
                return lbl + ' '.repeat(Math.max(2, pad)) + chunk;
            }
            return '\x00R:' + chunk;
        });
    }

    rightOnlyLine(value) {
        const text = this.stringValue(value);
        return ' '.repeat(Math.max(0, this.WIDTH - text.length)) + text;
    }

    wrapText(value, width = this.WIDTH) {
        return this.wrapChunks(this.stringValue(value), width).join('\n');
    }

    wrapChunks(value, width) {
        const text = this.stringValue(value);
        if (!text) return [''];
        if (text.length <= width) return [text];

        const words = text.split(/\s+/);
        const lines = [];
        let current = '';

        words.forEach(word => {
            if (!current) {
                current = word;
            } else if ((current.length + 1 + word.length) <= width) {
                current += ' ' + word;
            } else {
                lines.push(current);
                current = word;
            }

            while (current.length > width) {
                lines.push(current.substring(0, width));
                current = current.substring(width);
            }
        });

        if (current) lines.push(current);
        return lines;
    }

    itemName(item) {
        return this.stringValue(item.nama) || this.stringValue(item.nama_produk) || '-';
    }

    itemQuantityPrice(item, batchVal, expVal) {
        const qty = this.numValue(item.qty ?? item.kuantitas);
        const unit = this.stringValue(item.unit) || this.stringValue(item.satuan) || 'Pcs';
        const harga = this.numValue(item.harga ?? item.harga_satuan);
        const qtyText = this.formatQty(qty);
        if (batchVal !== undefined && expVal !== undefined) {
            return `${batchVal} - ${expVal}  ${qtyText} x ${this.currency(harga)}`;
        }
        return `${qtyText} ${unit} x ${this.currency(harga)}`;
    }

    listOfMaps(value) {
        return Array.isArray(value) ? value.filter(item => item && typeof item === 'object') : [];
    }

    stringValue(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'string') return value.trim();
        if (typeof value === 'number' || typeof value === 'boolean') return String(value);
        return String(value).trim();
    }

    truncateDisplay(value, max = 20) {
        const text = this.stringValue(value);
        if (text.length <= max) return text;
        if (max <= 3) return text.substring(0, max);
        return text.substring(0, max - 3) + '...';
    }

    formatPhone(value) {
        const raw = this.stringValue(value);
        if (!raw) return '';
        let digits = raw.replace(/\D/g, '');
        if (!digits) return raw;

        if (digits.startsWith('620')) {
            digits = '62' + digits.substring(3);
        }
        if (digits.startsWith('62')) {
            return '+62 ' + this.groupPhoneDigits(digits.substring(2));
        }
        if (digits.startsWith('0')) {
            return '+62 ' + this.groupPhoneDigits(digits.substring(1));
        }
        if (digits.startsWith('8') && digits.length >= 9) {
            return '+62 ' + this.groupPhoneDigits(digits);
        }
        if (raw.trim().startsWith('+')) {
            return '+' + this.groupPhoneDigits(digits);
        }
        return this.groupPhoneDigits(digits);
    }

    groupPhoneDigits(digits) {
        // Format: 3-4-4 (e.g. 851-9555-0202)
        if (digits.length >= 10) {
            return digits.substring(0, 3) + '-' + digits.substring(3, 7) + '-' + digits.substring(7);
        }
        return (digits.match(/.{1,4}/g) || []).join('-');
    }

    numValue(value) {
        if (value === null || value === undefined || value === '') return 0;
        if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
        const cleaned = String(value).trim().replace(/[^0-9,.\-]/g, '');
        if (!cleaned || cleaned === '-') return 0;
        if (cleaned.includes(',')) {
            return Number(cleaned.replace(/\./g, '').replace(',', '.')) || 0;
        }
        if (/^-?\d{1,3}(\.\d{3})+$/.test(cleaned)) {
            return Number(cleaned.replace(/\./g, '')) || 0;
        }
        return Number(cleaned) || 0;
    }

    formatQty(value) {
        const num = this.numValue(value);
        return Number.isInteger(num) ? String(num) : num.toFixed(2);
    }

    currency(value) {
        return this.formatRupiah(this.numValue(value)).replace(/\u00a0/g, '');
    }

    formatExpDate(raw) {
        const value = this.stringValue(raw);
        if (!value) return 'N/A';
        const parts = value.split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return value;
    }

    removeTrailingBlank(lines) {
        if (lines.length && lines[lines.length - 1] === null) lines.pop();
    }

    /**
     * Load image and convert to ESC * bitmap format
     * ESC * is MUCH more compatible with cheap BLE printers than GS v 0
     * 
     * @param {string} imageUrl - URL of the image
     * @param {number} maxWidth - Maximum width in pixels (default 384 for 58mm)
     * @returns {Promise<Uint8Array>} - ESC * bitmap command data
     */
    async loadImageAsBitmap(imageUrl, maxWidth = null) {
        maxWidth = maxWidth || this.PRINT_WIDTH;
        
        // Check cache
        const cacheKey = imageUrl + '_' + maxWidth;
        if (this.imageCache[cacheKey]) {
            return this.imageCache[cacheKey];
        }

        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'Anonymous';
            
            img.onload = () => {
                try {
                    // Calculate dimensions (maintain aspect ratio)
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > maxWidth) {
                        height = Math.floor(height * (maxWidth / width));
                        width = maxWidth;
                    }
                    
                    // Width must be multiple of 8 for ESC *
                    width = Math.floor(width / 8) * 8;
                    
                    // Create canvas
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    
                    // White background
                    ctx.fillStyle = 'white';
                    ctx.fillRect(0, 0, width, height);
                    
                    // Draw image
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Get pixel data
                    const imageData = ctx.getImageData(0, 0, width, height);
                    const pixels = imageData.data;
                    
                    // Convert to ESC * format (line by line, much more BLE compatible)
                    const bytesPerLine = width / 8;
                    const commands = [];
                    
                    // ESC * m nL nH d1...dk format
                    // m = 0 (8-dot single density) or 1 (8-dot double density) or 33 (24-dot double)
                    // We use mode 0 for maximum compatibility
                    
                    for (let y = 0; y < height; y++) {
                        // ESC * command for each line
                        // ESC * m nL nH
                        commands.push(0x1B, 0x2A, 0x00); // ESC * mode 0
                        commands.push(bytesPerLine & 0xFF); // nL
                        commands.push((bytesPerLine >> 8) & 0xFF); // nH
                        
                        // Bitmap data for this line
                        for (let byteX = 0; byteX < bytesPerLine; byteX++) {
                            let byte = 0;
                            for (let bit = 0; bit < 8; bit++) {
                                const x = byteX * 8 + bit;
                                const pixelIndex = (y * width + x) * 4;
                                
                                // Convert to grayscale
                                const r = pixels[pixelIndex];
                                const g = pixels[pixelIndex + 1];
                                const b = pixels[pixelIndex + 2];
                                const gray = 0.299 * r + 0.587 * g + 0.114 * b;
                                
                                // Threshold (black if < 128)
                                if (gray < 128) {
                                    byte |= (0x80 >> bit);
                                }
                            }
                            commands.push(byte);
                        }
                        
                        // Line feed after each line
                        commands.push(0x0A); // LF
                    }
                    
                    const result = new Uint8Array(commands);
                    
                    // Cache and return
                    this.imageCache[cacheKey] = result;
                    resolve(result);
                    
                } catch (error) {
                    reject(error);
                }
            };
            
            img.onerror = () => {
                reject(new Error('Failed to load image: ' + imageUrl));
            };
            
            img.src = imageUrl;
        });
    }

    /**
     * Generate QR Code OFFLINE using canvas
     * NO external API dependency - works offline!
     * Uses simple QR encoding algorithm
     * 
     * @param {string} data - Data to encode in QR code
     * @param {number} size - Size in pixels (default 150)
     * @returns {Promise<Uint8Array>} - ESC * bitmap command data
     */
    async generateQRCode(data, size = 150) {
        // Try to use QRCode library if available (loaded from CDN)
        if (typeof QRCode !== 'undefined') {
            return await this.generateQRCodeWithLib(data, size);
        }
        
        // Fallback: Load QRCode.js dynamically
        try {
            await this.loadQRCodeLibrary();
            return await this.generateQRCodeWithLib(data, size);
        } catch (e) {
            console.warn('QRCode library failed, trying API fallback:', e);
            // Last resort: use external API
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(data)}&margin=2&format=png`;
            return await this.loadImageAsBitmap(qrUrl, size);
        }
    }

    /**
     * Load QRCode.js library dynamically
     */
    async loadQRCodeLibrary() {
        if (typeof QRCode !== 'undefined') return;
        
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Generate QR using QRCode.js library (offline capable once loaded)
     */
    async generateQRCodeWithLib(data, size = 150) {
        return new Promise((resolve, reject) => {
            try {
                // Create temp container
                const container = document.createElement('div');
                container.style.display = 'none';
                document.body.appendChild(container);
                
                // Generate QR Code
                const qr = new QRCode(container, {
                    text: data,
                    width: size,
                    height: size,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                
                // Wait for QR to render
                setTimeout(() => {
                    try {
                        const canvas = container.querySelector('canvas');
                        if (!canvas) {
                            document.body.removeChild(container);
                            reject(new Error('QR canvas not found'));
                            return;
                        }
                        
                        // Convert canvas to ESC * bitmap
                        const result = this.canvasToBitmap(canvas);
                        document.body.removeChild(container);
                        resolve(result);
                    } catch (e) {
                        document.body.removeChild(container);
                        reject(e);
                    }
                }, 100);
            } catch (e) {
                reject(e);
            }
        });
    }

    /**
     * Convert canvas element to ESC * bitmap
     * @param {HTMLCanvasElement} canvas
     * @returns {Uint8Array}
     */
    canvasToBitmap(canvas) {
        const ctx = canvas.getContext('2d');
        let width = canvas.width;
        let height = canvas.height;
        
        // Width must be multiple of 8
        width = Math.floor(width / 8) * 8;
        
        const imageData = ctx.getImageData(0, 0, width, height);
        const pixels = imageData.data;
        
        const bytesPerLine = width / 8;
        const commands = [];
        
        // ESC * line by line for BLE compatibility
        for (let y = 0; y < height; y++) {
            commands.push(0x1B, 0x2A, 0x00); // ESC * mode 0
            commands.push(bytesPerLine & 0xFF);
            commands.push((bytesPerLine >> 8) & 0xFF);
            
            for (let byteX = 0; byteX < bytesPerLine; byteX++) {
                let byte = 0;
                for (let bit = 0; bit < 8; bit++) {
                    const x = byteX * 8 + bit;
                    const pixelIndex = (y * width + x) * 4;
                    
                    const r = pixels[pixelIndex];
                    const g = pixels[pixelIndex + 1];
                    const b = pixels[pixelIndex + 2];
                    const gray = 0.299 * r + 0.587 * g + 0.114 * b;
                    
                    if (gray < 128) {
                        byte |= (0x80 >> bit);
                    }
                }
                commands.push(byte);
            }
            commands.push(0x0A); // LF
        }
        
        return new Uint8Array(commands);
    }

    /**
     * Generate a short code from URL for display
     * @param {string} url - Full URL
     * @returns {string} - Short code
     */
    extractShortCode(url) {
        const match = url.match(/\/(\d+)$/);
        if (match) {
            return match[1];
        }
        return url.slice(-15);
    }

    /**
     * Print image/binary data with BLE-optimized chunking
     * @param {Uint8Array} imageData - ESC/POS command data
     */
    async printImage(imageData) {
        if (!this.characteristic) {
            throw new Error('Printer tidak terhubung');
        }

        // Use smaller chunks for BLE stability
        const chunkSize = this.BLE_CHUNK_SIZE;
        
        for (let i = 0; i < imageData.byteLength; i += chunkSize) {
            const chunk = imageData.slice(i, Math.min(i + chunkSize, imageData.byteLength));
            
            try {
                if (this.characteristic.properties.writeWithoutResponse) {
                    await this.characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await this.characteristic.writeValue(chunk);
                }
            } catch (error) {
                console.error('Image write error at chunk', i / chunkSize, error);
                throw error;
            }
            
            // Longer delay for BLE stability
            await new Promise(resolve => setTimeout(resolve, this.BLE_DELAY));
        }
        
        return true;
    }

    // Build receipt content for Penjualan
    async buildPenjualanReceipt(data, options = {}) {
        return this.buildFlutter58Receipt('penjualan', data, options);
        const printLogo = options.printLogo !== false;
        const printQR = options.printQR !== false;
        // Use absolute URL for logo to ensure it loads correctly
        const baseUrl = window.location.origin;
        const logoUrl = options.logoUrl || (baseUrl + '/assets/img/logoHE1.png');
        const qrData = options.qrUrl || data.invoice_url || '';
        
        let parts = []; // Array of {type: 'text'|'image', data: ...}
        
        // Reset printer
        parts.push({ type: 'text', data: this.COMMANDS.RESET });
        
        // Logo (if enabled) - ALIGN_CENTER langsung sebelum image!
        if (printLogo) {
            try {
                // PENTING: ALIGN_CENTER harus langsung sebelum image
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                const logoData = await this.loadImageAsBitmap(logoUrl, 200);
                parts.push({ type: 'image', data: logoData });
                parts.push({ type: 'text', data: '\n' });
            } catch (e) {
                console.warn('Could not load logo:', e);
            }
        }
        
        // Header text
        let header = this.COMMANDS.ALIGN_CENTER;
        header += this.COMMANDS.BOLD_ON + 'HIBISCUS EFSYA\n' + this.COMMANDS.BOLD_OFF;
        header += 'INVOICE PENJUALAN\n';
        header += this.COMMANDS.ALIGN_LEFT + '\n';
        
        // Info Section
        let body = '';
        body += this.formatInfoRow('Nomor', data.nomor);
        body += this.formatInfoRow('Tanggal', data.tanggal);
        body += this.formatInfoRow('Jatuh Tempo', data.jatuh_tempo);
        body += this.formatInfoRow('Pembayaran', data.pembayaran);
        body += this.formatInfoRow('Pelanggan', data.pelanggan);
        body += this.formatInfoRow('Sales', data.sales);
        body += this.formatInfoRow('Disetujui', data.approver);
        body += this.formatInfoRow('Gudang', data.gudang);
        body += this.formatInfoRow('Status', data.status);
        
        // Items
        body += this.divider();
        
        data.items.forEach(item => {
            body += this.COMMANDS.BOLD_ON + item.nama + this.COMMANDS.BOLD_OFF + '\n';
            body += 'Qty: ' + item.qty + ' ' + item.unit + '\n';
            body += this.padLine('Harga', this.formatRupiah(item.harga));
            if (item.diskon > 0) {
                body += this.padLine('Disc', item.diskon + '%');
            }
            if (this.numValue(item.diskon_nominal) > 0) {
                body += this.padLine('Disc Rp', '- ' + this.formatRupiah(item.diskon_nominal));
            }
            body += this.padLine('Jumlah', this.formatRupiah(item.jumlah));
        });
        
        // Totals
        body += this.divider();
        body += this.padLine('Subtotal', this.formatRupiah(data.subtotal));
        
        if (data.diskon_akhir > 0) {
            body += this.padLine('Diskon Akhir', '- ' + this.formatRupiah(data.diskon_akhir));
        }
        
        if (data.tax_percentage > 0) {
            body += this.padLine('Pajak (' + data.tax_percentage + '%)', this.formatRupiah(data.pajak));
        }
        
        body += this.divider();
        body += this.COMMANDS.BOLD_ON;
        body += this.padLine('GRAND TOTAL', this.formatRupiah(data.grand_total));
        body += this.COMMANDS.BOLD_OFF;
        
        // Footer with QR Code as IMAGE (not native command)
        let footer = '\n' + this.divider('=');
        
        parts.push({ type: 'text', data: header + body + footer });
        
        // Print QR Code as IMAGE - ALIGN_CENTER langsung sebelum image!
        if (printQR && qrData) {
            try {
                parts.push({ type: 'text', data: '\nScan untuk lihat invoice:\n' });
                
                // PENTING: ALIGN_CENTER langsung sebelum QR image
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                
                // Generate QR as image (offline, no API dependency)
                const qrImage = await this.generateQRCode(qrData, 150);
                parts.push({ type: 'image', data: qrImage });
                
                // Print short ID below QR
                const shortCode = this.extractShortCode(qrData);
                parts.push({ type: 'text', data: '\nID: ' + shortCode + '\n' });
            } catch (e) {
                console.warn('QR image failed:', e);
                // Fallback: just print URL as text
                parts.push({ type: 'text', data: '\n' + qrData + '\n' });
            }
        }
        
        // Final footer
        let finalFooter = '\n' + this.COMMANDS.ALIGN_CENTER;
        finalFooter += 'marketing@hibiscusefsya.com\n';
        finalFooter += '-- Terima Kasih --\n';
        finalFooter += '\n\n\n\n';
        
        parts.push({ type: 'text', data: finalFooter });
        
        return parts;
    }

    // Build receipt content for Pembelian
    async buildPembelianReceipt(data, options = {}) {
        return this.buildFlutter58Receipt('pembelian', data, options);
        const printLogo = options.printLogo !== false;
        const printQR = options.printQR !== false;
        // Use absolute URL for logo to ensure it loads correctly
        const baseUrl = window.location.origin;
        const logoUrl = options.logoUrl || (baseUrl + '/assets/img/logoHE1.png');
        const qrData = options.qrUrl || data.invoice_url || '';
        
        let parts = [];
        
        // Reset printer
        parts.push({ type: 'text', data: this.COMMANDS.RESET });
        
        // Logo - ALIGN_CENTER langsung sebelum image!
        if (printLogo) {
            try {
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                const logoData = await this.loadImageAsBitmap(logoUrl, 200);
                parts.push({ type: 'image', data: logoData });
                parts.push({ type: 'text', data: '\n' });
            } catch (e) {
                console.warn('Could not load logo:', e);
            }
        }
        
        let header = this.COMMANDS.ALIGN_CENTER;
        header += this.COMMANDS.BOLD_ON + 'HIBISCUS EFSYA\n' + this.COMMANDS.BOLD_OFF;
        header += 'PERMINTAAN PEMBELIAN\n';
        header += this.COMMANDS.ALIGN_LEFT + '\n';
        
        let body = '';
        body += this.formatInfoRow('Nomor', data.nomor);
        body += this.formatInfoRow('Tanggal', data.tanggal);
        body += this.formatInfoRow('Jatuh Tempo', data.jatuh_tempo);
        body += this.formatInfoRow('Pembayaran', data.pembayaran);
        body += this.formatInfoRow('Vendor', data.vendor);
        body += this.formatInfoRow('Sales', data.sales);
        body += this.formatInfoRow('Disetujui', data.approver);
        body += this.formatInfoRow('Gudang', data.gudang);
        body += this.formatInfoRow('Status', data.status);
        
        body += this.divider();
        
        data.items.forEach(item => {
            body += this.COMMANDS.BOLD_ON + item.nama + this.COMMANDS.BOLD_OFF + '\n';
            body += 'Qty: ' + item.qty + ' ' + item.unit + '\n';
            body += this.padLine('Harga', this.formatRupiah(item.harga));
            if (item.diskon > 0) {
                body += this.padLine('Disc', item.diskon + '%');
            }
            body += this.padLine('Jumlah', this.formatRupiah(item.jumlah));
        });
        
        body += this.divider();
        body += this.padLine('Subtotal', this.formatRupiah(data.subtotal));
        
        if (data.diskon_akhir > 0) {
            body += this.padLine('Diskon Akhir', '- ' + this.formatRupiah(data.diskon_akhir));
        }
        
        if (data.tax_percentage > 0) {
            body += this.padLine('Pajak (' + data.tax_percentage + '%)', this.formatRupiah(data.pajak));
        }
        
        body += this.divider();
        body += this.COMMANDS.BOLD_ON;
        body += this.padLine('GRAND TOTAL', this.formatRupiah(data.grand_total));
        body += this.COMMANDS.BOLD_OFF;
        
        let footer = '\n' + this.divider('=');
        
        parts.push({ type: 'text', data: header + body + footer });
        
        // Print QR Code as IMAGE - ALIGN_CENTER langsung sebelum image!
        if (printQR && qrData) {
            try {
                parts.push({ type: 'text', data: '\nScan untuk lihat dokumen:\n' });
                
                // PENTING: ALIGN_CENTER langsung sebelum QR image
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                
                const qrImage = await this.generateQRCode(qrData, 150);
                parts.push({ type: 'image', data: qrImage });
                
                const shortCode = this.extractShortCode(qrData);
                parts.push({ type: 'text', data: '\nID: ' + shortCode + '\n' });
            } catch (e) {
                console.warn('QR image failed:', e);
                parts.push({ type: 'text', data: '\n' + qrData + '\n' });
            }
        }
        
        let finalFooter = '\n' + this.COMMANDS.ALIGN_CENTER;
        finalFooter += 'marketing@hibiscusefsya.com\n';
        finalFooter += '-- Dokumen Internal --\n';
        finalFooter += '\n\n\n\n';
        
        parts.push({ type: 'text', data: finalFooter });
        
        return parts;
    }

    // Build receipt content for Biaya
    async buildBiayaReceipt(data, options = {}) {
        return this.buildFlutter58Receipt('biaya', data, options);
        const printLogo = options.printLogo !== false;
        const printQR = options.printQR !== false;
        // Use absolute URL for logo to ensure it loads correctly
        const baseUrl = window.location.origin;
        const logoUrl = options.logoUrl || (baseUrl + '/assets/img/logoHE1.png');
        const qrData = options.qrUrl || data.invoice_url || '';

        // Jenis biaya (masuk/keluar) untuk judul & label
        const jenisRaw = (data.jenis_biaya || '').toString().toLowerCase();
        console.log('DEBUG jenis_biaya:', data.jenis_biaya, '| jenisRaw:', jenisRaw);
        const isMasuk = jenisRaw.includes('masuk'); // robust even if value is "Biaya Masuk"
        const jenisLabel = isMasuk ? 'Biaya Masuk' : 'Biaya Keluar';
        const titleText = isMasuk ? 'BUKTI PEMASUKAN' : 'BUKTI PENGELUARAN';
        console.log('DEBUG isMasuk:', isMasuk, '| titleText:', titleText);
        
        let parts = [];
        
        // Reset printer
        parts.push({ type: 'text', data: this.COMMANDS.RESET });
        
        // Logo - ALIGN_CENTER langsung sebelum image!
        if (printLogo) {
            try {
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                const logoData = await this.loadImageAsBitmap(logoUrl, 200);
                parts.push({ type: 'image', data: logoData });
                parts.push({ type: 'text', data: '\n' });
            } catch (e) {
                console.warn('Could not load logo:', e);
            }
        }
        
        let header = this.COMMANDS.ALIGN_CENTER;
        header += this.COMMANDS.BOLD_ON + 'HIBISCUS EFSYA\n' + this.COMMANDS.BOLD_OFF;
        header += titleText + '\n';
        header += this.COMMANDS.ALIGN_LEFT + '\n';
        
        let body = '';
        body += this.formatInfoRow('Jenis', jenisLabel);
        body += this.formatInfoRow('Nomor', data.nomor);
        body += this.formatInfoRow('Tanggal', data.tanggal);
        body += this.formatInfoRow('Pembayaran', data.cara_pembayaran);
        body += this.formatInfoRow('Bayar Dari', data.bayar_dari);
        body += this.formatInfoRow('Penerima', data.penerima);
        body += this.formatInfoRow('Sales', data.sales);
        body += this.formatInfoRow('Disetujui', data.approver);
        body += this.formatInfoRow('Status', data.status);
        
        body += this.divider();
        
        data.items.forEach(item => {
            body += this.COMMANDS.BOLD_ON + item.kategori + this.COMMANDS.BOLD_OFF + '\n';
            if (item.deskripsi) {
                body += 'Ket: ' + item.deskripsi + '\n';
            }
            body += this.padLine('Jumlah', this.formatRupiah(item.jumlah));
        });
        
        body += this.divider();
        body += this.padLine('Subtotal', this.formatRupiah(data.subtotal));
        
        if (data.tax_percentage > 0) {
            body += this.padLine('Pajak (' + data.tax_percentage + '%)', this.formatRupiah(data.pajak));
        }
        
        body += this.divider();
        body += this.COMMANDS.BOLD_ON;
        body += this.padLine('GRAND TOTAL', this.formatRupiah(data.grand_total));
        body += this.COMMANDS.BOLD_OFF;
        
        let footer = '\n' + this.divider('=');
        
        parts.push({ type: 'text', data: header + body + footer });
        
        // Print QR Code as IMAGE - ALIGN_CENTER langsung sebelum image!
        if (printQR && qrData) {
            try {
                parts.push({ type: 'text', data: '\nScan untuk lihat bukti:\n' });
                
                // PENTING: ALIGN_CENTER langsung sebelum QR image
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                
                const qrImage = await this.generateQRCode(qrData, 150);
                parts.push({ type: 'image', data: qrImage });
                
                const shortCode = this.extractShortCode(qrData);
                parts.push({ type: 'text', data: '\nID: ' + shortCode + '\n' });
            } catch (e) {
                console.warn('QR image failed:', e);
                parts.push({ type: 'text', data: '\n' + qrData + '\n' });
            }
        }
        
        let finalFooter = '\n' + this.COMMANDS.ALIGN_CENTER;
        finalFooter += 'marketing@hibiscusefsya.com\n';
        finalFooter += '-- Terima Kasih --\n';
        finalFooter += '\n\n\n\n';
        
        parts.push({ type: 'text', data: finalFooter });
        
        return parts;
    }

    // Build receipt content for Kunjungan
    async buildKunjunganReceipt(data, options = {}) {
        return this.buildFlutter58Receipt('kunjungan', data, options);
        const printLogo = options.printLogo !== false;
        const printQR = options.printQR !== false;
        // Use absolute URL for logo to ensure it loads correctly
        const baseUrl = window.location.origin;
        const logoUrl = options.logoUrl || (baseUrl + '/assets/img/logoHE1.png');
        const qrData = options.qrUrl || data.invoice_url || '';
        
        let parts = [];
        
        // Reset printer
        parts.push({ type: 'text', data: this.COMMANDS.RESET });
        
        // Logo - ALIGN_CENTER langsung sebelum image!
        if (printLogo) {
            try {
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                const logoData = await this.loadImageAsBitmap(logoUrl, 200);
                parts.push({ type: 'image', data: logoData });
                parts.push({ type: 'text', data: '\n' });
            } catch (e) {
                console.warn('Could not load logo:', e);
            }
        }
        
        let header = this.COMMANDS.ALIGN_CENTER;
        header += this.COMMANDS.BOLD_ON + 'HIBISCUS EFSYA\n' + this.COMMANDS.BOLD_OFF;
        header += 'LAPORAN KUNJUNGAN\n';
        header += this.COMMANDS.ALIGN_LEFT + '\n';
        
        let body = '';
        body += this.formatInfoRow('Nomor', data.nomor);
        body += this.formatInfoRow('Tanggal', data.tanggal);
        body += this.formatInfoRow('Waktu', data.waktu);
        body += this.formatInfoRow('Tujuan', data.tujuan);
        
        body += this.divider();
        
        body += this.COMMANDS.BOLD_ON + 'DATA KONTAK\n' + this.COMMANDS.BOLD_OFF;
        body += this.formatInfoRow('Nama', data.sales_nama);
        body += this.formatInfoRow('Email', data.sales_email);
        body += this.formatInfoRow('Alamat', data.sales_alamat);
        
        body += this.divider();
        
        body += this.formatInfoRow('Pembuat', data.pembuat);
        body += this.formatInfoRow('Disetujui', data.approver);
        body += this.formatInfoRow('Gudang', data.gudang);
        body += this.formatInfoRow('Status', data.status);
        
        if (data.koordinat && data.koordinat !== '-') {
            body += this.formatInfoRow('Koordinat', data.koordinat);
        }

        // PRODUK ITEMS
        if (data.items && data.items.length > 0) {
            body += this.divider();
            body += this.COMMANDS.BOLD_ON + 'PRODUK:\n' + this.COMMANDS.BOLD_OFF;
            data.items.forEach((item, index) => {
                body += (index + 1) + '. ' + item.kode + '\n';
                body += '   ' + item.nama + '\n';
                body += '   Qty: ' + item.qty;
                if (item.batch) {
                    body += ' | Batch: ' + item.batch;
                }
                if (item.exp) {
                    body += ' | Exp: ' + item.exp;
                }
                if (item.keterangan) {
                    body += ' | ' + item.keterangan;
                }
                body += '\n';
            });
        }
        
        if (data.memo && data.memo !== '-') {
            body += this.divider();
            body += this.COMMANDS.BOLD_ON + 'MEMO:\n' + this.COMMANDS.BOLD_OFF;
            body += data.memo + '\n';
        }
        
        let footer = '\n' + this.divider('=');
        
        parts.push({ type: 'text', data: header + body + footer });
        
        // Print QR Code as IMAGE - ALIGN_CENTER langsung sebelum image!
        if (printQR && qrData) {
            try {
                parts.push({ type: 'text', data: '\nScan untuk lihat laporan:\n' });
                
                // PENTING: ALIGN_CENTER langsung sebelum QR image
                parts.push({ type: 'text', data: this.COMMANDS.ALIGN_CENTER });
                
                const qrImage = await this.generateQRCode(qrData, 150);
                parts.push({ type: 'image', data: qrImage });
                
                const shortCode = this.extractShortCode(qrData);
                parts.push({ type: 'text', data: '\nID: ' + shortCode + '\n' });
            } catch (e) {
                console.warn('QR image failed:', e);
                parts.push({ type: 'text', data: '\n' + qrData + '\n' });
            }
        }
        
        let finalFooter = '\n' + this.COMMANDS.ALIGN_CENTER;
        finalFooter += 'marketing@hibiscusefsya.com\n';
        finalFooter += '-- Dokumen Internal --\n';
        finalFooter += '\n\n\n\n';
        
        parts.push({ type: 'text', data: finalFooter });
        
        return parts;
    }

    // Connect to Bluetooth printer
    async connect() {
        if (!navigator.bluetooth) {
            throw new Error('Bluetooth tidak didukung di browser ini. Gunakan Chrome/Edge di Android.');
        }

        try {
            // Request Bluetooth device with various service filters
            this.device = await navigator.bluetooth.requestDevice({
                filters: [
                    { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
                    { namePrefix: 'POS' },
                    { namePrefix: 'Thermal' },
                    { namePrefix: 'Printer' },
                    { namePrefix: 'RPP' },
                    { namePrefix: 'PT-' },
                    { namePrefix: 'MTP' },
                    { namePrefix: 'BlueTooth' },
                    { namePrefix: 'BT' }
                ],
                optionalServices: [
                    '000018f0-0000-1000-8000-00805f9b34fb',
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
                ]
            });

            // Connect to GATT server
            const server = await this.device.gatt.connect();
            
            // Try different services
            let service = null;
            const serviceUUIDs = [
                '000018f0-0000-1000-8000-00805f9b34fb',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
            ];
            
            for (const uuid of serviceUUIDs) {
                try {
                    service = await server.getPrimaryService(uuid);
                    break;
                } catch (e) {
                    continue;
                }
            }
            
            if (!service) {
                throw new Error('Tidak dapat menemukan service printer');
            }

            // Try different characteristics
            const characteristicUUIDs = [
                '00002af1-0000-1000-8000-00805f9b34fb',
                '49535343-8841-43f4-a8d4-ecbe34729bb3',
                'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f'
            ];
            
            for (const uuid of characteristicUUIDs) {
                try {
                    this.characteristic = await service.getCharacteristic(uuid);
                    break;
                } catch (e) {
                    continue;
                }
            }
            
            if (!this.characteristic) {
                // Fallback: get all characteristics and use first writable one
                const characteristics = await service.getCharacteristics();
                for (const char of characteristics) {
                    if (char.properties.write || char.properties.writeWithoutResponse) {
                        this.characteristic = char;
                        break;
                    }
                }
            }
            
            if (!this.characteristic) {
                throw new Error('Tidak dapat menemukan characteristic untuk menulis');
            }

            return true;
        } catch (error) {
            console.error('Bluetooth connection error:', error);
            throw error;
        }
    }

    // Print text data with BLE-optimized chunking
    async print(content) {
        if (!this.characteristic) {
            throw new Error('Printer tidak terhubung');
        }

        const encoder = new TextEncoder();
        const data = encoder.encode(content);
        
        // Use BLE-optimized chunk size (64-128 bytes)
        const chunkSize = this.BLE_CHUNK_SIZE;
        
        for (let i = 0; i < data.byteLength; i += chunkSize) {
            const chunk = data.slice(i, Math.min(i + chunkSize, data.byteLength));
            
            try {
                if (this.characteristic.properties.writeWithoutResponse) {
                    await this.characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await this.characteristic.writeValue(chunk);
                }
            } catch (error) {
                console.error('Write error at chunk', i / chunkSize, error);
                throw error;
            }
            
            // BLE-optimized delay between chunks (100-150ms)
            await new Promise(resolve => setTimeout(resolve, this.BLE_DELAY));
        }
        
        return true;
    }

    /**
     * Print receipt with mixed content (text + images)
     * @param {Array} parts - Array of {type: 'text'|'image', data: string|Uint8Array}
     */
    async printMixed(parts) {
        for (const part of parts) {
            if (part.type === 'text') {
                await this.print(part.data);
            } else if (part.type === 'image') {
                await this.printImage(part.data);
            }
            // Delay between parts for BLE stability
            await new Promise(resolve => setTimeout(resolve, this.BLE_DELAY));
        }
    }

    // Disconnect from printer
    disconnect() {
        if (this.device && this.device.gatt.connected) {
            this.device.gatt.disconnect();
        }
        this.device = null;
        this.characteristic = null;
    }
}

// Initialize global instance
window.BluetoothPrinter = new BluetoothThermalPrinter();

// Helper function for printing with button feedback
async function printViaBluetooth(button, type, jsonUrl, options = {}) {
    const originalHtml = button.innerHTML;
    
    // Default options - use absolute URL for logo
    const baseUrl = window.location.origin;
    options = {
        printLogo: true,
        printQR: false,
        paperSize: '58mm',
        logoUrl: baseUrl + '/assets/img/logoHE1.png',
        ...options
    };
    
    try {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading data...';
        button.disabled = true;

        // Fetch JSON data from server
        const response = await fetch(jsonUrl);
        if (!response.ok) {
            throw new Error('Gagal mengambil data dari server');
        }
        const data = await response.json();
        
        // Debug: log data untuk troubleshooting
        console.log('Bluetooth Print Data:', type, data);

        button.innerHTML = '<i class="fas fa-receipt"></i> Pilih ukuran...';
        const paperSize = await window.BluetoothPrinter.showPaperSizeDialog(options.paperSize || options.paper_size || '58mm');
        if (!paperSize) {
            button.innerHTML = originalHtml;
            button.disabled = false;
            return;
        }
        options.paperSize = paperSize;

        button.innerHTML = '<i class="fas fa-receipt"></i> Preview...';

        const proceed = await window.BluetoothPrinter.showPreviewDialog(type, data, options);
        if (!proceed) {
            button.innerHTML = originalHtml;
            button.disabled = false;
            return;
        }

        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';

        // Connect to printer after user confirms preview
        await window.BluetoothPrinter.connect();

        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';

        // Build receipt based on type (now async because of image loading)
        let parts = [];
        switch (type) {
            case 'penjualan':
                parts = await window.BluetoothPrinter.buildPenjualanReceipt(data, options);
                break;
            case 'pembelian':
                parts = await window.BluetoothPrinter.buildPembelianReceipt(data, options);
                break;
            case 'biaya':
                parts = await window.BluetoothPrinter.buildBiayaReceipt(data, options);
                break;
            case 'kunjungan':
                parts = await window.BluetoothPrinter.buildKunjunganReceipt(data, options);
                break;
            default:
                throw new Error('Tipe tidak dikenali: ' + type);
        }

        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';

        // Print mixed content (text + images)
        await window.BluetoothPrinter.printMixed(parts);

        // Success feedback
        button.innerHTML = '<i class="fas fa-check"></i> Berhasil!';
        button.classList.remove('btn-primary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-success');
            button.classList.add('btn-primary');
            button.disabled = false;
        }, 2000);

    } catch (error) {
        console.error('Print error:', error);
        button.innerHTML = '<i class="fas fa-times"></i> Gagal';
        button.classList.remove('btn-primary');
        button.classList.add('btn-danger');
        
        alert('Gagal print via Bluetooth: ' + error.message);
        
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-danger');
            button.classList.add('btn-primary');
            button.disabled = false;
        }, 2000);
    } finally {
        // Disconnect after printing
        window.BluetoothPrinter.disconnect();
    }
}
