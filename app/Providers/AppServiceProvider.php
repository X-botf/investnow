<?php

namespace App\Providers;

use App\Models\Language;
use App\Models\Theme;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Remotelywork\Installer\Repository\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application modules.
     *
     * @return void
     */
    public function register()
    {
        Paginator::defaultView('frontend::include.__pagination');

    }

    /**
     * Bootstrap any application modules.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function boot()
    {

        if (App::dbConnectionCheck()) {
            $timezone = setting('site_timezone', 'global');
            config()->set([
                'app.timezone' => $timezone,
                'app.debug' => setting('debug_mode', 'permission'),
                'app.locale' => Language::where('is_default', '=', true)->first('locale')->locale ?? 'en',
            ]);
            date_default_timezone_set($timezone);

            Blade::directive('lasset', function ($expression) {
                $customLandingTheme = Theme::where('type', 'landing')->where('status', true)->first();
                if ($customLandingTheme) {
                    return asset("landing_theme/$customLandingTheme->name/$expression");
                }

                return false;
            });

            Blade::directive('removeimg', function ($expression) {
                [$isHidden, $img_field] = explode(',', $expression);
                $isHidden = trim($isHidden);
                $img_field = trim($img_field);

                return "<?php \$isHidden = $isHidden; \$img_field = '$img_field'; ?>
                <div data-des=\"<?php echo \$img_field; ?>\" <?php if(!\$isHidden) echo 'hidden'; ?> class=\"close remove-img <?php echo \$img_field; ?>\"><i icon-name=\"x\"></i></div>";
            });
        }

    }
}
