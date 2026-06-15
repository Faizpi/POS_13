<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class PenerimaanBarangTemplateExport implements FromView, WithTitle, ShouldAutoSize
{
    public function view(): View
    {
        return view('reports.template-penerimaan-barang');
    }

    public function title(): string
    {
        return 'Template Penerimaan Barang';
    }
}
