<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix duplicate nomor values in biayas table before unique index is applied.
     * Appends -2, -3, etc. to duplicate values to make them unique.
     */
    public function up(): void
    {
        // Get all duplicate nomor values
        $duplicates = DB::table('biayas')
            ->select('nomor', DB::raw('COUNT(*) as count'))
            ->whereNotNull('nomor')
            ->groupBy('nomor')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('nomor');

        foreach ($duplicates as $nomor) {
            // Get all IDs with this nomor, ordered by ID
            $records = DB::table('biayas')
                ->where('nomor', $nomor)
                ->orderBy('id')
                ->get();

            // Keep the first one, update the rest
            $first = true;
            $counter = 2;
            foreach ($records as $record) {
                if ($first) {
                    $first = false;
                    continue;
                }

                DB::table('biayas')
                    ->where('id', $record->id)
                    ->update(['nomor' => $nomor . '-' . $counter]);

                $counter++;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely reverse this as we don't know which records were modified
    }
};
