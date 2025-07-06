<?php

namespace App\Http\Controllers\Backend;

use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Http\Controllers\Controller;
use App\Models\Invest;
use App\Models\LevelReferral;
use App\Models\LoginActivities;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\RegisterCustomField;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Exception;
use Hash;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Txn;

class UserController extends Controller
{
    use ImageUpload, NotifyTrait;

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:customer-list|customer-login|customer-mail-send|customer-basic-manage|customer-change-password|all-type-status|customer-balance-add-or-subtract', ['only' => ['index', 'activeUser', 'disabled', 'mailSendAll', 'mailSend']]);
        $this->middleware('permission:customer-basic-manage|customer-change-password|all-type-status|customer-balance-add-or-subtract', ['only' => ['edit']]);
        $this->middleware('permission:customer-login', ['only' => ['userLogin']]);
        $this->middleware('permission:customer-mail-send', ['only' => ['mailSendAll', 'mailSend']]);
        $this->middleware('permission:customer-basic-manage', ['only' => ['update']]);
        $this->middleware('permission:customer-change-password', ['only' => ['passwordUpdate']]);
        $this->middleware('permission:all-type-status', ['only' => ['statusUpdate']]);
        $this->middleware('permission:customer-balance-add-or-subtract', ['only' => ['balanceUpdate']]);
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function index(Request $request)
    {
        $search = $request->query('query') ?? null;
        $users = User::query()
            ->when(! blank(request('email_status')), function ($query) {
                if (request('email_status') == 'verified') {
                    $query->whereNotNull('email_verified_at');
                } elseif (request('email_status') == 'unverified') {
                    $query->whereNull('email_verified_at');
                }
            })
            ->when(! blank(request('kyc_status')), function ($query) {
                $query->where('kyc', request('kyc_status'));
            })
            ->when(! blank(request('status')), function ($query) {
                $query->where('status', request('status'));
            })
            ->when(! blank(request('sort_field')), function ($query) {
                $query->orderBy(request('sort_field'), request('sort_dir'));
            })
            ->when(! request()->has('sort_field'), function ($query) {
                $query->latest();
            })
            ->search($search)
            ->paginate();

        $title = __('All Customers');

        return view('backend.user.index', compact('users', 'title'));
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function activeUser(Request $request)
    {
        $search = $request->query('query') ?? null;

        $users = User::active()
            ->when(! blank(request('email_status')), function ($query) {
                if (request('email_status') === 'verified') {
                    $query->whereNotNull('email_verified_at');
                } elseif (request('email_status') === 'unverified') {
                    $query->whereNull('email_verified_at');
                }
            })
            ->when(! blank(request('kyc_status')), function ($query) {
                $query->where('kyc', request('kyc_status'));
            })
            ->when(! blank(request('status')), function ($query) {
                $query->where('status', request('status'));
            })
            ->when(! blank(request('sort_field')), function ($query) {
                $query->orderBy(request('sort_field'), request('sort_dir'));
            })
            ->when(! request()->has('sort_field'), function ($query) {
                $query->latest();
            })
            ->search($search)
            ->paginate();

        $title = __('All Customers');

        return view('backend.user.index', compact('users', 'title'));
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function disabled(Request $request)
    {
        $search = $request->query('query') ?? null;

        $users = User::disabled()
            ->when(! blank(request('email_status')), function ($query) {
                if (request('email_status') === 'verified') {
                    $query->whereNotNull('email_verified_at');
                } elseif (request('email_status') === 'unverified') {
                    $query->whereNull('email_verified_at');
                }
            })
            ->when(! blank(request('kyc_status')), function ($query) {
                $query->where('kyc', request('kyc_status'));
            })
            ->when(! blank(request('status')), function ($query) {
                $query->where('status', request('status'));
            })
            ->when(! blank(request('sort_field')), function ($query) {
                $query->orderBy(request('sort_field'), request('sort_dir'));
            })
            ->when(! request()->has('sort_field'), function ($query) {
                $query->latest();
            })
            ->search($search)
            ->paginate();
        $title = __('All Customers');

        return view('backend.user.index', compact('users', 'title'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit($id, $tab = null)
    {
        $user = User::findOrFail($id);
        $level = LevelReferral::where('type', 'investment')->max('the_order') + 1;
        $login_activities = LoginActivities::where('user_id', $id)->first();

        $viewData = compact('user', 'level', 'login_activities');

        $viewData['investmentData'] = Invest::with('schema')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'investments');

        $viewData['earningData'] = Transaction::where('user_id', $id)
            ->whereIn('type', [
                TxnType::Referral,
                TxnType::Interest,
                TxnType::Bonus,
                TxnType::SignupBonus,
            ])->latest()
            ->paginate(10, ['*'], 'earnings');

        $viewData['transactionData'] = Transaction::where('user_id', $id)
            ->latest()
            ->paginate(10, ['*'], 'transactions');

        $viewData['tickets'] = Ticket::where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'tickets');

        return view('backend.user.edit', $viewData);
    }

    /**
     * @return RedirectResponse
     */
    public function statusUpdate($id, Request $request)
    {

        $input = $request->all();
        $validator = Validator::make($input, [
            'status' => 'required',
            'email_verified' => 'required',
            'kyc' => 'required',
            'two_fa' => 'required',
            'deposit_status' => 'required',
            'withdraw_status' => 'required',
            'transfer_status' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $data = [
            'status' => $input['status'],
            'kyc' => $input['kyc'],
            'two_fa' => $input['two_fa'],
            'deposit_status' => $input['deposit_status'],
            'withdraw_status' => $input['withdraw_status'],
            'transfer_status' => $input['transfer_status'],
            'email_verified_at' => $input['email_verified'] == 1 ? now() : null,
        ];

        $user = User::find($id);

        if ($user->status != $input['status'] && ! $input['status']) {

            $shortcodes = [
                '[[full_name]]' => $user->full_name,
                '[[site_title]]' => setting('site_title', 'global'),
                '[[site_url]]' => route('home'),
            ];

            $this->mailNotify($user->email, 'user_account_disabled', $shortcodes);
            $this->smsNotify('user_account_disabled', $shortcodes, $user->phone);
        }

        User::find($id)->update($data);

        notify()->success('Status Updated Successfully', 'success');

        return redirect()->back();
    }

    /**
     * @return RedirectResponse
     */
    public function update($id, Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'first_name' => 'required',
            'last_name' => 'required',
            'username' => 'required|unique:users,username,'.$id,
            'custom_fields_data' => [new RegisterCustomField(true)],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $user = User::findOrFail($id);

        $registerCustomFields = json_decode(getPageSetting('register_custom_fields'), true);
        if ($registerCustomFields) {
            foreach ($registerCustomFields as $key => $field) {
                if (isset($input['custom_fields_data'][$field['name']])) {
                    if (in_array($field['type'], ['file', 'camera'])) {
                        $input['custom_fields_data'][$field['name']] = $this->imageUploadTrait($request->file('custom_fields_data.'.$field['name'].''));
                    } else {
                        $input['custom_fields_data'][$field['name']] = $request->{'custom_fields_data.'.$field['name']};
                    }
                } else {
                    $input['custom_fields_data'][$field['name']] = $user->custom_fields_data[$field['name']] ?? null;
                }
            }
        }

        $input['custom_fields_data'] = $input['custom_fields_data'] ?? [];

        $user->update($input);

        notify()->success('User Info Updated Successfully', 'success');

        return redirect()->back();
    }

    public function destroy($id)
    {
        try {
            $user = User::find($id);
            $user->transaction()->delete();
            $user->ticket()->delete();
            $user->activities()->delete();
            $user->messages()->delete();
            $user->notifications()->delete();
            $user->refferelLinks()->delete();
            $user->withdrawAccounts()->delete();
            Invest::where('user_id', $id)->delete();
            $user->delete();

            notify()->success(__('User deleted successfully'), 'Success');

            return to_route('admin.user.index');
        } catch (\Throwable $th) {
            notify()->error(__('Sorry, something went wrong!'), 'Error');

            return redirect()->back();
        }
    }

    /**
     * @return RedirectResponse
     */
    public function passwordUpdate($id, Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'new_password' => ['required'],
            'new_confirm_password' => ['same:new_password'],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $password = $validator->validated();

        User::find($id)->update([
            'password' => Hash::make($password['new_password']),
        ]);
        notify()->success('User Password Updated Successfully', 'success');

        return redirect()->back();
    }

    /**
     * @return RedirectResponse|void
     */
    public function balanceUpdate($id, Request $request)
    {

        $validator = Validator::make($request->all(), [
            'amount' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        try {

            $amount = $request->amount;
            $type = $request->type;
            $wallet = $request->wallet;

            $user = User::find($id);
            $adminUser = \Auth::user();

            if ($type == 'add') {

                if ($wallet == 'main') {
                    $user->balance += $amount;
                    $user->save();
                } else {
                    $user->profit_balance += $amount;
                    $user->save();
                }

                Txn::new($amount, 0, $amount, 'system', 'Money added in '.ucwords($wallet).' Wallet from System', TxnType::Deposit, TxnStatus::Success, null, null, $id, $adminUser->id, 'Admin');

                $status = 'success';
                $message = __('Account Balance Update');
            } elseif ($type == 'subtract') {

                if ($wallet == 'main') {
                    $user->balance -= $amount;
                    $user->save();
                } else {
                    $user->profit_balance -= $amount;
                    $user->save();
                }

                Txn::new($amount, 0, $amount, 'system', 'Money subtract in '.ucwords($wallet).' Wallet from System', TxnType::Subtract, TxnStatus::Success, null, null, $id, $adminUser->id, 'Admin');
                $status = 'success';
                $message = __('Account Balance Updated');
            }

            notify()->success($message, $status);

            return redirect()->back();
        } catch (Exception $e) {
            $status = 'warning';
            $message = __('something is wrong');
            $code = 503;
        }
    }

    /**
     * @return Application|Factory|View
     */
    public function mailSendAll()
    {
        return view('backend.user.mail_send_all');
    }

    /**
     * @return RedirectResponse
     */
    public function mailSend(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'subject' => 'required',
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        try {

            $input = [
                'subject' => $request->subject,
                'message' => $request->message,
            ];

            $shortcodes = [
                '[[subject]]' => $input['subject'],
                '[[message]]' => $input['message'],
                '[[site_title]]' => setting('site_title', 'global'),
                '[[site_url]]' => route('home'),
            ];

            if (isset($request->id)) {
                $user = User::find($request->id);

                $shortcodes = array_merge($shortcodes, ['[[full_name]]' => $user->full_name]);

                $this->mailNotify($user->email, 'user_mail', $shortcodes);
            } else {
                $users = User::where('status', 1)->get();

                foreach ($users as $user) {
                    $shortcodes = array_merge($shortcodes, ['[[full_name]]' => $user->full_name]);

                    $this->mailNotify($user->email, 'user_mail', $shortcodes);
                }
            }
            $status = 'success';
            $message = __('Mail Send Successfully');
        } catch (Exception $e) {

            $status = 'warning';
            $message = __('something is wrong');
        }

        notify()->$status($message, $status);

        return redirect()->back();
    }

    /**
     * @return JsonResponse|void
     *
     * @throws Exception
     */
    public function transaction($id, Request $request)
    {

        if ($request->ajax()) {
            $data = Transaction::where('user_id', $id)->latest();

            return Datatables::of($data)
                ->addIndexColumn()
                ->editColumn('status', 'backend.user.include.__txn_status')
                ->editColumn('type', 'backend.user.include.__txn_type')
                ->editColumn('final_amount', 'backend.user.include.__txn_amount')
                ->rawColumns(['status', 'type', 'final_amount'])
                ->make(true);
        }
    }

    /**
     * @return RedirectResponse
     */
    public function userLogin($id)
    {
        Auth::guard('web')->loginUsingId($id);

        return redirect()->route('user.dashboard');
    }

    public function notificationSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required',
        ]);
        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        try {

            $notification = new Notification;
            $notification->user_id = $request->id;
            $notification->notice = $request->message;
            $notification->for = 'popup';

            $notification->save();
            $status = 'success';
            $message = __('Notification Send Successfully');
        } catch (Exception $e) {

            $status = 'warning';
            $message = __('something is wrong');
        }

        notify()->$status($message, $status);

        return redirect()->back();
    }
}
