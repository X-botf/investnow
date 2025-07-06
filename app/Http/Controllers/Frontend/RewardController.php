<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Http\Controllers\Controller;
use App\Models\RewardPointEarning;
use App\Models\RewardPointRedeem;
use App\Models\Transaction;
use Txn;

class RewardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $redeems = RewardPointRedeem::with('ranking')->get();
        $earnings = RewardPointEarning::with('ranking')->get();

        $myRanking = RewardPointRedeem::where('ranking_id', auth()->user()->ranking_id)->first();

        $transactions = Transaction::where('user_id', auth()->id())
            ->latest()
            ->where('type', TxnType::RewardRedeem)
            ->paginate(5);

        return view('frontend::rewards.index', compact('redeems', 'earnings', 'myRanking', 'transactions'));
    }

    public function redeemNow()
    {
        $user = auth()->user();

        $ranking = RewardPointRedeem::where('ranking_id', $user->ranking_id)->first();

        // Check ranking exists or not and user's point equal or less than 0 then redirect back
        if (! $ranking || $user->points <= 0) {
            return back();
        }

        $redeemAmount = ($ranking->amount / $ranking->point) * $user->points;

        // Create transaction
        Txn::new($redeemAmount, 0, $redeemAmount, 'System', $user->points.' Points Reward Redeem', TxnType::RewardRedeem, TxnStatus::Success, '', null, $user->id, null, 'User');

        $user->points = 0;
        $user->save();

        $user->increment('balance', $redeemAmount);

        notify()->success(__('Rewards redeem successfully!'));

        return back();
    }
}
