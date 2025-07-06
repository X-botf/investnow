<?php

namespace App\Traits;

use App\Enums\InvestStatus;
use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Models\DepositMethod;
use App\Models\Invest;
use App\Models\LevelReferral;
use App\Models\Transaction;
use Payment\AuthorizeDotNet\AuthorizeDotNetTxn;
use Payment\Binance\BinanceTxn;
use Payment\Blockchain\BlockchainTxn;
use Payment\BlockIo\BlockIoTxn;
use Payment\Btcpayserver\BtcpayserverTxn;
use Payment\Cashmaal\CashmaalTxn;
use Payment\Coinbase\CoinbaseTxn;
use Payment\Coinbase\PaystackTxn;
use Payment\Coingate\CoingateTxn;
use Payment\Coinpayments\CoinpaymentsTxn;
use Payment\Coinremitter\CoinremitterTxn;
use Payment\Cryptomus\CryptomusTxn;
use Payment\Flutterwave\FlutterwaveTxn;
use Payment\Instamojo\InstamojoTxn;
use Payment\Midtrans\MidtransTxn;
use Payment\Mollie\MollieTxn;
use Payment\Monnify\MonnifyTxn;
use Payment\Nowpayments\NowpaymentsTxn;
use Payment\Paymongo\PaymongoTxn;
use Payment\Paypal\PaypalTxn;
use Payment\Paytm\PaytmTxn;
use Payment\Perfectmoney\PerfectmoneyTxn;
use Payment\Razorpay\RazorpayTxn;
use Payment\Securionpay\SecurionpayTxn;
use Payment\Stripe\StripeTxn;
use Payment\Twocheckout\TwocheckoutTxn;
use Session;
use Txn;
use URL;

trait Payment
{
    //automatic deposit gateway snippet
    protected function depositAutoGateway($gateway, $txnInfo)
    {
        $txn = $txnInfo->tnx;
        Session::put('deposit_tnx', $txn);
        $gateway = DepositMethod::code($gateway)->first()->gateway->gateway_code ?? 'none';

        $gatewayTxn = self::gatewayMap($gateway, $txnInfo);
        if ($gatewayTxn) {
            return $gatewayTxn->deposit();
        }

        return self::paymentNotify($txn, 'pending');

    }

    //automatic withdraw gateway snippet

    private function gatewayMap($gateway, $txnInfo)
    {
        // dd($txnInfo);
        $gatewayMap = [
            'paypal' => PaypalTxn::class,
            'stripe' => StripeTxn::class,
            'mollie' => MollieTxn::class,
            'perfectmoney' => PerfectmoneyTxn::class,
            'coinbase' => CoinbaseTxn::class,
            'paystack' => PaystackTxn::class,
            'voguepay' => BinanceTxn::class,
            'flutterwave' => FlutterwaveTxn::class,
            'cryptomus' => CryptomusTxn::class,
            'nowpayments' => NowpaymentsTxn::class,
            'securionpay' => SecurionpayTxn::class,
            'coingate' => CoingateTxn::class,
            'monnify' => MonnifyTxn::class,
            'coinpayments' => CoinpaymentsTxn::class,
            'paymongo' => PaymongoTxn::class,
            'coinremitter' => CoinremitterTxn::class,
            'btcpayserver' => BtcpayserverTxn::class,
            'binance' => BinanceTxn::class,
            'cashmaal' => CashmaalTxn::class,
            'blockio' => BlockIoTxn::class,
            'blockchain' => BlockchainTxn::class,
            'instamojo' => InstamojoTxn::class,
            'paytm' => PaytmTxn::class,
            'razorpay' => RazorpayTxn::class,
            'twocheckout' => TwocheckoutTxn::class,
            'authorize.net' => AuthorizeDotNetTxn::class,
            'midtrans' => MidtransTxn::class,
        ];

        if (array_key_exists($gateway, $gatewayMap)) {
            return app($gatewayMap[$gateway], ['txnInfo' => $txnInfo]);
        }

        return false;

    }

    //automatic payment notify snippet

    protected function paymentNotify($tnx, $status)
    {

        $tnxInfo = Transaction::tnx($tnx);

        $title = '';
        $investNotifyTitle = '';
        switch ($status) {
            case 'success':
                $title = 'Successfully';
                $investNotifyTitle = 'Successfully Investment';
                break;
            case 'pending':
                $title = 'Pending';
                $investNotifyTitle = 'Successfully Investment Apply';
                break;
        }

        $status = ucfirst($status);
        if ($tnxInfo->type == TxnType::Investment) {

            $shortcodes = [
                '[[full_name]]' => $tnxInfo->user->full_name,
                '[[txn]]' => $tnxInfo->tnx,
                '[[plan_name]]' => $tnxInfo->invest->schema->name,
                '[[invest_amount]]' => $tnxInfo->amount.setting('site_currency', 'global'),
                '[[site_title]]' => setting('site_title', 'global'),
                '[[site_url]]' => route('home'),
            ];

            $this->mailNotify($tnxInfo->user->email, 'user_investment', $shortcodes);
            $this->pushNotify('user_investment', $shortcodes, route('user.invest-logs'), $tnxInfo->user->id);
            $this->smsNotify('user_investment', $shortcodes, $tnxInfo->user->phone);

            notify()->success($investNotifyTitle, $status);

            return redirect()->route('user.invest-logs');
        }

        $symbol = setting('currency_symbol', 'global');

        $notify = [
            'card-header' => "$status Your Deposit Process",
            'title' => "$symbol $tnxInfo->amount Deposit $title",
            'p' => "The amount has been $title added into your account",
            'strong' => 'Transaction ID: '.$tnx,
            'action' => route('user.deposit.amount'),
            'a' => 'Deposit again',
        ];

        if ($status == 'Pending') {
            $shortcodes = [
                '[[full_name]]' => $tnxInfo->user->full_name,
                '[[txn]]' => $tnxInfo->tnx,
                '[[gateway_name]]' => $tnxInfo->method,
                '[[deposit_amount]]' => $tnxInfo->amount,
                '[[site_title]]' => setting('site_title', 'global'),
                '[[site_url]]' => route('home'),
                '[[message]]' => '',
                '[[status]]' => $status,
            ];
            $this->mailNotify(setting('site_email', 'global'), 'manual_deposit_request', $shortcodes);
            $this->pushNotify('manual_deposit_request', $shortcodes, route('admin.deposit.manual.pending'), $tnxInfo->user->id);
            $this->smsNotify('manual_deposit_request', $shortcodes, $tnxInfo->user->phone);
        }

        $isStepOne = 'current';
        $isStepTwo = 'current';

        return view('frontend::deposit.success', compact('isStepOne', 'isStepTwo', 'notify'));
    }

    //automatic payment success snippet

    protected function withdrawAutoGateway($gatewayCode, $txnInfo)
    {

        $gatewayTxn = self::gatewayMap($gatewayCode, $txnInfo);
        if ($gatewayTxn && config('app.demo') == 0) {
            $gatewayTxn->withdraw();
        }

        $symbol = setting('currency_symbol', 'global');
        $notify = [
            'card-header' => 'Withdraw Money',
            'title' => $symbol.$txnInfo->amount.' Withdraw Request Successful',
            'p' => 'The Withdraw Request has been successfully sent',
            'strong' => 'Transaction ID: '.$txnInfo->tnx,
            'action' => route('user.withdraw.view'),
            'a' => 'WITHDRAW REQUEST AGAIN',
            'view_name' => 'withdraw',
        ];
        Session::put('user_notify', $notify);

        return redirect()->route('user.notify');

    }

    //automatic gateway map snippet

    protected function paymentSuccess($ref, $isRedirect = true)
    {
        $txnInfo = Transaction::tnx($ref);

        if ($txnInfo->status == TxnStatus::Success) {
            return false;
        }

        if ($txnInfo->type == TxnType::Investment) {

            $investmentInfo = Invest::where('transaction_id', $txnInfo->id)->first();
            $investmentInfo->update([
                'status' => InvestStatus::Ongoing,
                'created_at' => now(),
            ]);

            $txnInfo->update([
                'status' => TxnStatus::Success,
            ]);

            if (setting('site_referral', 'global') == 'level' && setting('investment_level')) {
                $level = LevelReferral::where('type', 'investment')->max('the_order') + 1;
                creditReferralBonus($txnInfo->user, 'investment', $txnInfo->amount, $level);
            }

            if ($isRedirect) {
                notify()->success('Successfully Investment', 'success');

                return redirect()->route('user.invest-logs');
            }

        } else {

            $txnInfo->update([
                'status' => TxnStatus::Success,
            ]);
            Txn::update($ref, TxnStatus::Success, $txnInfo->user_id);

            if (setting('site_referral', 'global') == 'level' && setting('deposit_level')) {
                $level = LevelReferral::where('type', 'deposit')->max('the_order') + 1;
                creditReferralBonus($txnInfo->user, 'deposit', $txnInfo->amount, $level);
            }

            if ($isRedirect) {
                return redirect(URL::temporarySignedRoute(
                    'status.success', now()->addMinutes(2)
                ));
            }

        }
    }
}
