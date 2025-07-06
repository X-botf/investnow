<?php

namespace App\Http\Middleware;

use App\Enums\KYCStatus;
use Closure;
use Illuminate\Http\Request;

class KYC
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $kyc = auth()->user()->kyc;
        if ($kyc == KYCStatus::Verified->value || ! setting('kyc_verification', 'permission')) {
            return $next($request);
        }
        tnotify('warning', 'Your account is unverified with Kyc.');

        return redirect()->back();
    }
}
