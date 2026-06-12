<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BackupService
{
    /**
     * Generate SQL dump dari database MySQL menggunakan query langsung.
     * Tidak memerlukan mysqldump binary - pure PHP approach.
     * Stream-safe untuk file besar.
     */
    public function generateSqlDump(): \Generator
    {
        $dbName = config('database.connections.mysql.database');
        $now = now()->format('Y-m-d H:i:s');

        yield "-- ============================================================\n";
        yield "-- Hibiscus Efsya POS - Database Backup\n";
        yield "-- Database  : {$dbName}\n";
        yield "-- Generated : {$now}\n";
        yield "-- ============================================================\n\n";
        yield "SET FOREIGN_KEY_CHECKS=0;\n";
        yield "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        yield "SET NAMES utf8mb4;\n\n";

        $tables = $this->getTables();

        foreach ($tables as $table) {
            yield from $this->dumpTable($table);
        }

        yield "SET FOREIGN_KEY_CHECKS=1;\n";
        yield "-- EOF\n";
    }

    private function getTables(): array
    {
        $rows = DB::select('SHOW TABLES');
        $tables = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $tables[] = array_values($values)[0];
        }

        return $tables;
    }

    private function dumpTable(string $table): \Generator
    {
        yield "-- -----------------------------------------------------------\n";
        yield "-- Table: `{$table}`\n";
        yield "-- -----------------------------------------------------------\n\n";

        // DROP + CREATE structure
        $createResult = DB::select("SHOW CREATE TABLE `{$table}`");
        $createSql = $createResult[0]->{'Create Table'};
        yield "DROP TABLE IF EXISTS `{$table}`;\n";
        yield $createSql . ";\n\n";

        // Dump data in chunks to avoid memory issues
        $chunkSize = 500;
        $offset = 0;

        while (true) {
            $rows = DB::table($table)->offset($offset)->limit($chunkSize)->get();

            if ($rows->isEmpty()) {
                break;
            }

            $columns = array_keys((array) $rows->first());
            $columnList = implode('`, `', $columns);

            yield "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n";

            $rowCount = count($rows);
            foreach ($rows as $index => $row) {
                $values = array_map(function ($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return "'" . addslashes((string) $value) . "'";
                }, (array) $row);

                $valueList = implode(', ', $values);
                $isLast = ($index === $rowCount - 1);
                yield "({$valueList})" . ($isLast ? ";\n" : ",\n");
            }

            yield "\n";
            $offset += $chunkSize;
        }
    }

    /**
     * Kembalikan nama file backup yang di-generate.
     */
    public function getBackupFilename(): string
    {
        $dbName = config('database.connections.mysql.database');
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "backup_{$dbName}_{$timestamp}.sql";
    }
}
