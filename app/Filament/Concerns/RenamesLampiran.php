<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait RenamesLampiran
{
    /**
     * Rename lampiran files to legacy convention: {nomor}-{counter}.{ext}
     * Called from afterCreate / afterSave.
     */
    protected function renameLampiranFiles(): void
    {
        $record = $this->getRecord();

        if (empty($record->lampiran_paths)) {
            return;
        }

        $paths    = $record->lampiran_paths;
        $nomor    = $record->nomor ?? ('ID-' . $record->id);
        $dir      = explode('/', $paths[0] ?? '')[0] ?? 'lampiran';
        $disk     = Storage::disk('local');
        $rootPath = $disk->path('');

        $hasChanges = false;
        $counter    = 1;

        // Cari counter tertinggi dari file yang sudah benar formatnya
        foreach ($paths as $path) {
            $fn = basename($path);
            if (preg_match('/^' . preg_quote($nomor, '/') . '-(\d+)\./', $fn, $m)) {
                $counter = max($counter, (int) $m[1] + 1);
            }
        }

        foreach ($paths as $i => $path) {
            $fn = basename($path);

            // Skip file yang sudah sesuai format: {nomor}-{counter}.{ext}
            if (preg_match('/^' . preg_quote($nomor, '/') . '-\d+\.\w+$/', $fn)) {
                continue;
            }

            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $newFn = "{$nomor}-{$counter}.{$ext}";
            $newPath = "{$dir}/{$newFn}";

            // Use direct filesystem to avoid disk misconfiguration
            $absOld = $rootPath . $path;
            $absNew = $rootPath . $newPath;

            if (File::exists($absOld) && !File::exists($absNew)) {
                File::move($absOld, $absNew);
                $paths[$i] = $newPath;
                $hasChanges = true;
                $counter++;
            }
        }

        if ($hasChanges) {
            $record->updateQuietly(['lampiran_paths' => array_values($paths)]);
        }
    }
}
