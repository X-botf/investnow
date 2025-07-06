<?php

namespace App\Http\Controllers\Backend;

use App\Exports\TransactionExport;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct()
    {
        $this->middleware('permission:transaction-list');
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws \Exception
     */
    public function transactions(Request $request, $id = null)
    {
        // dd($request->all());
        $search = $request->query('query') ?? null;
        $date_range = $request->input('daterange') ?? null;
        $export = $request->input('export') ?? null;

        $transactionType = $request->query('filter_by_transaction_type') ?? null;
        $status = $request->query('status') ?? null;

        $data = Transaction::query()
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->when($transactionType, function ($query, $transactionType) {
                $query->where('type', $transactionType);
            })
            ->when($date_range, function ($query, $date_range) {
                $start_date = Carbon::createFromFormat('d/m/Y', str_replace(' ', '', explode('-', $date_range)[0]))->toDateString().' 00:00:00';
                $end_date = Carbon::createFromFormat('d/m/Y', str_replace(' ', '', explode('-', $date_range)[1]))->toDateString().' 23:59:59';
                $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            });

        if ($export == 'true') {
            $export_data = $data->get();
            $name = 'transaction';
            $file_name = $name.'_'.date('d-M-Y').'.csv';

            return Excel::download(new TransactionExport($export_data), $file_name);
        }
        $data = $data->latest()
            ->paginate(10);
        $title = __('All Transactions');

        return view('backend.transaction.index', compact('data', 'title'));

    }
}
