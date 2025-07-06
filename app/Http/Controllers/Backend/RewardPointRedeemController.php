<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ranking;
use App\Models\RewardPointRedeem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RewardPointRedeemController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reward-redeem-list', ['only' => ['index']]);
        $this->middleware('permission:reward-redeem-create', ['only' => ['store']]);
        $this->middleware('permission:reward-redeem-edit', ['only' => ['update']]);
        $this->middleware('permission:reward-redeem-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $redeems = RewardPointRedeem::with('ranking')->latest()->paginate();
        $rankings = Ranking::all();

        return view('backend.reward-point.redeem.index', compact('redeems', 'rankings'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ranking_id' => 'required|unique:reward_point_redeems,ranking_id',
            'amount' => 'required|regex:/^\d+(\.\d{1,2})?$/|unique:reward_point_redeems,amount',
            'point' => 'required|regex:/^\d+(\.\d{1,2})?$/',
        ], [
            'ranking_id.unique' => __('Ranking redeem already exists.'),
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        RewardPointRedeem::create([
            'ranking_id' => $request->get('ranking_id'),
            'amount' => $request->get('amount'),
            'point' => $request->get('point'),
        ]);

        notify()->success(__('Redeem added successfully!'), 'Success');

        return redirect()->route('admin.reward.point.redeem.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'ranking_id' => 'required|unique:reward_point_redeems,ranking_id,'.$id,
            'amount' => 'required|regex:/^\d+(\.\d{1,2})?$/|unique:reward_point_redeems,amount,'.$id,
            'point' => 'required|regex:/^\d+(\.\d{1,2})?$/',
        ], [
            'ranking_id.unique' => __('Ranking redeem already exists.'),
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        RewardPointRedeem::findOrFail($id)->update([
            'ranking_id' => $request->get('ranking_id'),
            'amount' => $request->get('amount'),
            'point' => $request->get('point'),
        ]);

        notify()->success(__('Redeem updated successfully!'), 'Success');

        return redirect()->route('admin.reward.point.redeem.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id)
    {
        RewardPointRedeem::destroy($id);

        notify()->success(__('Redeem deleted successfully!'), 'Success');

        return redirect()->route('admin.reward.point.redeem.index');
    }
}
