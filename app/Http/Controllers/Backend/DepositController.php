<?php

namespace App\Http\Controllers\Backend;

use App\Enums\GatewayType;
use App\Enums\InvestStatus;
use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Http\Controllers\Controller;
use App\Models\DepositMethod;
use App\Models\Gateway;
use App\Models\Invest;
use App\Models\LevelReferral;
use App\Models\Transaction;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Purifier;
use Txn;

class DepositController extends Controller
{
    use ImageUpload, NotifyTrait;

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:deposit-list|deposit-action', ['only' => ['pending', 'history']]);
        $this->middleware('permission:deposit-action', ['only' => ['depositAction', 'actionNow']]);
    }

    //-------------------------------------------  Deposit method start ---------------------------------------------------------------

    public function methodList($type)
    {
        $button = [
            'name' => __('ADD NEW'),
            'icon' => 'plus',
            'route' => route('admin.deposit.method.create', $type),
        ];

        $depositMethods = DepositMethod::where('type', $type)->get();

        return view('backend.deposit.method_list', compact('depositMethods', 'button', 'type'));
    }

    public function createMethod($type)
    {
        $gateways = Gateway::where('status', true)->get();

        return view('backend.deposit.create_method', compact('type', 'gateways'));
    }

    public function methodStore(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'logo' => 'required_if:type,==,manual',
            'name' => 'required',
            'gateway_id' => 'required_if:type,==,auto',
            'method_code' => 'required_if:type,==,manual|unique:deposit_methods,gateway_code',
            'currency' => 'required|unique:deposit_methods,currency',
            'currency_symbol' => 'required',
            'charge' => 'required',
            'charge_type' => 'required',
            'rate' => 'required',
            'minimum_deposit' => 'required',
            'maximum_deposit' => 'required',
            'status' => 'required',
            'field_options' => 'required_if:type,==,manual',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        if (isset($input['gateway_id'])) {
            $gateway = Gateway::find($input['gateway_id']);
            $methodCode = $gateway->gateway_code.'-'.strtolower($input['currency']);
        }

        $data = [
            'logo' => isset($input['logo']) ? self::imageUploadTrait($input['logo']) : null,
            'name' => $input['name'],
            'type' => $input['type'],
            'gateway_id' => $input['gateway_id'] ?? null,
            'gateway_code' => $input['method_code'] ?? $methodCode,
            'currency' => $input['currency'],
            'currency_symbol' => $input['currency_symbol'],
            'charge' => $input['charge'],
            'charge_type' => $input['charge_type'],
            'rate' => $input['rate'],
            'minimum_deposit' => $input['minimum_deposit'],
            'maximum_deposit' => $input['maximum_deposit'],
            'status' => $input['status'],
            'field_options' => isset($input['field_options']) ? json_encode($input['field_options']) : null,
            'payment_details' => isset($input['payment_details']) ? Purifier::clean(htmlspecialchars_decode($input['payment_details'])) : null,
        ];

        $depositMethod = DepositMethod::create($data);
        notify()->success($depositMethod->name.' '.__(' Method Created'));

        return redirect()->route('admin.deposit.method.list', $depositMethod->type);
    }

    public function methodEdit($type)
    {
        $gateways = Gateway::where('status', true)->get();
        $method = DepositMethod::find(\request('id'));
        $supported_currencies = Gateway::find($method->gateway_id)->supported_currencies ?? [];

        return view('backend.deposit.edit_method', compact('method', 'type', 'gateways', 'supported_currencies'));
    }

    public function methodUpdate($id, Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required',
            'gateway_id' => 'required_if:type,==,auto',
            'currency' => 'required',
            'method_code' => 'unique:deposit_methods,gateway_code,'.$id,
            'currency_symbol' => 'required',
            'charge' => 'required',
            'charge_type' => 'required',
            'rate' => 'required',
            'minimum_deposit' => 'required',
            'maximum_deposit' => 'required',
            'status' => 'required',
            'field_options' => 'required_if:type,==,manual',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $depositMethod = DepositMethod::find($id);

        $user = \Auth::user();
        if ($depositMethod->type == GatewayType::Automatic) {
            if (! $user->can('automatic-gateway-manage')) {
                return redirect()->route('admin.deposit.method.list', $depositMethod->type);
            }

        } else {
            if (! $user->can('manual-gateway-manage')) {
                return redirect()->route('admin.deposit.method.list', $depositMethod->type);
            }
        }

        $data = [
            'name' => $input['name'],
            'type' => $input['type'],
            'gateway_id' => $input['gateway_id'] ?? null,
            'currency' => $input['currency'],
            'currency_symbol' => $input['currency_symbol'],
            'charge' => $input['charge'],
            'charge_type' => $input['charge_type'],
            'rate' => $input['rate'],
            'minimum_deposit' => $input['minimum_deposit'],
            'maximum_deposit' => $input['maximum_deposit'],
            'status' => $input['status'],
            'field_options' => isset($input['field_options']) ? json_encode($input['field_options']) : null,
            'payment_details' => isset($input['payment_details']) ? Purifier::clean(htmlspecialchars_decode($input['payment_details'])) : null,
        ];

        if ($request->hasFile('logo')) {
            $logo = self::imageUploadTrait($input['logo'], $depositMethod->logo);
            $data = array_merge($data, ['logo' => $logo]);
        }

        $depositMethod->update($data);
        notify()->success($depositMethod->name.' '.__(' Method Updated'));

        return redirect()->route('admin.deposit.method.list', $depositMethod->type);
    }

    //-------------------------------------------  Deposit method end ---------------------------------------------------------------

    public function pending(Request $request)
    {
        $search = $request->query('query') ?? null;

        $pendings = Transaction::where('status', 'pending')
            ->where(function ($query) {
                return $query->where('type', TxnType::ManualDeposit)
                    ->orWhere('type', TxnType::Investment);
            })
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->latest()
            ->paginate(10);

        return view('backend.deposit.manual', compact('pendings'));
    }

    public function history(Request $request)
    {
        $search = $request->query('query') ?? null;
        $status = $request->query('status') ?? null;

        $histories = Transaction::where(function ($query) {
            $query->where('type', TxnType::ManualDeposit)
                ->orWhere('type', TxnType::Deposit);
        })
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10);

        return view('backend.deposit.history', compact('histories'));
    }

    public function depositAction($id)
    {

        $data = Transaction::find($id);

        return view('backend.deposit.include.__deposit_action', compact('data', 'id'))->render();
    }

    public function actionNow(Request $request)
    {

        $input = $request->all();
        $id = $input['id'];
        $approvalCause = $input['message'];
        $transaction = Transaction::find($id);

        if (isset($input['approve'])) {

            if ($transaction->type == TxnType::Investment) {
                $invest = Invest::where('transaction_id', $id)->first();
                $periodHours = $invest->period_hours;
                $nextProfitTime = Carbon::now()->addHour($periodHours);
                $invest->update([
                    'next_profit_time' => $nextProfitTime,
                    'status' => InvestStatus::Ongoing,
                ]);

                //level referral
                if (setting('site_referral', 'global') == 'level' && setting('investment_level')) {
                    $level = LevelReferral::where('type', 'investment')->max('the_order') + 1;
                    creditReferralBonus($transaction->user, 'investment', $transaction->amount, $level);
                }

            } else {

                //level referral
                if (setting('site_referral', 'global') == 'level' && setting('deposit_level')) {
                    $level = LevelReferral::where('type', 'deposit')->max('the_order') + 1;
                    creditReferralBonus($transaction->user, 'deposit', $transaction->amount, $level);
                }
            }

            Txn::update($transaction->tnx, TxnStatus::Success, $transaction->user_id, $approvalCause);

            notify()->success('Approve successfully');

        } elseif (isset($input['reject'])) {
            $invest = Invest::where('transaction_id', $id)->first();

            if ($invest) {
                $invest->delete();
            }
            Txn::update($transaction->tnx, TxnStatus::Failed, $transaction->user_id, $approvalCause);
            notify()->success('Reject successfully');
        }

        $shortcodes = [
            '[[full_name]]' => $transaction->user->full_name,
            '[[txn]]' => $transaction->tnx,
            '[[gateway_name]]' => $transaction->method,
            '[[deposit_amount]]' => $transaction->amount,
            '[[site_title]]' => setting('site_title', 'global'),
            '[[site_url]]' => route('home'),
            '[[message]]' => $transaction->approval_cause,
            '[[status]]' => isset($input['approve']) ? 'approved' : 'Rejected',
        ];

        $this->mailNotify($transaction->user->email, 'user_manual_deposit_request', $shortcodes);
        $this->pushNotify('user_manual_deposit_request', $shortcodes, route('user.deposit.log'), $transaction->user->id);
        $this->smsNotify('user_manual_deposit_request', $shortcodes, $transaction->user->phone);

        return redirect()->back();
    }
}
