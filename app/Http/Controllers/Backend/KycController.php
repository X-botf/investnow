<?php

namespace App\Http\Controllers\Backend;

use App\Enums\KYCStatus;
use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\User;
use App\Traits\NotifyTrait;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Validator;

class KycController extends Controller
{
    use NotifyTrait;

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:kyc-form-manage', ['only' => ['create', 'store', 'show', 'edit', 'update', 'destroy']]);
        $this->middleware('permission:kyc-list', ['only' => ['KycPending', 'kycAll', 'KycRejected']]);
        $this->middleware('permission:kyc-action', ['only' => ['depositAction', 'actionNow']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View
     */
    public function index()
    {
        $kycs = Kyc::all();

        return view('backend.kyc.index', compact('kycs'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return string
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|unique:kycs,name',
            'status' => 'required',
            'fields' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $data = [
            'name' => $input['name'],
            'status' => $input['status'],
            'fields' => json_encode($input['fields']),
        ];

        $kyc = Kyc::create($data);
        notify()->success($kyc->name.' '.__(' KYC Created'));

        return redirect()->route('admin.kyc-form.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('backend.kyc.create');
    }

    /**
     * Display the specified resource.
     *
     * @return Application|Factory|View
     */
    public function show(Kyc $kyc)
    {
        return view('backend.kyc.edit', compact('kyc'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Application|Factory|View
     */
    public function edit($id)
    {
        $kyc = Kyc::find($id);

        return view('backend.kyc.edit', compact('kyc'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return RedirectResponse
     */
    public function destroy($id)
    {
        Kyc::find($id)->delete();
        notify()->success(__('KYC Deleted Successfully'));

        return redirect()->route('admin.kyc-form.index');
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function KycPending(Request $request)
    {
        $search = $request->query('query') ?? null;

        $kycs = User::where('kyc', KYCStatus::Pending->value)
            ->search($search)
            ->latest('updated_at')
            ->paginate(10);

        $title = __('All Pending KYC');

        return view('backend.kyc.pending', compact('kycs', 'title'));
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function KycRejected(Request $request)
    {
        $search = $request->query('query') ?? null;

        $kycs = User::where('kyc', KYCStatus::Failed->value)
            ->search($search)
            ->latest()
            ->paginate(10);

        $title = __('All Rejected KYC');

        return view('backend.kyc.rejected', compact('kycs', 'title'));
    }

    /**
     * @return string
     */
    public function depositAction($id)
    {
        $user = User::find($id);
        $kycCredential = json_decode($user->kyc_credential, true);
        unset($kycCredential['kyc_type_of_name']);
        unset($kycCredential['kyc_time_of_time']);

        $kycStatus = $user->kyc;

        return view('backend.kyc.include.__kyc_data', compact('kycCredential', 'id', 'kycStatus'))->render();
    }

    /**
     * @return RedirectResponse
     */
    public function actionNow(Request $request)
    {
        $input = $request->all();
        $user = User::find($input['id']);
        $kycCredential = json_decode($user->kyc_credential, true);
        $kycCredential = array_merge($kycCredential, ['Action Message' => $input['message']]);
        $user->update([
            'kyc' => $input['status'],
            'kyc_credential' => $kycCredential,
        ]);

        $status = $input['status'] == 1 ? 'Approved' : ($input['status'] == 2 ? 'Pending' : ($input['status'] == 3 ? 'Rejected' : 'Unknown'));

        $shortcodes = [
            '[[full_name]]' => $user->full_name,
            '[[email]]' => $user->email,
            '[[site_title]]' => setting('site_title', 'global'),
            '[[site_url]]' => route('home'),
            '[[kyc_type]]' => $kycCredential['kyc_type_of_name'],
            '[[message]]' => $input['message'],
            '[[status]]' => $status,
        ];
        $this->mailNotify($user->email, 'kyc_action', $shortcodes);
        $this->smsNotify('kyc_action', $shortcodes, $user->phone);
        $this->pushNotify('kyc_action', $shortcodes, route('user.kyc'), $user->id);

        notify()->success(__('KYC Update Successfully'));

        return redirect()->route('admin.kyc.all');
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, $id)
    {

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|unique:kycs,name,'.$id,
            'status' => 'required',
            'fields' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $data = [
            'name' => $input['name'],
            'status' => $input['status'],
            'fields' => json_encode($input['fields']),
        ];

        $kyc = Kyc::find($id);
        $kyc->update($data);
        notify()->success($kyc->name.' '.__(' KYC Updated'));

        return redirect()->route('admin.kyc-form.index');
    }

    /**
     * @return Application|Factory|View|JsonResponse
     *
     * @throws Exception
     */
    public function kycAll(Request $request)
    {
        $search = $request->query('query') ?? null;
        $status = $request->query('status') ?? null;

        $kycs = User::query()
            ->whereNotNull('kyc_credential')
            ->when($search, function ($query) use ($search) {
                $query->search($search);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('kyc', $status);
            })
            ->latest()
            ->paginate(10);

        $title = __('All KYC Requests');

        return view('backend.kyc.all', compact('kycs', 'title'));
    }
}
