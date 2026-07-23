<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * DEPLOY CONSOLE
 * Helper script to execute artisan commands safely from web interface.
 */
$projectPath = realpath(__DIR__.'/..');

if (! is_dir($projectPath)) {
    http_response_code(500);
    exit('Path not found');
}

chdir($projectPath);

if (! file_exists($projectPath.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('vendor/autoload.php not found. Please run composer install first.');
}

require_once $projectPath.'/vendor/autoload.php';
$app = require_once $projectPath.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$commands = [
    'Cache Clear' => 'cache:clear',
    'Config Clear' => 'config:clear',
    'Route Clear' => 'route:clear',
    'View Clear' => 'view:clear',
    'Optimize' => 'optimize',
    'Optimize Clear' => 'optimize:clear',
    'Storage Link' => 'storage:link',
    'Storage Unlink' => 'storage:unlink',
    'Storage Force Link' => 'storage:link --force',
    'Migrate Status' => 'migrate:status',
    'Migrate Production' => 'migrate --force',
    'Sync Legacy Schema' => 'migrate --path=database/migrations/2026_06_19_120000_sync_schema_after_import.php --force',
    'Baseline Legacy Migrations' => 'migrate:mark-ran --except-accounting',
    'Fix Missing Legacy Tables' => 'fix-tables',
    'Seed Accounting COA' => 'db:seed --class=HibiscusEfsyaChartOfAccountsSeeder --force',
    'Filament Upgrade' => 'filament:upgrade',
    'Apply Transaction Integrity Fixes' => 'migrate --force',
    'Audit Transaction Integrity' => 'audit:transaction-integrity',
    'Audit Duplicate Nomor' => 'audit:duplicate-nomor',
    'Audit Stock Consistency' => 'audit:stock-consistency',
];

$presets = [
    'Quick Deploy' => ['Cache Clear', 'Config Clear', 'Route Clear', 'View Clear', 'Optimize'],
    'Full Clear' => ['Cache Clear', 'Config Clear', 'Route Clear', 'View Clear', 'Optimize Clear'],
    'Filament Assets Update' => ['Filament Upgrade', 'Cache Clear', 'View Clear', 'Optimize'],
    'Upgrade Restored Legacy DB' => ['Sync Legacy Schema', 'Baseline Legacy Migrations', 'Fix Missing Legacy Tables', 'Migrate Production', 'Seed Accounting COA', 'Cache Clear', 'View Clear', 'Optimize', 'Migrate Status'],
    'Production Migration' => ['Migrate Production', 'Seed Accounting COA', 'Cache Clear', 'View Clear', 'Optimize', 'Migrate Status'],
    'Transaction Integrity Update' => ['Apply Transaction Integrity Fixes', 'Audit Transaction Integrity', 'Audit Duplicate Nomor', 'Audit Stock Consistency', 'Cache Clear', 'View Clear', 'Optimize', 'Migrate Status'],
];

$customHandlers = [
    'Storage Force Link' => function () use ($projectPath, $kernel) {
        $publicStorage = $projectPath.'/public/storage';
        if (file_exists($publicStorage)) {
            if (is_link($publicStorage)) {
                unlink($publicStorage);
                echo "✓ Old symlink removed\n";
            } elseif (is_dir($publicStorage)) {
                $backup = $publicStorage.'_backup_'.date('YmdHis');
                rename($publicStorage, $backup);
                echo "⚠ Directory moved to: $backup\n";
            }
        }
        $status = $kernel->call('storage:link', ['--force' => true]);
        echo $kernel->output();

        return $status;
    },
];

// ─── Log Viewer ──────────────────────────────────────────────────────────────
$logAction = isset($_GET['log']) ? $_GET['log'] : null;
$logContent = null;
$logLines = (int) ($_GET['lines'] ?? 100);
$logSearch = trim($_GET['search'] ?? '');
$logFile = $projectPath.'/storage/logs/laravel.log';

if ($logAction === 'view') {
    if (file_exists($logFile)) {
        $allLines = file($logFile, FILE_IGNORE_NEW_LINES);
        $allLines = array_reverse($allLines); // newest first
        if ($logSearch !== '') {
            $allLines = array_filter($allLines, fn ($l) => stripos($l, $logSearch) !== false);
            $allLines = array_values($allLines);
        }
        $logContent = implode("\n", array_slice($allLines, 0, $logLines));
    } else {
        $logContent = '(Log file tidak ditemukan)';
    }
}

if ($logAction === 'clear' && file_exists($logFile)) {
    file_put_contents($logFile, '');
    header('Location: ?log=view');
    exit;
}

// ─── Execute logic ────────────────────────────────────────────────────────────
$executionResults = null;
$selectedCommands = isset($_POST['commands']) && is_array($_POST['commands']) ? $_POST['commands'] : [];
$customCommand = isset($_POST['custom_command']) ? trim($_POST['custom_command']) : '';

if (! empty($selectedCommands) || ! empty($customCommand)) {
    if (! empty($customCommand)) {
        $cleanCommand = htmlspecialchars($customCommand);
        $label = "Custom: $cleanCommand";
        $commands[$label] = $customCommand;
        $selectedCommands[] = $label;
    }

    $totalCommands = count($selectedCommands);
    $successCount = 0;
    $failCount = 0;
    $resultLines = [];

    foreach ($selectedCommands as $index => $label) {
        if (! isset($commands[$label])) {
            $resultLines[] = ['label' => $label, 'cmd' => '—', 'output' => "✗ Invalid command: $label", 'ok' => false, 'ms' => 0];
            $failCount++;

            continue;
        }
        ob_start();
        try {
            $t0 = microtime(true);
            if (isset($customHandlers[$label])) {
                $status = $customHandlers[$label]();
            } else {
                $status = $kernel->call($commands[$label]);
                echo $kernel->output();
            }
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $out = ob_get_clean();
            $ok = ($status === 0);
            $resultLines[] = ['label' => $label, 'cmd' => $commands[$label], 'output' => $out, 'ok' => $ok, 'ms' => $ms, 'status' => $status];
            $ok ? $successCount++ : $failCount++;
        } catch (Exception $e) {
            $ms = round((microtime(true) - $t0) * 1000, 2);
            ob_get_clean();
            $resultLines[] = ['label' => $label, 'cmd' => $commands[$label], 'output' => '✗ EXCEPTION: '.$e->getMessage(), 'ok' => false, 'ms' => $ms];
            $failCount++;
        }
    }
    $executionResults = ['lines' => $resultLines, 'total' => $totalCommands, 'success' => $successCount, 'fail' => $failCount];
}

$publicStorage = $projectPath.'/public/storage';
$storageStatus = is_link($publicStorage) ? ['ok',    'Symlink aktif → '.readlink($publicStorage)]
               : (is_dir($publicStorage) ? ['warn',  '/public/storage adalah direktori (bukan symlink)']
               : ['error', 'Storage link tidak ditemukan']);

// Log file size
$logSize = file_exists($logFile) ? round(filesize($logFile) / 1024, 1).' KB' : 'N/A';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deploy Console — <?= htmlspecialchars(basename($projectPath)) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

:root {
  --canvas: #f5f5f4;
  --surface: #ffffff;
  --surface-soft: #fafaf9;
  --ink: #202124;
  --ink-soft: #4d5056;
  --muted: #6f737b;
  --line: #dededb;
  --line-strong: #c9cac6;
  --lime: #daf39f;
  --lime-strong: #bde867;
  --blue: #dceff7;
  --blue-strong: #3d7f9c;
  --lilac: #ebd3ff;
  --lilac-strong: #8655ad;
  --yellow: #ffdeb0;
  --yellow-strong: #9b6016;
  --danger: #b42318;
  --danger-soft: #fee4e2;
  --success: #237a45;
  --success-soft: #dcf3e5;
  --font-mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  --font-sans: "Manrope", ui-sans-serif, system-ui, sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { background: var(--canvas); }
body { min-height: 100vh; background: var(--canvas); color: var(--ink); font-family: var(--font-sans); padding: 28px; line-height: 1.5; }
button, input, select { font: inherit; }
.container { width: min(1180px, 100%); margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.header { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; padding: 10px 2px 20px; border-bottom: 1px solid var(--line-strong); }
.header h1 { font-size: 32px; line-height: 1.08; font-weight: 800; letter-spacing: -0.035em; text-wrap: balance; }
.header h1::before { content: "HE"; display: inline-grid; place-items: center; width: 42px; height: 42px; margin-right: 12px; border-radius: 12px; background: var(--lilac); color: var(--ink); font-size: 13px; letter-spacing: -0.02em; vertical-align: 5px; }
.header-path { max-width: 52%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: var(--font-mono); font-size: 11px; color: var(--ink-soft); background: var(--blue); padding: 9px 12px; border-radius: 8px; }
.card, .tab-content { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 24px; }
h2 { font-size: 15px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; letter-spacing: -0.015em; }

.tabs { display: flex; gap: 6px; margin: 0 0 10px; padding: 4px; width: fit-content; background: #e9e9e6; border-radius: 10px; }
.tab { padding: 9px 16px; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; color: var(--ink-soft); transition: background-color 180ms ease, color 180ms ease; }
.tab.active { background: var(--ink); color: #fff; }
.tab:hover:not(.active) { background: var(--surface); color: var(--ink); }
.tab:focus-visible, .btn:focus-visible, .preset-btn:focus-visible, .cmd-tile:focus-within, input:focus-visible, select:focus-visible { outline: 3px solid rgba(61, 127, 156, 0.28); outline-offset: 2px; }
.tab-content { border-radius: 14px; }

.preset-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; }
.preset-btn { width: 100%; min-height: 70px; background: var(--lime); border: 0; color: var(--ink); padding: 14px 16px; border-radius: 11px; cursor: pointer; text-align: left; font-size: 13px; font-weight: 800; line-height: 1.35; transition: background-color 180ms ease, transform 180ms ease; }
.preset-grid form:nth-child(2n) .preset-btn { background: var(--blue); }
.preset-grid form:nth-child(3n) .preset-btn { background: var(--lilac); }
.preset-grid form:nth-child(4n) .preset-btn { background: var(--yellow); }
.preset-btn:hover { transform: translateY(-2px); background: var(--lime-strong); }

.cmd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 8px; }
.cmd-tile { background: var(--surface-soft); border: 1px solid var(--line); border-radius: 10px; cursor: pointer; transition: border-color 180ms ease, background-color 180ms ease; }
.cmd-tile:hover { background: var(--blue); border-color: var(--blue-strong); }
.cmd-tile label { display: flex; gap: 10px; min-height: 48px; padding: 11px 12px; cursor: pointer; align-items: center; font-size: 12px; font-weight: 650; }
.cmd-tile input { width: 16px; height: 16px; accent-color: var(--ink); flex: 0 0 auto; }
.danger-tile { background: var(--danger-soft); border-color: #f4b8b3; }
.danger-tile:hover { background: #ffd5d1; border-color: var(--danger); }

.action-bar { margin-top: 18px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.btn { min-height: 40px; padding: 9px 16px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; border: 0; transition: background-color 180ms ease, transform 180ms ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.btn:hover { transform: translateY(-1px); }
.btn-run { background: var(--ink); color: #fff; margin-left: auto; }
.btn-run:hover { background: #35373b; }
.btn-ghost { background: #ececea; color: var(--ink-soft); }
.btn-ghost:hover { background: var(--lilac); color: var(--ink); }
.btn-blue { background: var(--blue); color: var(--ink); }
.btn-blue:hover { background: #c8e7f3; }
.btn-red { background: var(--danger-soft); color: var(--danger); }
.btn-red:hover { background: #ffd5d1; }

.custom-input { display: flex; gap: 0; margin-top: 18px; }
.custom-input span { display: flex; align-items: center; background: var(--ink); padding: 10px 13px; border-radius: 8px 0 0 8px; font-family: var(--font-mono); font-size: 11px; color: #fff; white-space: nowrap; }
.custom-input input { flex: 1; min-width: 0; background: var(--surface-soft); border: 1px solid var(--line-strong); color: var(--ink); padding: 10px 12px; border-radius: 0 8px 8px 0; font-family: var(--font-mono); font-size: 12px; outline: none; }
.custom-input input::placeholder, .log-toolbar input::placeholder { color: #656970; opacity: 1; }
.custom-input input:focus { border-color: var(--blue-strong); background: #fff; }

.terminal { background: var(--ink); border-radius: 12px; overflow: hidden; margin-top: 18px; }
.terminal-header { background: #303236; padding: 10px 14px; font-size: 11px; color: #d9dadc; font-family: var(--font-mono); display: flex; justify-content: space-between; align-items: center; }
.terminal-body { padding: 16px; font-family: var(--font-mono); font-size: 11px; color: #d4d6d9; line-height: 1.7; max-height: 560px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
.t-cmd { color: #fff; font-weight: 700; }
.t-ok { color: #b7efc9; }
.t-err { color: #ffaaa3; }
.t-warn { color: var(--yellow); }

.log-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.log-toolbar input[type=text], .log-toolbar select { min-height: 40px; background: var(--surface-soft); border: 1px solid var(--line-strong); color: var(--ink); padding: 8px 11px; border-radius: 8px; font-size: 12px; outline: none; }
.log-toolbar input[type=text] { width: min(240px, 100%); font-family: var(--font-mono); }
.log-toolbar input[type=text]:focus, .log-toolbar select:focus { border-color: var(--blue-strong); background: #fff; }
.log-meta { font-size: 11px; color: var(--muted); margin-left: auto; }
.log-highlight-wa { color: #9edcf2; }
.log-highlight-err { color: #ffaaa3; }
.log-highlight-info { color: #b7efc9; }

.storage-pill { display: inline-flex; align-items: center; padding: 9px 12px; border-radius: 8px; font-family: var(--font-mono); font-size: 11px; font-weight: 700; }
.spl-ok { background: var(--success-soft); color: var(--success); }
.spl-warn { background: var(--yellow); color: var(--yellow-strong); }
.spl-error { background: var(--danger-soft); color: var(--danger); }

@media (max-width: 720px) {
  body { padding: 16px; }
  .header { align-items: flex-start; flex-direction: column; gap: 12px; }
  .header h1 { font-size: 26px; }
  .header-path { max-width: 100%; width: 100%; }
  .card, .tab-content { padding: 17px; }
  .tabs { width: 100%; }
  .tab { flex: 1; text-align: center; }
  .custom-input { flex-direction: column; gap: 6px; }
  .custom-input span, .custom-input input { border-radius: 8px; }
  .btn-run { width: 100%; margin-left: 0; order: -1; }
  .log-meta { width: 100%; margin-left: 0; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Deploy workspace</h1>
            <p style="margin-top:7px;color:var(--muted);font-size:12px;font-weight:600">Release, migrate, dan audit aplikasi dari satu ruang kerja yang terarah.</p>
        </div>
        <div class="header-path" title="<?= htmlspecialchars($projectPath) ?>"><?= htmlspecialchars($projectPath) ?></div>
    </div>

    <?php if ($executionResults) { ?>
    <div class="card" id="results">
        <h2>Output Eksekusi</h2>
        <div style="font-size: 13px; margin-bottom: 10px;">
            Total: <?= $executionResults['total'] ?> |
            <span style="color:var(--green)">Sukses: <?= $executionResults['success'] ?></span> |
            <span style="color:var(--red)">Gagal: <?= $executionResults['fail'] ?></span>
        </div>
        <div class="terminal">
            <div class="terminal-header">Terminal Output</div>
            <div class="terminal-body"><?php
                foreach ($executionResults['lines'] as $i => $r) {
                    echo "<span style='color:#475569'>[".($i + 1)."]</span> <span class='t-cmd'>{$r['label']}</span>\n";
                    $out = htmlspecialchars($r['output']);
                    $out = preg_replace('/(✓|SUCCESS|DONE)/i', '<span class="t-ok">$1</span>', $out);
                    $out = preg_replace('/(✗|ERROR|FAILED|FAIL)/i', '<span class="t-err">$1</span>', $out);
                    $out = preg_replace('/(⚠|WARNING)/i', '<span class="t-warn">$1</span>', $out);
                    echo $out."\n";
                    echo $r['ok']
                        ? "<span class='t-ok'>✓ Sukses</span> ({$r['ms']}ms)\n\n"
                        : "<span class='t-err'>✗ Gagal</span> ({$r['ms']}ms)\n\n";
                }
        ?></div>
        </div>
    </div>
    <?php } ?>

    <!-- TABS -->
    <div>
        <div class="tabs">
            <a class="tab <?= ! $logAction ? 'active' : '' ?>" href="?">Deploy</a>
            <a class="tab <?= $logAction ? 'active' : '' ?>" href="?log=view">Log viewer</a>
        </div>

        <?php if (! $logAction) { ?>
        <!-- ── DEPLOY TAB ── -->
        <div class="tab-content">
            <h2 style="margin-bottom:6px">Quick presets</h2>
            <p style="margin-bottom:16px;color:var(--muted);font-size:12px;max-width:68ch">Pilih alur siap pakai untuk deployment rutin, upgrade database lama, atau pemeriksaan integritas.</p>
            <div class="preset-grid">
                <?php foreach ($presets as $name => $cmds) { ?>
                <form method="post" style="display:inline-block">
                    <?php foreach ($cmds as $c) { ?>
                    <input type="hidden" name="commands[]" value="<?= htmlspecialchars($c) ?>">
                    <?php } ?>
                    <button type="submit" class="preset-btn"><?= htmlspecialchars($name) ?></button>
                </form>
                <?php } ?>
            </div>

            <form method="post" style="margin-top:24px">
                <h2 style="margin-bottom:6px">Command library</h2>
                <p style="margin-bottom:16px;color:var(--muted);font-size:12px;max-width:68ch">Susun eksekusi manual. Command berjalan berurutan sesuai pilihan yang dikirim.</p>
                <div class="cmd-grid" id="tileGrid">
                    <?php foreach ($commands as $label => $cmd) {
                        $isDanger = str_contains($label, 'Fresh') || str_contains($label, 'Unlink');
                        ?>
                    <div class="cmd-tile <?= $isDanger ? 'danger-tile' : '' ?>">
                        <label>
                            <input type="checkbox" name="commands[]" value="<?= htmlspecialchars($label) ?>">
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    </div>
                    <?php } ?>
                </div>

                <div class="custom-input">
                    <span>php artisan</span>
                    <input type="text" name="custom_command" placeholder="command:name (optional)">
                </div>

                <div class="action-bar">
                    <button type="button" class="btn btn-ghost" onclick="selectAll()">Pilih Semua</button>
                    <button type="button" class="btn btn-ghost" onclick="deselectAll()">Reset</button>
                    <button type="submit" class="btn btn-run">Jalankan command</button>
                </div>
            </form>

            <div style="margin-top:24px">
                <h2>Storage status</h2>
                <?php
                        [$st, $msg] = $storageStatus;
            $cls = $st === 'ok' ? 'spl-ok' : ($st === 'warn' ? 'spl-warn' : 'spl-error');
            echo "<div class='storage-pill {$cls}'>{$msg}</div>";
            ?>
            </div>
        </div>

        <?php } else { ?>
        <!-- ── LOG VIEWER TAB ── -->
        <div class="tab-content">
            <div class="log-toolbar">
                <form method="get" style="display:contents">
                    <input type="hidden" name="log" value="view">
                    <input type="text" name="search" placeholder="Filter: WA, Fonnte, ERROR..." value="<?= htmlspecialchars($logSearch) ?>">
                    <select name="lines">
                        <?php foreach ([50, 100, 200, 500, 1000] as $n) { ?>
                        <option value="<?= $n ?>" <?= $logLines === $n ? 'selected' : '' ?>><?= $n ?> baris</option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="btn btn-blue">🔍 Tampilkan</button>
                </form>

                <!-- Shortcut filter buttons -->
                <a href="?log=view&search=WA+customer&lines=<?= $logLines ?>" class="btn btn-ghost">WA Customer</a>
                <a href="?log=view&search=WA+admin&lines=<?= $logLines ?>" class="btn btn-ghost">WA Admin</a>
                <a href="?log=view&search=Fonnte&lines=<?= $logLines ?>" class="btn btn-ghost">Fonnte</a>
                <a href="?log=view&search=ERROR&lines=<?= $logLines ?>" class="btn btn-ghost" style="color:var(--red)">ERROR</a>
                <a href="?log=view&lines=<?= $logLines ?>" class="btn btn-ghost">Semua</a>

                <span class="log-meta">📁 laravel.log — <?= $logSize ?></span>
                <a href="?log=clear" class="btn btn-red" onclick="return confirm('Yakin hapus semua log?')">🗑 Clear Log</a>
            </div>

            <div class="terminal">
                <div class="terminal-header">
                    <span>storage/logs/laravel.log <?= $logSearch ? '| filter: "'.htmlspecialchars($logSearch).'"' : '' ?> | <?= $logLines ?> baris terbaru</span>
                    <span style="color:var(--muted)">terbaru di atas</span>
                </div>
                <div class="terminal-body"><?php
                if ($logContent !== null) {
                    $lines = explode("\n", $logContent);
                    foreach ($lines as $line) {
                        $escaped = htmlspecialchars($line);
                        // Highlight berdasarkan konten
                        if (stripos($line, 'WA customer') !== false || stripos($line, 'WA admin') !== false || stripos($line, 'Fonnte') !== false) {
                            echo "<span class='log-highlight-wa'>{$escaped}</span>\n";
                        } elseif (stripos($line, '.ERROR') !== false || stripos($line, 'EXCEPTION') !== false) {
                            echo "<span class='log-highlight-err'>{$escaped}</span>\n";
                        } elseif (stripos($line, '.INFO') !== false) {
                            echo "<span class='log-highlight-info'>{$escaped}</span>\n";
                        } else {
                            echo "{$escaped}\n";
                        }
                    }
                }
            ?></div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<script>
function selectAll()   { document.querySelectorAll('#tileGrid input').forEach(cb => cb.checked = true); }
function deselectAll() { document.querySelectorAll('#tileGrid input').forEach(cb => cb.checked = false); }
<?php if ($executionResults) { ?>
window.scrollTo({ top: 0, behavior: 'smooth' });
<?php } ?>
</script>

</body>
</html>