<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Schema;
use App\Traits\ImageUpload;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SchemaController extends Controller
{
    use ImageUpload;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function __construct()
    {
        $this->middleware('permission:schema-list|schema-create|schema-edit', ['only' => ['index', 'store']]);
        $this->middleware('permission:schema-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:schema-edit', ['only' => ['edit', 'update']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View
     */
    public function index()
    {
        $schemas = Schema::all();

        return view('backend.schema.index', compact('schemas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        $schedules = Schedule::all();
        $holidays = Holiday::all();

        // $offDaySchedule = [
        //     'Sunday',
        //     'Monday',
        //     'Tuesday',
        //     'Wednesday',
        //     'Thursday',
        //     'Friday',
        //     'Saturday',
        // ];

        // $combinedSchedule = collect($offDaySchedule)->map(function ($day) {
        //     return ['type' => 'day', 'value' => $day];
        // });

        // $combinedSchedule = $combinedSchedule->merge(
        //     $holidays->map(function ($holiday) {
        //         return ['type' => 'holiday', 'value' => $holiday->date];
        //     })
        // );

        return view('backend.schema.create', compact('schedules', 'holidays'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'icon' => 'required',
            'name' => 'required',
            'schema_type' => 'required',
            'min_amount' => 'required_if:type,==,range',
            'max_amount' => 'required_if:type,==,range',
            'fixed_amount' => 'required_if:type,==,fixed',
            'capital_back' => 'required',
            'featured' => 'required',
            'badge' => 'required_if:featured,==,1',
            'status' => 'required',
            'roi_type' => 'required',
            'roi_fixed_amount' => 'required_if:interest_type,==roi_fixed',
            'min_roi' => 'required_if:interest_type,==roi_range',
            'max_roi' => 'required_if:interest_type,==roi_range',
            'return_period' => 'required',
            'return_type' => 'required',
            'number_of_period' => 'required_if:return_type,==,period',
            'expiry_minute' => 'max:59,required_if:schema_cancel,==,1|integer|max:59',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $input = $request->all();
        if ($input['roi_type'] == 'range') {
            $roi_interest_type = $input['roi_interest_type_range'];
        } else {
            $roi_interest_type = $input['roi_interest_type_fixed'];
        }

        $finalData = [
            'name' => $input['name'],
            'type' => $input['schema_type'],
            'min_amount' => $input['schema_type'] == 'fixed' ? 0 : $input['schema_min_amount'],
            'max_amount' => $input['schema_type'] == 'fixed' ? 0 : $input['schema_min_amount'],
            'fixed_amount' => $input['schema_type'] == 'range' ? 0 : $input['schema_fixed_amount'],
            'capital_back' => $input['capital_back'],
            'featured' => $input['featured'],
            'badge' => $input['featured'] == 1 ? $input['badge'] : null,
            'status' => $input['status'],
            'interest_type' => $input['roi_type'] === 'fixed' ? 'fixed' : ($input['roi_type'] === 'range' ? 'range' : 'percentage'),
            'roi_interest_type' => $roi_interest_type,
            'fixed_roi' => $input['roi_type'] == 'range' ? 0 : $input['roi_fixed_amount'],
            'min_roi' => $input['roi_type'] == 'fixed' ? 0 : $input['roi_min_amount'],
            'max_roi' => $input['roi_type'] == 'fixed' ? 0 : $input['roi_max_amount'],
            'return_period' => $input['return_period'],
            'return_type' => $input['return_type'],
            'number_of_period' => $input['return_type'] == 'period' ? $input['number_of_period'] : 0,
            'off_days' => isset($input['off_days']) ? json_encode($input['off_days']) : null,
            'schema_cancel' => $input['schema_cancel'],
            'expiry_minute' => $input['schema_cancel'] != 0 ? $input['expiry_minute'] : 59,
            'is_trending' => $input['is_trending'],
            'icon' => self::imageUploadTrait($input['icon']),
        ];
        Schema::create($finalData);

        notify()->success('schema created successfully');

        return redirect()->route('admin.schema.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit($id)
    {
        $schedules = Schedule::all();
        $schema = Schema::find($id);
        $holidays = Holiday::all();

        return view('backend.schema.edit', compact('schema', 'schedules', 'holidays'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'schema_type' => 'required',
            'min_amount' => 'required_if:type,==,range',
            'max_amount' => 'required_if:type,==,range',
            'fixed_amount' => 'required_if:type,==,fixed',
            'capital_back' => 'required',
            'featured' => 'required',
            'badge' => 'required_if:featured,==,1',
            'status' => 'required',
            'roi_type' => 'required',
            'roi_fixed_amount' => 'required_if:interest_type,==roi_fixed',
            'min_roi' => 'required_if:interest_type,==roi_range',
            'max_roi' => 'required_if:interest_type,==roi_range',
            'return_period' => 'required',
            'return_type' => 'required',
            'number_of_period' => 'required_if:return_type,==,period',
            'expiry_minute' => 'max:59,required_if:schema_cancel,==,1|integer|max:59',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $schema = Schema::find($id);
        $input = $request->all();
        if ($input['roi_type'] == 'range') {
            $roi_interest_type = $input['roi_interest_type_range'];
        } else {
            $roi_interest_type = $input['roi_interest_type_fixed'];
        }

        $finalData = [
            'name' => $input['name'],
            'type' => $input['schema_type'],
            'min_amount' => $input['schema_type'] == 'fixed' ? 0 : $input['schema_min_amount'],
            'max_amount' => $input['schema_type'] == 'fixed' ? 0 : $input['schema_max_amount'],
            'fixed_amount' => $input['schema_type'] == 'range' ? 0 : $input['schema_fixed_amount'],
            'capital_back' => $input['capital_back'],
            'featured' => $input['featured'],
            'badge' => $input['featured'] == 1 ? $input['badge'] : null,
            'status' => $input['status'],
            'interest_type' => $input['roi_type'] === 'fixed' ? 'fixed' : ($input['roi_type'] === 'range' ? 'range' : 'percentage'),
            'roi_interest_type' => $roi_interest_type,
            'fixed_roi' => $input['roi_type'] == 'range' ? 0 : $input['roi_fixed_amount'],
            'min_roi' => $input['roi_type'] == 'fixed' ? 0 : $input['roi_min_amount'],
            'max_roi' => $input['roi_type'] == 'fixed' ? 0 : $input['roi_max_amount'],
            'return_period' => $input['return_period'],
            'return_type' => $input['return_type'],
            'number_of_period' => $input['return_type'] == 'period' ? $input['number_of_period'] : 0,
            'off_days' => isset($input['off_days']) ? json_encode($input['off_days']) : null,
            'schema_cancel' => $input['schema_cancel'],
            'expiry_minute' => $input['schema_cancel'] != 0 ? $input['expiry_minute'] : 59,
            'is_trending' => $input['is_trending'],
        ];

        if ($request->hasFile('icon')) {
            $icon = self::imageUploadTrait($input['icon'], $schema->iconicon);
            $finalData = array_merge($finalData, ['icon' => $icon]);
        }
        $schema->update($finalData);

        notify()->success('schema Update successfully');

        return redirect()->route('admin.schema.index');
    }
}
