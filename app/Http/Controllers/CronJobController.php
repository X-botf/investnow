<?php

namespace App\Http\Controllers;

use App\Enums\InvestStatus;
use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Models\Invest;
use App\Models\LevelReferral;
use App\Models\Ranking;
use App\Models\Referral;
use App\Models\ReferralLink;
use App\Models\ReferralRelationship;
use App\Models\Schema;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\NotifyTrait;
use Artisan;
use Carbon\Carbon;
use Remotelywork\Installer\Repository\App;
use Txn;

class CronJobController extends Controller
{
    use NotifyTrait;

    /**
     * @return string
     */
    public function investmentCronJob()
    {
        $ongoingInvestment = Invest::has('user')->with('user')->where('status', InvestStatus::Ongoing)->where('next_profit_time', '<=', Carbon::now()->format('Y-m-d H:i:s'))->cursor();

        foreach ($ongoingInvestment as $invest) {
            $schema = Schema::find($invest->schema_id);

            $profitOffDays = json_decode($schema->off_days, true) ?? [];

            $date = Carbon::now();

            $todayDate = $date->format('Y-m-d');

            if ($profitOffDays == null || ! in_array($todayDate, $profitOffDays)) {
                $user = $invest->user;
                $calculateInterest = ($invest->interest * $invest->invest_amount) / 100;
                $interest = $invest->interest_type != 'percentage' ? $invest->interest : $calculateInterest;

                $nextProfitTime = Carbon::now()->addHours($invest->period_hours);

                $updateData = [
                    'next_profit_time' => $nextProfitTime,
                    'last_profit_time' => Carbon::now(),
                    'number_of_period' => ($invest->number_of_period - 1),
                    'already_return_profit' => ($invest->already_return_profit + 1),
                    'total_profit_amount' => ($invest->total_profit_amount + $interest),
                ];

                $shortcodes = [
                    '[[full_name]]' => $user->full_name,
                    '[[plan_name]]' => $schema->name,
                    '[[invest_amount]]' => $invest->invest_amount,
                    '[[roi]]' => $interest,
                    '[[site_title]]' => setting('site_title', 'global'),
                    '[[site_url]]' => route('home'),
                ];

                if ($invest->return_type == 'lifetime') {
                    $invest->update($updateData);

                    $user->increment('profit_balance', $interest);
                    $tnxInfo = Txn::new($interest, 0, $interest, 'system', $schema->name.' Plan Interest', TxnType::Interest, TxnStatus::Success, null, null, $user->id);

                    if (setting('site_referral', 'global') == 'level' && setting('profit_level')) {
                        $level = LevelReferral::where('type', 'profit')->max('the_order') + 1;
                        creditReferralBonus($tnxInfo->user, 'profit', $interest, $level);
                    }

                    //Notify newsletter
                    $this->mailNotify($tnxInfo->user->email, 'invest_roi', array_merge($shortcodes, ['[[txn]]' => $tnxInfo->tnx]));
                    $this->smsNotify('invest_roi', array_merge($shortcodes, ['[[txn]]' => $tnxInfo->tnx]), $tnxInfo->user->phone);
                    $this->pushNotify('invest_roi', $shortcodes, route('user.transactions'), $tnxInfo->user->id);
                } else {

                    if ($invest->number_of_period > 0) {
                        if ($invest->number_of_period == 1) {
                            $updateData = array_merge($updateData, [
                                'status' => InvestStatus::Completed,
                            ]);

                            if ($invest->capital_back == 1) {
                                $user->increment('balance', $invest->invest_amount);
                                $tnxInfo = Txn::new($invest->invest_amount, 0, $invest->invest_amount, 'system', $schema->name.' Capital Back', TxnType::Refund, TxnStatus::Success, null, null, $user->id);

                                //notify newsletter
                                $this->mailNotify($tnxInfo->user->email, 'investment_end', array_merge($shortcodes, ['[[roi]]' => '', '[[txn]]' => $tnxInfo->tnx]));
                                $this->smsNotify('investment_end', array_merge($shortcodes, ['[[roi]]' => '', '[[txn]]' => $tnxInfo->tnx]), $tnxInfo->user->phone);
                                $this->pushNotify('investment_end', array_merge($shortcodes, ['[[roi]]' => '', '[[txn]]' => $tnxInfo->tnx]), route('user.transactions'), $tnxInfo->user->id);
                            }
                        }

                        $invest->update($updateData);

                        $user->increment('profit_balance', $interest);
                        $tnxInfo = Txn::new($interest, 0, $interest, 'system', $schema->name.' Plan Interest', TxnType::Interest, TxnStatus::Success, null, null, $user->id);

                        if (setting('site_referral', 'global') == 'level' && setting('profit_level')) {
                            $level = LevelReferral::where('type', 'profit')->max('the_order') + 1;
                            creditReferralBonus($tnxInfo->user, 'profit', $interest, $level);
                        }

                        //Notify newsletter
                        $this->mailNotify($tnxInfo->user->email, 'invest_roi', array_merge($shortcodes, ['[[txn]]' => $tnxInfo->tnx]));
                        $this->smsNotify('invest_roi', array_merge($shortcodes, ['[[txn]]' => $tnxInfo->tnx]), $tnxInfo->user->phone);
                        $this->pushNotify('invest_roi', array_merge($shortcodes, ['[[txn]]' => $tnxInfo->tnx]), route('user.transactions'), $tnxInfo->user->id);
                    }
                }
            }
        }

        return '....cron job successfully completed';
    }

    /**
     * @return string
     */
    public function referralCronJob()
    {
        if (setting('site_referral', 'global') == 'level') {
            return '....';
        }
        $referrals = Referral::all();
        $referralRelationship = ReferralRelationship::all();

        foreach ($referralRelationship as $relationship) {
            $provider = ReferralLink::find($relationship->referral_link_id)->user;

            $user = User::find($relationship->user_id);

            $totalDeposit = $user->totalDeposit();
            $totalInvest = $user->totalInvestment();

            $filterReferrals = $referrals->reject(function ($referral) use ($provider, $user) {

                return Transaction::where(function ($query) use ($user, $provider, $referral) {
                    $query->where('target_id', '!=', null)
                        ->where('user_id', $provider->id)
                        ->where('from_user_id', $user->id)
                        ->where('target_id', $referral->referral_target_id)
                        ->where('target_type', $referral->type)
                        ->where('is_level', 0);
                })->exists();
            })->map(function ($referral) {
                return $referral;
            });

            foreach ($filterReferrals as $referral) {

                $referralBonus = ($referral->bounty * $referral->target_amount) / 100;

                $targetName = $referral->target->name;

                if ($referral->type == 'deposit' && $referral->target_amount <= $totalDeposit && setting('deposit_referral_bounty', 'permission')) {
                    Txn::new($referralBonus, 0, $referralBonus, 'system', 'Referral Bonus with '.$targetName.' Via '.$user->full_name, TxnType::Referral, TxnStatus::Success, null, null, $provider->id, $user->id, 'User', [], 'none', $referral->referral_target_id, $referral->type);
                    $provider->increment('profit_balance', $referralBonus);
                }

                if ($referral->type == 'investment' && $referral->target_amount <= $totalInvest && setting('investment_referral_bounty', 'permission')) {
                    Txn::new($referralBonus, 0, $referralBonus, 'system', 'Referral Bonus with '.$targetName.' Via '.$user->full_name, TxnType::Referral, TxnStatus::Success, null, null, $provider->id, $user->id, 'User', [], 'none', $referral->referral_target_id, $referral->type);
                    $provider->increment('profit_balance', $referralBonus);
                }
            }
        }

        return '....referral job successfully completed';
    }

    /**
     * @return string
     */
    public function userRanking()
    {

        $rankings = Ranking::where('status', '=', true)->get();

        foreach (User::where('status', true)->get() as $user) {
            $eligibleRanks = $rankings->reject(function ($rank) use ($user) {
                $totalEarning = $user->totalProfit();
                $totalDeposit = $user->totalDeposit();
                $totalInvest = $user->totalInvestment();
                $minimumReferral = $user->referrals->count();
                $minimumReferralDeposit = $user->referrals->sum('total_deposit');
                $minimumReferralInvest = $user->referrals->sum('total_invest');

                return in_array($rank->id, json_decode($user->rankings)) ||
                    $rank->minimum_earnings > $totalEarning ||
                    $rank->minimum_deposit > $totalDeposit ||
                    $rank->minimum_invest > $totalInvest ||
                    $rank->minimum_referral > $minimumReferral ||
                    $rank->minimum_referral_deposit > $minimumReferralDeposit ||
                    $rank->minimum_referral_invest > $minimumReferralInvest;
            });

            if ($eligibleRanks->isNotEmpty()) {
                $maxRank = $eligibleRanks->max('minimum_earnings');
                $highestRank = $eligibleRanks->where('minimum_earnings', $maxRank)->first();

                foreach ($eligibleRanks as $rank) {
                    Txn::new($rank->bonus, 0, $rank->bonus, 'system', 'Referral Bonus by '.$rank->ranking, TxnType::Bonus, TxnStatus::Success, null, null, $user->id);
                    $user->profit_balance += $rank->bonus;

                    if ($rank->id === $highestRank->id) {
                        $user->update([
                            'ranking_id' => $rank->id,
                            'rankings' => json_encode(array_merge(json_decode($user->rankings), [$rank->id])),
                        ]);
                    }
                }
            }
        }

        return '....referral job successfully completed';
    }

    public function queueWork()
    {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
        ]);

        return '.... Running successfully';
    }

    public function UnconfirmedUserDelete()
    {
        $this->startCron();

        $settingDays = Setting::where('name', 'user_delete')->value('val');

        if (! is_numeric($settingDays) || $settingDays < 0) {
            return 'Invalid setting for user deletion days. Job aborted.';
        }
        User::whereNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays($settingDays))
            ->delete();

        return 'Unconfirmed User Delete job successfully completed';
    }

    protected function startCron()
    {
        if (! App::initApp()) {
            return false;
        }
    }
}
