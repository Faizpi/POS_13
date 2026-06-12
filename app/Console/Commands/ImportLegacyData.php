<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:legacy-data {file=backup_laravel7.sql : Path to the SQL dump file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from legacy database SQL dump without running DDL statements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = base_path($this->argument('file'));

        if (!File::exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Starting data import from {$file}...");

        $tables = [
            'gudangs',
            'produks',
            'users',
            'admin_gudang',
            'spectator_gudang',
            'gudang_produk',
            'stok_logs',
            'kontaks',
            'penjualans',
            'penjualan_items',
            'pembelians',
            'pembelian_items',
            'biayas',
            'biaya_items',
            'kunjungans',
            'kunjungan_items',
            'pembayarans',
            'penerimaan_barangs',
            'penerimaan_barang_items',
            'password_resets',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->info("Truncating tables...");
        foreach (array_reverse($tables) as $table) {
            DB::table($table)->truncate();
        }

        $this->info("Parsing and importing data...");
        $handle = fopen($file, "r");
        
        $tablePattern = implode('|', $tables);
        $regex = "/^INSERT INTO \`($tablePattern)\`/i";

        $isInsert = false;
        $currentQuery = '';
        $currentTable = '';

        DB::beginTransaction();
        try {
            while (($line = fgets($handle)) !== false) {
                if (!$isInsert && preg_match($regex, $line, $matches)) {
                    $isInsert = true;
                    $currentTable = $matches[1];
                    $currentQuery = $line;
                } elseif ($isInsert) {
                    $currentQuery .= $line;
                }
                
                if ($isInsert && trim($line) !== '' && substr(trim($line), -1) === ';') {
                    $this->info("Executing insert for table: {$currentTable}");
                    DB::unprepared($currentQuery);
                    $isInsert = false;
                    $currentQuery = '';
                    $currentTable = '';
                }
            }
            
            DB::commit();
            $this->info("Data imported successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error during import on table {$currentTable}: " . $e->getMessage());
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            fclose($handle);
            return 1;
        }

        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("Verifying row counts:");
        $this->table(
            ['Table', 'Rows'],
            collect($tables)->map(fn($table) => [$table, DB::table($table)->count()])->toArray()
        );

        return 0;
    }
}
