<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Rules\RegisterCustomField;
use App\Traits\ImageUpload;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class SettingController extends Controller
{
    use ImageUpload;

    public function settings()
    {
        return view('frontend::user.setting.index');
    }

    public function profileUpdate(Request $request)
    {
        $input = $request->all();
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'username' => 'required|unique:users,username,'.$user->id,
            'gender' => 'required',
            'date_of_birth' => 'date',
            'phone' => 'required',
            'custom_fields_data' => [new RegisterCustomField(true)],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $data = [
            'avatar' => $request->hasFile('avatar') ? self::imageUploadTrait($input['avatar'], $user->avatar) : $user->avatar,
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'username' => $input['username'],
            'gender' => $input['gender'],
            'date_of_birth' => $input['date_of_birth'] == '' ? null : $input['date_of_birth'],
            'phone' => $input['phone'],
            'city' => $input['city'],
            'zip_code' => $input['zip_code'],
            'address' => $input['address'],
        ];

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
        $data['custom_fields_data'] = $input['custom_fields_data'] ?? [];
        $user->update($data);

        notify()->success('Your Profile Updated successfully');

        return redirect()->route('user.setting.show');
    }

    public function twoFa()
    {
        $user = \Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user->update([
            'google2fa_secret' => $secret,
        ]);
        notify()->success(__('QR Code And Secret Key Generate successfully'));

        return redirect()->back();
    }

    public function actionTwoFa(Request $request)
    {
        $user = \Auth::user();

        if ($request->status == 'disable') {

            if (Hash::check(request('one_time_password'), $user->password)) {
                $user->update([
                    'two_fa' => 0,
                ]);
                notify()->success(__('2Fa Authentication Disable successfully'));

                return redirect()->back();
            }

            notify()->warning(__('Wrong Your Password'));

            return redirect()->back();
        } elseif ($request->status == 'enable') {
            session([
                config('google2fa.session_var') => [
                    'auth_passed' => false,
                ],
            ]);

            $authenticator = app(Authenticator::class)->boot($request);
            if ($authenticator->isAuthenticated()) {

                $user->update([
                    'two_fa' => 1,
                ]);
                notify()->success(__('2Fa Authentication Enable successfully'));

                return redirect()->back();
            }

            notify()->warning(__('2Fa Authentication Wrong One Time Key'));

            return redirect()->back();
        }
    }
}
