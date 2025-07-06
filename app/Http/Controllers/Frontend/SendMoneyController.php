<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Session;
use Txn;
use Validator;

class SendMoneyController extends Controller
{
    public function sendMoney()
    {

        if (! setting('transfer_status', 'permission') or ! \Auth::user()->transfer_status) {
            abort('403', 'Send Money Disable Now');
        }

        $isStepOne = 'current';
        $isStepTwo = '';

        return view('frontend::send_money.now', compact('isStepOne', 'isStepTwo'));
    }

    public function sendMoneyNow(Request $request)
    {
        if (! setting('transfer_status', 'permission') || ! \Auth::user()->transfer_status) {
            abort('403', 'Send Money Disable Now');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'amount' => ['required', 'regex:/^[0-9]+(\.[0-9][0-9]?)?$/'],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }
        //daily limit
        $todayTransaction = Transaction::where('user_id', \Auth::id())->where('type', TxnType::SendMoney)->whereDate('created_at', Carbon::today())->count();
        $dayLimit = (float) Setting('send_money_day_limit', 'fee');
        if ($todayTransaction >= $dayLimit) {
            notify()->error(__('Today Send Money limit has been reached'), 'Error');

            return redirect()->back();
        }

        $input = $request->all();

        $fromUser = \Auth::user();
        $toUser = User::where('email', $input['email'])->first();

        if (! $toUser) {
            notify()->error(__('User Not Found'), 'Error');

            return redirect()->back();
        }

        $amount = (float) $input['amount'];

        $min = setting('min_send', 'fee');
        $max = setting('max_send', 'fee');
        if ($amount < $min || $amount > $max) {
            $currencySymbol = setting('currency_symbol', 'global');
            $message = 'Please Send the Amount within the range '.$currencySymbol.$min.' to '.$currencySymbol.$max;
            notify()->error($message, 'Error');

            return redirect()->back();
        }

        $chargeType = Setting('send_charge_type', 'fee');

        $charge = (float) Setting('send_charge', 'fee');

        if ($chargeType == 'percentage') {
            $charge = $amount * ($charge / 100);
        }

        $totalAmount = $amount + $charge;

        if ($fromUser->balance < $amount) {
            notify()->error(__('Insufficient Balance Your Main Wallet'), 'Error');

            return redirect()->back();
        }

        $fromUser->decrement('balance', $totalAmount);
        $sendDescription = 'Transfer Money To '.$toUser->username;
        $txnInfo = Txn::new($amount, $charge, $totalAmount, 'system', $sendDescription,
            TxnType::SendMoney, TxnStatus::Success, null, null, $fromUser->id, $toUser->id);

        $toUser->increment('balance', $amount);
        $receiveDescription = 'Transfer Money Form '.$fromUser->username;
        $txnInfo = Txn::new($amount, $charge, $totalAmount, 'system', $receiveDescription,
            TxnType::ReceiveMoney, TxnStatus::Success, null, null, $toUser->id, $fromUser->id, 'User', [], $input['note']);

        notify()->success('Successfully Send Money', 'success');

        $symbol = setting('currency_symbol', 'global');

        $notify = [
            'card-header' => 'Success Your Send Money Process',
            'title' => $symbol.$txnInfo->amount.' Send Money Successfully',
            'p' => 'The Send Money has been successfully sent to the'.$toUser->first_name.' '.$toUser->last_name,
            'strong' => 'Transaction ID: '.$txnInfo->tnx,
            'action' => route('user.send-money.view'),
            'a' => 'Send Money again',
            'view_name' => 'send_money',
        ];
        Session::put('user_notify', $notify);

        return redirect()->route('user.notify');

    }

    public function sendMoneyLog(Request $request)
    {

        $sendMoneys = Transaction::search(request('query'), function ($query) {
            $query->where('user_id', auth()->user()->id)
                ->where('type', TxnType::SendMoney)
                ->when(request('date'), function ($query) {
                    $query->whereDay('created_at', '=', Carbon::parse(request('date'))->format('d'));
                });
        })->where('user_id', auth()->user()->id)->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('frontend::send_money.log', compact('sendMoneys'));
    }
}
