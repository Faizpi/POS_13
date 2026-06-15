<?php

namespace App\Imports;

use App\Models\Produk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PenerimaanBarangItemImport implements ToCollection, WithHeadingRow, WithValidation
{
    public Collection $importedItems;

    public function __construct()
    {
        $this->importedItems = collect();
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $namaOrKode = $row['nama_produk'] ?? $row['kode_produk'] ?? null;
            if (!$namaOrKode) {
                continue;
            }

            // Lookup produk by nama_produk (case-insensitive) or item_code
            $produk = Produk::where('nama_produk', 'like', $namaOrKode)
                ->orWhere('item_code', 'like', $namaOrKode)
                ->first();

            if (!$produk) {
                continue;
            }

            $qtyDiterima = $row['qty_diterima'] ?? 0;
            $batchNumber = !empty($row['no_batch']) ? $row['no_batch'] : null;
            
            $expiredDate = null;
            if (!empty($row['tanggal_expired'])) {
                $rawExp = $row['tanggal_expired'];
                if (is_numeric($rawExp)) {
                    try {
                        $expiredDate = \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawExp))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $expiredDate = null;
                    }
                } else {
                    try {
                        $expiredDate = \Carbon\Carbon::parse($rawExp)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $expiredDate = null;
                    }
                }
            }

            $this->importedItems->push([
                'produk_id' => $produk->id,
                'batch_number' => $batchNumber,
                'expired_date' => $expiredDate,
                'qty_diterima' => (int) $qtyDiterima,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nama_produk' => [
                'required_without:kode_produk',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $exists = Produk::where('nama_produk', 'like', $value)
                            ->orWhere('item_code', 'like', $value)
                            ->exists();
                        if (!$exists) {
                            $fail("Produk dengan nama '{$value}' tidak ditemukan.");
                        }
                    }
                }
            ],
            'kode_produk' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $exists = Produk::where('nama_produk', 'like', $value)
                            ->orWhere('item_code', 'like', $value)
                            ->exists();
                        if (!$exists) {
                            $fail("Produk dengan kode '{$value}' tidak ditemukan.");
                        }
                    }
                }
            ],
            'qty_diterima' => ['required', 'numeric', 'min:1'],
        ];
    }
}
