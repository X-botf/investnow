<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Invest;
use App\Models\Schema;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:investment-list');

    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function investments(Request $request, $id = null)
    {
        $search = $request->query('query') ?? null;
        $schemaId = $request->query('filter_by_schema') ?? null;
        $status = $request->query('status') ?? null;

        $schemas = Schema::all();
        $data = Invest::with('schema')
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->when($schemaId, function ($query, $schemaId) {
                $query->where('schema_id', $schemaId);
            })
            ->when(! is_null($status), function ($query) use ($status) {
                if ($status == '1') {
                    $query->where('capital_back', 1);
                } elseif ($status == '0') {
                    $query->where('capital_back', 0);
                }
            })
            ->latest()
            ->paginate(10);

        $title = 'All Investments';

        return view('backend.investment.index', compact('data', 'schemas', 'title'));
    }
}
