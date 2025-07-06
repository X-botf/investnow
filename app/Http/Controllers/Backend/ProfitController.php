<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TxnType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct()
    {
        $this->middleware('permission:profit-list');

    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function allProfits(Request $request, $id = null)
    {
        $search = $request->query('query') ?? null;
        $transactionType = $request->query('filter_by_transaction_type') ?? null;

        $data = Transaction::whereIn('type', [
            TxnType::Referral,
            TxnType::Interest,
            TxnType::Bonus,
            TxnType::SignupBonus,
        ])
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->when($transactionType, function ($query, $transactionType) {
                $query->where('type', $transactionType);
            })
            ->latest()
            ->paginate(10);

        $title = __('All Profits');

        return view('backend.transaction.profit', compact('data', 'title', 'search'));
    }
}
