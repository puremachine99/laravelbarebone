<?php

namespace App\Providers;

use App\Models\Employee;
use App\Observers\EmployeeObserver;
use App\View\Components\AccessibleSelect;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureFrontendAssets();

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);

        Employee::observe(EmployeeObserver::class);

        $this->configureAccessibleSelect();
    }

    /**
     * Mary UI's <x-select> renders its label as a <fieldset> <legend>, which never gets wired
     * to the <select> itself — every <x-select> on the site fails Lighthouse's "select elements
     * do not have associated label elements" audit. Re-point the 'select' Blade alias to our
     * own subclass (registered once every provider — Mary's included — has booted, so this wins
     * regardless of provider load order) instead of patching the vendor file.
     */
    protected function configureAccessibleSelect(): void
    {
        $this->app->booted(fn () => Blade::component('select', AccessibleSelect::class));
    }

    /**
     * Opt every framework-injected script tag out of Cloudflare Rocket Loader.
     *
     * Rocket Loader (aktif di zone Cloudflare production, tidak di staging) menunda
     * lalu mengeksekusi ulang setiap <script> lewat loader-nya sendiri. Itu merusak
     * urutan boot Livewire/Alpine dan membuat tag `modulepreload` bawaan Laravel
     * tidak pernah terpakai — gejalanya persis beda-perilaku antara staging & production.
     * Atribut `data-cfasync="false"` menyuruh Rocket Loader melewati tag ini.
     */
    protected function configureFrontendAssets(): void
    {
        Vite::useScriptTagAttributes(['data-cfasync' => 'false']);

        Livewire::useScriptTagAttributes(['data-cfasync' => 'false']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
