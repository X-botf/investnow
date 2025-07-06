<?php

use App\Enums\TxnStatus;
use App\Enums\TxnType;
use App\Models\Gateway;
use App\Models\Language;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

if (! function_exists('isActive')) {
    function isActive($route, $parameter = null)
    {

        if ($parameter != null && request()->url() === route($route, $parameter)) {
            return 'active';
        }
        if ($parameter == null && is_array($route)) {
            foreach ($route as $value) {
                if (Request::routeIs($value)) {
                    return 'show';
                }
            }
        }
        if ($parameter == null && Request::routeIs($route)) {
            return 'active';
        }

    }
}

if (! function_exists('tnotify')) {
    function tnotify($type, $message)
    {
        session()->flash('tnotify', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}

if (! function_exists('setting')) {
    function setting($key, $section = null, $default = null)
    {

        if (is_null($key)) {
            return new \App\Models\Setting;
        }

        if (is_array($key)) {

            return \App\Models\Setting::set($key[0], $key[1]);
        }

        $value = \App\Models\Setting::get($key, $section, $default);

        return is_null($value) ? value($default) : $value;
    }
}

if (! function_exists('oldSetting')) {

    function oldSetting($field, $section)
    {
        return old($field, setting($field, $section));
    }
}

if (! function_exists('settingValue')) {

    function settingValue($field)
    {
        return \App\Models\Setting::get($field);
    }
}

if (! function_exists('getPageSetting')) {

    function getPageSetting($key)
    {
        return \App\Models\PageSetting::where('key', $key)->first()->value;
    }
}

if (! function_exists('curl_get_file_contents')) {

    function curl_get_file_contents($URL)
    {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        curl_close($c);

        if ($contents) {
            return $contents;
        }

        return false;

    }
}

if (! function_exists('getCountries')) {

    function getCountries()
    {
        return json_decode(file_get_contents(resource_path().'/json/CountryCodes.json'), true);
    }
}

if (! function_exists('getJsonData')) {

    function getJsonData($fileName)
    {
        return file_get_contents(resource_path()."/json/$fileName.json");
    }
}

if (! function_exists('getTimezone')) {
    function getTimezone()
    {
        $timeZones = json_decode(file_get_contents(resource_path().'/json/timeZone.json'), true);

        return array_values(Arr::sort($timeZones, function ($value) {
            return $value['name'];
        }));
    }
}

if (! function_exists('getIpAddress')) {
    function getIpAddress()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
}

if (! function_exists('getLocation')) {
    function getLocation()
    {
        $clientIp = request()->ip();

        if ($clientIp == '127.0.0.1') {
            return new \Illuminate\Support\Fluent([
                'country_code' => 'BD',
                'name' => 'BANGLADESH',
                'dial_code' => '+880',
                'ip' => '127.0.0.1',
            ]);
        }

        $location = json_decode(curl_get_file_contents('http://ip-api.com/json/'.$clientIp), true);

        $currentCountry = collect(getCountries())->firstWhere('code', $location['countryCode']);

        return new \Illuminate\Support\Fluent([
            'country_code' => data_get($currentCountry, 'code', 'unknown'),
            'name' => data_get($currentCountry, 'name', 'unknown'),
            'dial_code' => data_get($currentCountry, 'dial_code', 'unknown'),
            'ip' => data_get($location, 'query', $clientIp),
        ]);
    }
}

if (! function_exists('carbonInstance')) {
    function carbonInstance($dataTime): Carbon
    {
        return Carbon::create($dataTime->toString());
    }
}

if (! function_exists('gateway_info')) {
    function gateway_info($code)
    {
        $info = Gateway::where('gateway_code', $code)->first();

        return json_decode($info->credentials);
    }
}

if (! function_exists('localeName')) {
    function localeName()
    {
        return Language::where('locale', App::currentLocale())->first()?->name;
    }
}

if (! function_exists('plugin_active')) {
    function plugin_active($name)
    {
        $plugin = \App\Models\Plugin::where('name', $name)->where('status', true)->first();
        if (! $plugin) {
            $plugin = \App\Models\Plugin::where('type', $name)->where('status', true)->first();
        }

        return $plugin;
    }
}

if (! function_exists('default_plugin')) {
    function default_plugin($type)
    {
        return \App\Models\Plugin::where('type', $type)->where('status', 1)->first('name')?->name;
    }
}

if (! function_exists('br2nl')) {
    function br2nl($input)
    {
        return preg_replace('/<br\\s*?\/??>/i', '', $input);
    }
}

if (! function_exists('safe')) {
    function safe($input)
    {
        if (! env('APP_DEMO', false)) {
            return $input;
        }

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {

            $emailParts = explode('@', $input);
            $username = $emailParts[0];
            $hiddenUsername = substr($username, 0, 2).str_repeat('*', strlen($username) - 2);
            $hiddenEmailDomain = substr($emailParts[1], 0, 2).str_repeat('*', strlen($emailParts[1]) - 3).
            $emailParts[1][strlen($emailParts[1]) - 1];

            return $hiddenUsername.'@'.$hiddenEmailDomain;

        }

        return preg_replace('/(\d{3})\d{3}(\d{3})/', '$1****$2', $input);

    }
}

if (! function_exists('creditReferralBonus')) {
    function creditReferralBonus($user, $type, $mainAmount, $level)
    {
        $depth = 1;
        $currentUser = $user;
        $fromUser = $user;

        while ($depth <= $level && $currentUser->ref_id !== null) {
            // Fetch the referral level configuration for the current depth
            $LevelReferral = \App\Models\LevelReferral::where('type', $type)->where('the_order', $depth)->first('bounty');

            if (! $LevelReferral) {
                break; // No configuration for this level
            }

            // Get the referrer user
            $referrer = User::find($currentUser->ref_id);

            // Calculate the referral bounty amount
            $bounty = $LevelReferral->bounty;
            $amount = ($mainAmount * $bounty) / 100;

            $description = ucwords($type).' Referral Bonus Via '.$fromUser->full_name.' - Level '.$depth;

            // Record the transaction for the referral bonus
            Txn::new($amount, 0, $amount, 'system', $description, TxnType::Referral, TxnStatus::Success, null, null,
                $referrer->id, $fromUser->id, 'User', [], 'none', $depth, $type, true);

            // Update the referrer's profit balance
            $referrer->profit_balance += $amount;
            $referrer->save();

            // Move to the next level up the referral chain
            $currentUser = $referrer;
            $depth++;
        }
    }
}

if (! function_exists('txn_type')) {
    function txn_type($type, $value = [], $theme = 'default')
    {

        $result = [];
        switch ($type) {
            case TxnType::Interest->value:
            case TxnType::ReceiveMoney->value:
            case TxnType::Deposit->value:
            case TxnType::ManualDeposit->value:
            case TxnType::Bonus->value:
            case TxnType::Refund->value:
            case TxnType::Exchange->value:
            case TxnType::Referral->value:
            case TxnType::SignupBonus->value:
                $result = $theme == 'hardrock' ? ['success-text', '+'] : ['green-color', '+'];
                break;
            case TxnType::SendMoney->value:
            case TxnType::Investment->value:
            case TxnType::Withdraw->value:
            case TxnType::Subtract->value:
                $result = $theme == 'hardrock' ? ['danger-text', '-'] : ['red-color', '-'];
                break;
        }

        $commonResult = array_intersect($value, $result);

        return current($commonResult);
    }
}

if (! function_exists('getClassName')) {
    function getClassName($type)
    {
        $class = '';

        switch ($type) {
            case TxnType::Interest:
            case TxnType::ReceiveMoney:
            case TxnType::Deposit:
            case TxnType::ManualDeposit:
            case TxnType::Bonus:
            case TxnType::Refund:
            case TxnType::Exchange:
            case TxnType::Referral:
            case TxnType::SignupBonus:
                $class = 'green-text';
                break;
            case TxnType::SendMoney:
            case TxnType::Investment:
            case TxnType::Withdraw:
            case TxnType::WithdrawAuto:
            case TxnType::Subtract:
            case TxnType::RewardRedeem:
                $class = 'kissable-text';
                break;
        }

        return $class;
    }
}

if (! function_exists('getIcon')) {

    function getIcon($type)
    {
        $icon = '';

        switch ($type) {
            case TxnType::Deposit:
            case TxnType::ManualDeposit:
            case TxnType::Refund:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M4 8H2V17L6.31083 19.1554C7.42168 19.7108 8.64658 20 9.88854 20H18C19.1046 20 20 19.1046 20 18C20 16.8954 19.1046 16 18 16H16.4164C15.4849 16 14.5663 15.7831 13.7331 15.3666L10.792 13.896C10.9843 13.7189 11.1432 13.4993 11.2528 13.2434C11.6664 12.2784 11.2241 11.1605 10.2622 10.7397L4 8Z"
            fill="#80ED99"></path>
        <circle cx="18" cy="8" r="4" fill="#80ED99"></circle>
    </svg>';
                break;
            case TxnType::Interest:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M4 8H2V17L6.31083 19.1554C7.42168 19.7108 8.64658 20 9.88854 20H18C19.1046 20 20 19.1046 20 18C20 16.8954 19.1046 16 18 16H16.4164C15.4849 16 14.5663 15.7831 13.7331 15.3666L10.792 13.896C10.9843 13.7189 11.1432 13.4993 11.2528 13.2434C11.6664 12.2784 11.2241 11.1605 10.2622 10.7397L4 8Z"
            fill="#E9D8A6"></path>
        <circle cx="18" cy="8" r="4" fill="#E9D8A6"></circle>
    </svg>';
                break;
            case TxnType::Investment:
            case TxnType::ReceiveMoney:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z"
            fill="#FFD6FF"></path>
        <path fill-rule="evenodd" clip-rule="evenodd"
            d="M12 5.75C12.4142 5.75 12.75 6.08579 12.75 6.5V7.35352C13.9043 7.67998 14.75 8.74122 14.75 10C14.75 10.4142 14.4142 10.75 14 10.75C13.5858 10.75 13.25 10.4142 13.25 10C13.25 9.30964 12.6904 8.75 12 8.75C11.3096 8.75 10.75 9.30964 10.75 10C10.75 10.6904 11.3096 11.25 12 11.25C13.5188 11.25 14.75 12.4812 14.75 14C14.75 15.2588 13.9043 16.32 12.75 16.6465V17.5C12.75 17.9142 12.4142 18.25 12 18.25C11.5858 18.25 11.25 17.9142 11.25 17.5V16.6465C10.0957 16.32 9.25 15.2588 9.25 14C9.25 13.5858 9.58579 13.25 10 13.25C10.4142 13.25 10.75 13.5858 10.75 14C10.75 14.6904 11.3096 15.25 12 15.25C12.6904 15.25 13.25 14.6904 13.25 14C13.25 13.3096 12.6904 12.75 12 12.75C10.4812 12.75 9.25 11.5188 9.25 10C9.25 8.74122 10.0957 7.67998 11.25 7.35352V6.5C11.25 6.08579 11.5858 5.75 12 5.75Z"
            fill="#FFD6FF"></path>
    </svg>';
                break;
            case TxnType::Exchange:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M2 4C2 2.89543 2.89543 2 4 2H12C13.1046 2 14 2.89543 14 4V8C14 9.10457 13.1046 10 12 10H4C2.89543 10 2 9.10457 2 8V4Z"
            fill="#FF8F77"></path>
        <path opacity="0.4"
            d="M10 16C10 14.8954 10.8954 14 12 14H20C21.1046 14 22 14.8954 22 16V20C22 21.1046 21.1046 22 20 22H12C10.8954 22 10 21.1046 10 20V16Z"
            fill="#FF8F77"></path>
        <path fill-rule="evenodd" clip-rule="evenodd"
            d="M20.6036 6.75L19.8839 7.46967C19.591 7.76256 19.591 8.23744 19.8839 8.53033C20.1768 8.82322 20.6517 8.82322 20.9445 8.53033L22.2374 7.23744C22.9209 6.55402 22.9209 5.44598 22.2374 4.76256L20.9445 3.46967C20.6517 3.17678 20.1768 3.17678 19.8839 3.46967C19.591 3.76256 19.591 4.23744 19.8839 4.53033L20.6036 5.25L16 5.25C15.5858 5.25 15.25 5.58579 15.25 6C15.25 6.41421 15.5858 6.75 16 6.75L20.6036 6.75Z"
            fill="#FF8F77"></path>
        <path fill-rule="evenodd" clip-rule="evenodd"
            d="M3.39645 18.75L4.11612 19.4697C4.40901 19.7626 4.40901 20.2374 4.11612 20.5303C3.82322 20.8232 3.34835 20.8232 3.05546 20.5303L1.76256 19.2374C1.07915 18.554 1.07914 17.446 1.76256 16.7626L3.05546 15.4697C3.34835 15.1768 3.82322 15.1768 4.11612 15.4697C4.40901 15.7626 4.40901 16.2374 4.11612 16.5303L3.39645 17.25L8 17.25C8.41421 17.25 8.75 17.5858 8.75 18C8.75 18.4142 8.41421 18.75 8 18.75L3.39645 18.75Z"
            fill="#FF8F77"></path>
    </svg>';
                break;
            case TxnType::Bonus:
            case TxnType::Referral:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M9.41266 4.68911C10.2496 3.85219 11.4055 3.41786 12.5771 3.50011L16.5414 3.77844C18.5209 3.91741 20.0839 5.48041 20.2228 7.45987L20.5011 11.4242C20.5834 12.5958 20.1491 13.7517 19.3122 14.5886L12.7468 21.154C11.1635 22.7373 8.61357 22.7545 7.05148 21.1924L2.80884 16.9498C1.24674 15.3877 1.26396 12.8378 2.8473 11.2545L9.41266 4.68911Z"
            fill="#86A8FF"></path>
        <circle cx="14.8281" cy="9.17218" r="2" transform="rotate(45 14.8281 9.17218)" fill="#86A8FF"></circle>
    </svg>';
                break;
            case TxnType::Withdraw:
            case TxnType::WithdrawAuto:
            case TxnType::Subtract:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M4 12C4 10.8954 4.89543 10 6 10H15C16.1046 10 17 10.8954 17 12C17 13.1046 16.1046 14 15 14H6C4.89543 14 4 13.1046 4 12Z"
            fill="#FFF4CC"></path>
        <path
            d="M15 14H6.16667C4.97005 14 4 14.8954 4 16C4 17.1046 4.97005 18 6.16667 18H15C16.1046 18 17 17.1046 17 16C17 14.8954 16.1046 14 15 14Z"
            fill="#FFF4CC"></path>
        <path opacity="0.4"
            d="M20 18C20 15.7909 18.2091 14 16 14C15.8007 14 15.6047 14.0146 15.4132 14.0427C13.4823 14.3266 12 15.9902 12 18C12 20.2091 13.7909 22 16 22C18.2091 22 20 20.2091 20 18Z"
            fill="#FFF4CC"></path>
        <path fill-rule="evenodd" clip-rule="evenodd"
            d="M11.25 3.39645L10.5303 4.11612C10.2374 4.40901 9.76256 4.40901 9.46967 4.11612C9.17678 3.82322 9.17678 3.34835 9.46967 3.05546L10.7626 1.76256C11.446 1.07915 12.554 1.07914 13.2374 1.76256L14.5303 3.05546C14.8232 3.34835 14.8232 3.82322 14.5303 4.11612C14.2374 4.40901 13.7626 4.40901 13.4697 4.11612L12.75 3.39645L12.75 7C12.75 7.41421 12.4142 7.75 12 7.75C11.5858 7.75 11.25 7.41421 11.25 7L11.25 3.39645Z"
            fill="#FFF4CC"></path>
    </svg>';
                break;
            case TxnType::SendMoney:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M6 12H8C10.2091 12 12 13.7909 12 16V18C12 20.2091 10.2091 22 8 22H6C3.79086 22 2 20.2091 2 18V16C2 13.7909 3.79086 12 6 12Z"
            fill="#56F5FF"></path>
        <path opacity="0.4"
            d="M10 2H18C20.2091 2 22 3.79086 22 6V14C22 16.2091 20.2091 18 18 18H10C7.79086 18 6 16.2091 6 14V6C6 3.79086 7.79086 2 10 2Z"
            fill="#56F5FF"></path>
        <path fill-rule="evenodd" clip-rule="evenodd"
            d="M11.25 7C11.25 6.58579 11.5858 6.25 12 6.25H17C17.4142 6.25 17.75 6.58579 17.75 7V12C17.75 12.4142 17.4142 12.75 17 12.75C16.5858 12.75 16.25 12.4142 16.25 12V8.81066L10.5303 14.5303C10.2374 14.8232 9.76256 14.8232 9.46967 14.5303C9.17678 14.2374 9.17678 13.7626 9.46967 13.4697L15.1893 7.75H12C11.5858 7.75 11.25 7.41421 11.25 7Z"
            fill="#56F5FF"></path>
    </svg>';
                break;
            case TxnType::RewardRedeem:
                $icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path opacity="0.4"
            d="M4 8H2V17L6.31083 19.1554C7.42168 19.7108 8.64658 20 9.88854 20H18C19.1046 20 20 19.1046 20 18C20 16.8954 19.1046 16 18 16H16.4164C15.4849 16 14.5663 15.7831 13.7331 15.3666L10.792 13.896C10.9843 13.7189 11.1432 13.4993 11.2528 13.2434C11.6664 12.2784 11.2241 11.1605 10.2622 10.7397L4 8Z"
            fill="#E9D8A6"></path>
        <circle cx="18" cy="8" r="4" fill="#E9D8A6"></circle>
    </svg>';
                break;
        }

        return $icon;
    }
}

if (! function_exists('is_custom_rate')) {
    function is_custom_rate($gateway_code)
    {
        if (in_array($gateway_code, ['nowpayments', 'coinremitter', 'blockchain'])) {
            return 'USD';
        }

        return null;
    }
}

if (! function_exists('site_theme')) {
    function site_theme()
    {
        $theme = new \App\Models\Theme;

        return $theme->active();
    }
}

if (! function_exists('content_exists')) {
    function content_exists($url)
    {
        return file_exists(base_path('assets/'.$url));
    }
}

if (! function_exists('generate_date_range_array')) {
    function generate_date_range_array($startDate, $endDate): array
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $dates = collect([]);

        while ($startDate->lte($endDate)) {
            $dates->push($startDate->format('d M'));
            $startDate->addDay();
        }

        return $dates->toArray();
    }
}
if (! function_exists('calPercentage')) {
    function calPercentage($num, $percentage)
    {
        return $num * ($percentage / 100);
    }
}

if (! function_exists('getQRCode')) {
    function getQRCode($data)
    {

        return "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=$data";
    }
}
if (! function_exists('isPlusTransaction')) {
    function isPlusTransaction($type)
    {
        if (
            $type == TxnType::Subtract || $type == TxnType::Deposit || $type == TxnType::ManualDeposit ||
            $type == TxnType::Withdraw || $type == TxnType::WithdrawAuto || $type == TxnType::Bonus ||
            $type == TxnType::PortfolioBonus || $type == TxnType::ReceiveMoney || $type == TxnType::Investment ||
            $type == TxnType::Interest || $type == TxnType::Refund
        ) {
            return false;
        }

        return true;
    }
}
