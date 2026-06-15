<?php

namespace App\Imports;

use App\Models\Produk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StockOpnameItemImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected int $gudangId;
    protected array $errors = [];
    protected Collection $rows;

    public function __construct(int $gudangId)
    {
        $this->gudangId = $gudangId;
        $this->rows = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->map(function ($row) {
            $namaProduk = trim($row['nama_produk'] ?? '');
            $produk = Produk::where('nama_produk', $namaProduk)->first();

            if (!$produk && $namaProduk) {
                $this->errors[] = "Produk '{$namaProduk}' tidak ditemukan.";
                return null;
            }

            return [
                'produk_id' => $produk?->id,
                'produk_nama' => $namaProduk,
                'batch_number' => $row['no_batch'] ?? null,
                'expired_date' => $row['tanggal'] ?? null,
                'qty_aktual' => (float) ($row['qty_aktual'] ?? 0),
            ];
        })->filter()->values();
    }

    public function rules(): array
    {
        return [
            '*.nama_produk' => ['required', 'string'],
            '*.qty_aktual' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRows(): Collection
    {
        return $this->rows;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
