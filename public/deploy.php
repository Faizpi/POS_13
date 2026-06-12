<?php
/**
 * DEPLOY CONSOLE
 * Helper script to execute artisan commands safely from web interface.
 */

$projectPath = realpath(__DIR__ . '/..');

if (!is_dir($projectPath)) { 
    http_response_code(500); 
    exit("Path not found"); 
}

chdir($projectPath);

if (!file_exists($projectPath . '/vendor/autoload.php')) { 
    http_response_code(500); 
    exit("vendor/autoload.php not found. Please run composer install first."); 
}

require_once $projectPath . '/vendor/autoload.php';
$app = require_once $projectPath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

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
    'Migrate' => 'migrate',
    'Migrate Fresh' => 'migrate:fresh',
    'Migrate Seed' => 'migrate --seed',
    'Filament Upgrade' => 'filament:upgrade',
    'Import Legacy Data' => 'import:legacy-data',
];

$presets = [
    'Quick Deploy' => ['Cache Clear', 'Config Clear', 'Route Clear', 'View Clear', 'Optimize'],
    'Full Clear' => ['Cache Clear', 'Config Clear', 'Route Clear', 'View Clear', 'Optimize Clear'],
    'Filament Assets Update' => ['Filament Upgrade', 'Cache Clear', 'View Clear', 'Optimize'],
    'Database Refresh' => ['Migrate Fresh', 'Migrate Seed', 'Storage Force Link'],
];

$customHandlers = [
    'Storage Force Link' => function() use ($projectPath, $kernel) {
        $publicStorage = $projectPath . '/public/storage';
        if (file_exists($publicStorage)) {
            if (is_link($publicStorage)) {
                unlink($publicStorage);
                echo "✓ Old symlink removed\n";
            } elseif (is_dir($publicStorage)) {
                $backup = $publicStorage . '_backup_' . date('YmdHis');
                rename($publicStorage, $backup);
                echo "⚠ Directory moved to: $backup\n";
            }
        }
        $status = $kernel->call('storage:link', ['--force' => true]);
        echo $kernel->output();
        return $status;
    }
];

// Execute logic
$executionResults = null;
$selectedCommands = isset($_POST['commands']) && is_array($_POST['commands']) ? $_POST['commands'] : [];
$customCommand    = isset($_POST['custom_command']) ? trim($_POST['custom_command']) : '';

if (!empty($selectedCommands) || !empty($customCommand)) {
    if (!empty($customCommand)) {
        $cleanCommand = htmlspecialchars($customCommand);
        $label        = "Custom: $cleanCommand";
        $commands[$label] = $customCommand;
        $selectedCommands[] = $label;
    }

    $totalCommands = count($selectedCommands);
    $successCount  = 0;
    $failCount     = 0;
    $resultLines   = [];

    foreach ($selectedCommands as $index => $label) {
        if (!isset($commands[$label])) {
            $resultLines[] = ['label' => $label, 'cmd' => '—', 'output' => "✗ Invalid command: $label", 'ok' => false, 'ms' => 0];
            $failCount++;
            continue;
        }
        $num = $index + 1;
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
            $ok  = ($status === 0);
            $resultLines[] = ['label' => $label, 'cmd' => $commands[$label], 'output' => $out, 'ok' => $ok, 'ms' => $ms, 'status' => $status];
            $ok ? $successCount++ : $failCount++;
        } catch (Exception $e) {
            $ms = round((microtime(true) - $t0) * 1000, 2);
            ob_get_clean();
            $resultLines[] = ['label' => $label, 'cmd' => $commands[$label], 'output' => "✗ EXCEPTION: " . $e->getMessage(), 'ok' => false, 'ms' => $ms];
            $failCount++;
        }
    }
    $executionResults = ['lines' => $resultLines, 'total' => $totalCommands, 'success' => $successCount, 'fail' => $failCount];
}

$publicStorage   = $projectPath . '/public/storage';
$storageStatus   = is_link($publicStorage) ? ['ok', 'Symlink aktif → ' . readlink($publicStorage)]
                 : (is_dir($publicStorage) ? ['warn', '/public/storage adalah direktori (bukan symlink)']
                 : ['error', 'Storage link tidak ditemukan']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deploy Console — <?= htmlspecialchars(basename($projectPath)) ?></title>
<style>
:root {
  --bg: #0f1117;
  --surface: #181c27;
  --panel: #1e2332;
  --border: #2b3148;
  --accent: #f59e0b;
  --green: #22c55e;
  --red: #ef4444;
  --text: #e2e8f0;
  --muted: #64748b;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  --font-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); padding: 20px; line-height: 1.5; }
.container { max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
.header { display: flex; justify-content: space-between; align-items: center; background: var(--surface); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
.header h1 { font-size: 20px; font-weight: 600; }
.header-path { font-family: var(--font-mono); font-size: 12px; color: var(--muted); background: var(--bg); padding: 4px 8px; border-radius: 4px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }
h2 { font-size: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }

/* Grid Presets */
.preset-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.preset-btn { background: var(--panel); border: 1px solid var(--border); color: var(--text); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: 0.2s; }
.preset-btn:hover { border-color: var(--accent); color: var(--accent); }

/* Grid Commands */
.cmd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.cmd-tile { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; transition: 0.2s; }
.cmd-tile:hover { border-color: var(--accent); }
.cmd-tile label { display: flex; gap: 10px; padding: 12px; cursor: pointer; align-items: center; font-size: 13px; }
.cmd-tile input { accent-color: var(--accent); }
.danger-tile { border-color: rgba(239, 68, 68, 0.3); }
.danger-tile:hover { border-color: var(--red); }

/* Buttons */
.action-bar { margin-top: 20px; display: flex; gap: 12px; align-items: center; }
.btn { padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; border: none; transition: 0.2s; }
.btn-run { background: var(--accent); color: #000; }
.btn-run:hover { background: #fbbf24; }
.btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover { color: var(--text); }

/* Input Custom */
.custom-input { display: flex; gap: 10px; margin-top: 20px; }
.custom-input span { background: var(--panel); padding: 10px 16px; border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-mono); font-size: 13px; color: var(--muted); }
.custom-input input { flex: 1; background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 10px; border-radius: 6px; font-family: var(--font-mono); font-size: 13px; outline: none; }
.custom-input input:focus { border-color: var(--accent); }

/* Terminal */
.terminal { background: #0a0c12; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-top: 20px; }
.terminal-header { background: var(--panel); padding: 8px 16px; font-size: 12px; color: var(--muted); font-family: var(--font-mono); border-bottom: 1px solid var(--border); }
.terminal-body { padding: 16px; font-family: var(--font-mono); font-size: 13px; color: #a1a1aa; line-height: 1.6; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
.t-cmd { color: #fff; font-weight: 600; }
.t-ok { color: var(--green); }
.t-err { color: var(--red); }
.t-warn { color: var(--accent); }

/* Storage */
.storage-pill { display: inline-block; padding: 6px 12px; border-radius: 4px; font-family: var(--font-mono); font-size: 13px; }
.spl-ok { background: rgba(34, 197, 94, 0.1); color: var(--green); border: 1px solid rgba(34, 197, 94, 0.2); }
.spl-warn { background: rgba(245, 158, 11, 0.1); color: var(--accent); border: 1px solid rgba(245, 158, 11, 0.2); }
.spl-error { background: rgba(239, 68, 68, 0.1); color: var(--red); border: 1px solid rgba(239, 68, 68, 0.2); }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>⚡ Deploy Console</h1>
        <div class="header-path"><?= htmlspecialchars($projectPath) ?></div>
    </div>

    <?php if ($executionResults): ?>
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
                foreach ($executionResults['lines'] as $i => $r):
                    echo "<span style='color:#475569'>[" . ($i+1) . "]</span> <span class='t-cmd'>{$r['label']}</span>\n";
                    
                    $out = htmlspecialchars($r['output']);
                    $out = preg_replace('/(✓|SUCCESS|DONE)/i', '<span class="t-ok">$1</span>', $out);
                    $out = preg_replace('/(✗|ERROR|FAILED|FAIL)/i', '<span class="t-err">$1</span>', $out);
                    $out = preg_replace('/(⚠|WARNING)/i', '<span class="t-warn">$1</span>', $out);
                    echo $out . "\n";
                    
                    if ($r['ok']) {
                        echo "<span class='t-ok'>✓ Sukses</span> ({$r['ms']}ms)\n\n";
                    } else {
                        echo "<span class='t-err'>✗ Gagal</span> ({$r['ms']}ms)\n\n";
                    }
                endforeach;
            ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>🚀 Quick Presets</h2>
        <div class="preset-grid">
            <?php foreach ($presets as $name => $cmds): ?>
            <form method="post" style="display:inline-block">
                <?php foreach ($cmds as $c): ?>
                <input type="hidden" name="commands[]" value="<?= htmlspecialchars($c) ?>">
                <?php endforeach; ?>
                <button type="submit" class="preset-btn"><?= htmlspecialchars($name) ?></button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post">
        <div class="card">
            <h2>📦 All Commands</h2>
            <div class="cmd-grid" id="tileGrid">
                <?php foreach ($commands as $label => $cmd): 
                    $isDanger = str_contains($label, 'Fresh') || str_contains($label, 'Unlink');
                ?>
                <div class="cmd-tile <?= $isDanger ? 'danger-tile' : '' ?>">
                    <label>
                        <input type="checkbox" name="commands[]" value="<?= htmlspecialchars($label) ?>">
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="custom-input">
                <span>php artisan</span>
                <input type="text" name="custom_command" placeholder="command:name (optional)">
            </div>

            <div class="action-bar">
                <button type="button" class="btn btn-ghost" onclick="selectAll()">Pilih Semua</button>
                <button type="button" class="btn btn-ghost" onclick="deselectAll()">Reset</button>
                <button type="submit" class="btn btn-run">▶ Jalankan Terpilih</button>
            </div>
        </div>
    </form>

    <div class="card">
        <h2>💾 Status Storage</h2>
        <?php
            [$st, $msg] = $storageStatus;
            $cls = $st === 'ok' ? 'spl-ok' : ($st === 'warn' ? 'spl-warn' : 'spl-error');
            echo "<div class='storage-pill {$cls}'>{$msg}</div>";
        ?>
    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('#tileGrid input').forEach(cb => cb.checked = true);
}
function deselectAll() {
    document.querySelectorAll('#tileGrid input').forEach(cb => cb.checked = false);
}
<?php if ($executionResults): ?>
window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>

</body>
</html>
