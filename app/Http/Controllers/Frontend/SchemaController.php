<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Schema;

class SchemaController extends Controller
{
    public function index()
    {

        $schemas = Schema::where('status', true)->with('schedule')->get();

        return view('frontend::schema.index', compact('schemas'));
    }

    public function schemaPreview($id)
    {
        $schemas = Schema::where('status', true)->with('schedule')->get();
        $schema = Schema::with('schedule')->find($id);

        return view('frontend::schema.preview', compact('schema', 'schemas'));
    }

    public function schemaSelect($id)
    {
        $schema = Schema::with('schedule')->find($id);
        $currency = setting('site_currency', 'global');

        $interest = $schema->interest_type == 'fixed' ? $schema->fixed_roi : $schema->min_roi.'-'.$schema->max_roi;
        $return_interest = ($schema->roi_interest_type == 'percentage' ? $interest.'%' : $interest.' '.$currency).' ('.$schema->schedule->name.')';
        $int = $schema->interest_type == 'fixed' ? $schema->fixed_roi : round($schema->min_roi + mt_rand() / mt_getrandmax() * ($schema->max_roi - $schema->min_roi), 1);

        return [
            'holiday' => $schema->off_days != null ? implode(', ', json_decode($schema->off_days, true)) : __('No'),
            'amount_range' => $schema->type == 'range' ? 'Minimum '.$schema->min_amount.' '.$currency.' - '.'Maximum '.$schema->max_amount.' '.$currency : $schema->fixed_amount.' '.$currency,
            'return_interest' => $return_interest,
            'number_period' => ($schema->return_type == 'period' ? $schema->number_of_period : 'Unlimited').($schema->number_of_period == 1 ? ' Time' : ' Times'),
            'capital_back' => $schema->capital_back ? 'Yes' : 'No',
            'invest_amount' => $schema->type == 'fixed' ? $schema->fixed_amount : 0,
            'interest' => $int,
            'period' => $schema->number_of_period,
            'interest_type' => $schema->interest_type,
            'roi_interest_type' => $schema->roi_interest_type,
            'min_roi' => $schema->min_roi,
            'max_roi' => $schema->max_roi,
            'fixed_roi' => $schema->fixed_roi,
        ];

    }
}
