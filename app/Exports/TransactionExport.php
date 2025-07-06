<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TransactionExport implements FromView, ShouldAutoSize
{
    public $export_data;

    public function __construct($export_data)
    {
        $this->export_data = $export_data;
    }

    public function view(): View
    {
        return view('backend.transaction.export', [
            'data' => $this->export_data,
        ]);
    }
}
