<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ranking;
use App\Models\RewardPointEarning;
use Illuminate\Http\Request;
use Validator;

class RewardPointEarningController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reward-earning-list', ['only' => ['index']]);
        $this->middleware('permission:reward-earning-create', ['only' => ['store']]);
        $this->middleware('permission:reward-earning-edit', ['only' => ['update']]);
        $this->middleware('permission:reward-earning-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $earnings = RewardPointEarning::with('ranking')->latest()->paginate();
        $rankings = Ranking::all();

        return view('backend.reward-point.earning.index', compact('earnings', 'rankings'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ranking_id' => 'required|unique:reward_point_earnings,ranking_id',
            'amount_of_transactions' => 'required|regex:/^\d+(\.\d{1,2})?$/|unique:reward_point_earnings,amount_of_transactions',
            'point' => 'required|regex:/^\d+(\.\d{1,2})?$/',
        ], [
            'ranking_id.unique' => __('Ranking reward already exists.'),
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        RewardPointEarning::create([
            'ranking_id' => $request->get('ranking_id'),
            'amount_of_transactions' => $request->get('amount_of_transactions'),
            'point' => $request->get('point'),
        ]);

        notify()->success(__('Reward added successfully!'), 'Success');

        return redirect()->route('admin.reward.point.earnings.index');
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
            'ranking_id' => 'required|unique:reward_point_earnings,ranking_id,'.$id,
            'amount_of_transactions' => 'required|regex:/^\d+(\.\d{1,2})?$/|unique:reward_point_earnings,amount_of_transactions,'.$id,
            'point' => 'required|regex:/^\d+(\.\d{1,2})?$/',
        ], [
            'ranking_id.unique' => __('Ranking reward already exists.'),
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        RewardPointEarning::findOrFail($id)->update([
            'ranking_id' => $request->get('ranking_id'),
            'amount_of_transactions' => $request->get('amount_of_transactions'),
            'point' => $request->get('point'),
        ]);

        notify()->success(__('Reward updated successfully!'), 'Success');

        return redirect()->route('admin.reward.point.earnings.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id)
    {
        RewardPointEarning::destroy($id);

        notify()->success(__('Reward deleted successfully!'), 'Success');

        return redirect()->route('admin.reward.point.earnings.index');
    }
}
