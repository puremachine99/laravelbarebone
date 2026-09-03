<?php

namespace App\Providers;

use App\Models\Employee;
use App\Observers\EmployeeObserver;
use Carbon\CarbonImmutable;
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
