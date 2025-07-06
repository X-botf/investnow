<?php

namespace App\Traits;

use App\Enums\TxnStatus;
use App\Models\RewardPointEarning;
use App\Models\Transaction;
use App\Models\User;

trait RewardTrait
{
    use NotifyTrait;

    public function rewardToUser($user_id, $trasaction_id)
    {
        $user = User::find($user_id);
        $userRanking = RewardPointEarning::where('ranking_id', $user->ranking_id)->first();

        if (! $user || ! $userRanking) {
            return false;
        }

        $totalTransactions = Transaction::where('user_id', $user_id)->where('status', TxnStatus::Success)->sum('final_amount');

        if ($totalTransactions >= $userRanking->amount_of_transactions) {

            Transaction::find($trasaction_id)->update([
                'points' => $userRanking->point,
            ]);

            $user->increment('points', $userRanking->point);

            $shortcodes = [
                '[[points]]' => $userRanking->point,
            ];

            $this->pushNotify('get_rewards', $shortcodes, route('user.rewards.index'), $user->id);
        }

    }
}
