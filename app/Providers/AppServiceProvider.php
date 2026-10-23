<?php

namespace App\Providers;

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Observers\EmployeeObserver;
use App\Support\CsvExport\CsvExportDefinition;
use App\Support\CsvExport\CsvExportRegistry;
use App\View\Components\AccessibleSelect;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::before(function ($user, $ability) {
            if ($user->isSuspended()) {
                return false;
            }

            return $user->hasRole('Super Admin') ? true : null;
        });

        Employee::observe(EmployeeObserver::class);

        $this->configureAccessibleSelect();

        $this->registerCsvExports();
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
     * Register the master-data pages that can export their listing to CSV.
     *
     * Adding a new exportable page only requires one more CsvExportRegistry::register()
     * call here — the navbar button and the download route both read from this registry.
     */
    protected function registerCsvExports(): void
    {
        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'employees',
            routeName: 'employees.index',
            label: 'Export Karyawan',
            ability: 'employees.export',
            headers: ['Nama', 'Email', 'Departemen', 'Jabatan Struktural', 'Jabatan Fungsional', 'Jabatan Teknis', 'Status Akun', 'Status Kepegawaian', 'Roles'],
            query: fn () => User::query()
                ->with(['employee.department', 'employee.positionStruktural', 'employee.positionFungsional', 'employee.positionTeknis', 'roles']),
            mapRow: fn (User $user) => [
                $user->name,
                $user->email,
                $user->employee?->department?->name,
                $user->employee?->positionStruktural?->name,
                $user->employee?->positionFungsional?->name,
                $user->employee?->positionTeknis?->name,
                match ($user->status) {
                    UserStatus::Active => 'Aktif',
                    UserStatus::Invited => 'Menunggu Aktivasi',
                    UserStatus::Suspended => 'Dinonaktifkan',
                    default => '-',
                },
                $user->employee?->employment_status,
                $user->roles->pluck('name')->implode(', '),
            ],
        ));
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
