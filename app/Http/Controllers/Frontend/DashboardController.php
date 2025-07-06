<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {

        $user = auth()->user();
        $transactions = Transaction::where('user_id', $user->id);

        $recentTransactions = $transactions->latest()->take(5)->get();

        $referral = $user->getReferrals()->first();

        $dataCount = [
            'total_transaction' => $transactions->count(),
            'total_deposit' => $user->totalDeposit(),
            'total_investment' => $user->totalInvestment(),
            'total_profit' => $user->totalProfit(),
            'profit_last_7_days' => $user->totalProfit(7),
            'total_withdraw' => $user->totalWithdraw(),
            'total_transfer' => $user->totalTransfer(),
            'total_referral_profit' => $user->totalReferralProfit(),
            'total_referral' => $referral->relationships()->count(),

            'deposit_bonus' => $user->totalDepositBonus(),
            'investment_bonus' => $user->totalInvestBonus(),
            'rank_achieved' => $user->rankAchieved(),
            'total_ticket' => $user->ticket->count(),
        ];

        $referral = $user->getReferrals()->first();

        return view('frontend::user.dashboard', compact('dataCount', 'recentTransactions', 'referral'));
    }
}
