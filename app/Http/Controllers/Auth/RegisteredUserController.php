<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Events\UserReferred;
use App\Http\Controllers\Controller;
use App\Models\LoginActivities;
use App\Models\Page;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Rules\Recaptcha;
use App\Rules\RegisterCustomField;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Session;
use Txn;

class RegisteredUserController extends Controller
{
    use ImageUpload, NotifyTrait;

    /**
     * Handle an incoming registration request.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $isUsername = (bool) getPageSetting('username_show');
        $isCountry = (bool) getPageSetting('country_show');
        $isPhone = (bool) getPageSetting('phone_show');
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => [Rule::requiredIf($isUsername), 'string', 'max:255', 'unique:users'],
            'country' => [Rule::requiredIf($isCountry), 'string', 'max:255'],
            'phone' => [Rule::requiredIf($isPhone), 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'g-recaptcha-response' => Rule::requiredIf(plugin_active('Google reCaptcha')),
            new Recaptcha,
            'i_agree' => ['required'],
            'custom_fields_data' => [new RegisterCustomField],
        ]);

        $input = $request->all();

        $location = getLocation();
        $phone = $isPhone ? ($isCountry ? explode(':', $input['country'])[1] : $location->dial_code).' '.$input['phone'] : $location->dial_code.' ';
        $country = $isCountry ? explode(':', $input['country'])[0] : $location->name;

        $registerCustomFields = json_decode(getPageSetting('register_custom_fields'), true);
        $custom_fields_data = [];
        if ($registerCustomFields) {
            foreach ($registerCustomFields as $key => $field) {
                if (isset($input['custom_fields_data'][$field['name']])) {
                    if (in_array($field['type'], ['file', 'camera'])) {
                        $custom_fields_data[$field['name']] = $this->imageUploadTrait($request->file('custom_fields_data.'.$field['name'].''));
                    } else {
                        $custom_fields_data[$field['name']] = $request->{'custom_fields_data.'.$field['name']};
                    }
                } else {
                    $custom_fields_data[$field['name']] = null;
                }
            }
        }

        $user = User::create([
            'ranking_id' => null,
            'rankings' => json_encode([]),
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'username' => $isUsername ? $input['username'] : $input['first_name'].$input['last_name'].rand(1000, 9999),
            'country' => $country,
            'phone' => $phone,
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'custom_fields_data' => $custom_fields_data,
        ]);

        $shortcodes = [
            '[[full_name]]' => $input['first_name'].' '.$input['last_name'],
            '[[message]]' => '.New User added our system.',
        ];

        //notify method call
        $this->pushNotify('new_user', $shortcodes, route('admin.user.edit', $user->id), $user->id);
        $this->smsNotify('new_user', $shortcodes, $user->phone);

        //referral code
        if (! setting('email_verification', 'permission')) {
            event(new UserReferred($request->cookie('invite'), $user));
        }

        if (setting('referral_signup_bonus', 'permission') && (float) setting('signup_bonus', 'fee') > 0) {
            $signupBonus = (float) setting('signup_bonus', 'fee');
            $user->increment('profit_balance', $signupBonus);
            Txn::new($signupBonus, 0, $signupBonus, 'system', 'Signup Bonus', TxnType::SignupBonus, TxnStatus::Success, null, null, $user->id);
            Session::put('signup_bonus', $signupBonus);
        }
        Cookie::queue(Cookie::forget('invite'));
        Auth::login($user);
        LoginActivities::add();

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Display the registration view.
     *
     * @return View
     */
    public function create()
    {
        if (! setting('account_creation', 'permission')) {
            abort('403', 'User registration is closed now');
        }

        $page = Page::where('code', 'registration')->where('locale', app()->getLocale())->first();
        $data = json_decode($page->data, true);

        $googleReCaptcha = plugin_active('Google reCaptcha');
        $location = getLocation();

        return view('frontend::auth.register', compact('location', 'googleReCaptcha', 'data'));
    }
}
