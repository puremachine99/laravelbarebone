<?php

namespace App\Providers;

use App\Enums\UserStatus;
use App\Models\Contact;
use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\OAuthClient;
use App\Models\Opd;
use App\Models\Output;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\Urusan;
use App\Models\User;
use App\Observers\EmployeeObserver;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\FakeAiGateway;
use App\Services\AiCenter\NakamaAiGateway;
use App\Support\CsvExport\CsvExportDefinition;
use App\Support\CsvExport\CsvExportRegistry;
use App\View\Components\AccessibleSelect;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Must be set before Passport registers its routes. Connected apps only use the authorization-code grant.
        Passport::$deviceCodeGrantEnabled = false;

        $this->app->bind(AiGateway::class, fn () => match (config('ai_center.driver')) {
            'fake' => new FakeAiGateway(delayMs: $this->app->runningUnitTests() ? 0 : 25),
            'nakama' => new NakamaAiGateway,
            default => throw new InvalidArgumentException('AI Center driver ['.config('ai_center.driver').'] is not available yet.'),
        });
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

        // Public OPD lookup API (no token): generous per-IP limit so normal dropdown use never hits it, while scraping
        // or a runaway client does.
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by('public-api:'.$request->ip()));

        // Data API queries the AI service runs for a user (path B), counted per asserted user.
        RateLimiter::for('ai-data', fn (Request $request) => Limit::perHour(config('ai_center.data.queries_per_hour'))
            ->by('ai-data-api:'.$request->user()?->id));

        $this->configurePassport();

        $this->configureAccessibleSelect();

        $this->registerCsvExports();
    }

    /**
     * SSO for other internal apps ("Login with SmartID"): authorization-code grant only, clients managed from
     * /tech/sso. Access tokens are short-lived; the connected app keeps its own session after login.
     */
    protected function configurePassport(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::tokensCan(OAuthClient::SCOPES);
        Passport::defaultScopes(['profile']);
        Passport::tokensExpireIn(now()->addHours(8));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::authorizationView('pages::auth.oauth-authorize');
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
            slug: 'opd',
            routeName: 'opd.index',
            label: 'Export OPD',
            ability: 'access-partnership',
            headers: ['Nama Instansi / OPD', 'Kabupaten / Kota', 'Provinsi', 'Bidang Urusan'],
            query: fn () => Opd::query()->with(['regency.province', 'urusans']),
            mapRow: fn (Opd $opd) => [
                $opd->name,
                $opd->regency?->name,
                $opd->regency?->province?->name,
                $opd->urusans->pluck('urusan')->implode(', '),
            ],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'contacts',
            routeName: 'contact.index',
            label: 'Export Kontak',
            ability: 'access-partnership',
            headers: ['Nama Kontak', 'Sumber', 'Region', 'Kabupaten/Kota', 'Instansi', 'Kategori', 'Jabatan', 'No HP', 'Email', 'PIC'],
            query: function () {
                $user = auth()->user();
                $employee = $user->employee;
                $isSuper = $user->email === 'supernakama@smartid.co.id' || $user->hasRole('Super Admin');
                $isSpv = ! $isSuper && $user->hasRole('Partnership Admin');

                return Contact::query()
                    ->with(['region', 'regency.province', 'opd', 'pic.user', 'contactPosition'])
                    ->when($isSpv && $employee?->region_id, fn ($q) => $q->where('pmo_contacts.region_id', $employee->region_id))
                    ->when(! $isSuper && ! $isSpv && $employee, fn ($q) => $q->where('pmo_contacts.pic_employee_id', $employee->id));
            },
            mapRow: fn (Contact $contact) => [
                $contact->name,
                $contact->sumber,
                $contact->region?->name,
                $contact->regency?->name,
                $contact->opd?->name,
                $contact->category,
                $contact->contactPosition?->name,
                $contact->phone,
                $contact->email,
                $contact->pic?->user?->name,
            ],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'contact-positions',
            routeName: 'contact-positions.index',
            label: 'Export Jabatan Kontak',
            ability: 'access-partnership',
            headers: ['Nomenklatur Jabatan', 'Kelompok Jabatan', 'Jenjang / Eselon', 'Tingkat / Keterangan'],
            query: fn () => ContactPosition::query(),
            mapRow: fn (ContactPosition $position) => [
                $position->name,
                $position->kelompok_jabatan,
                $position->eselon,
                $position->tingkat,
            ],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'urusan',
            routeName: 'urusan.index',
            label: 'Export Urusan',
            ability: 'access-partnership',
            headers: ['Kode Urusan', 'Nama Bidang Urusan'],
            query: fn () => Urusan::query(),
            mapRow: fn (Urusan $urusan) => [$urusan->code, $urusan->urusan],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'products',
            routeName: 'products.index',
            label: 'Export Produk',
            ability: 'access-products',
            headers: ['Kode Output', 'Nama Output / Produk', 'Divisi', 'Pusat Layanan', 'Pelaksana', 'Status'],
            query: fn () => Output::query()->with(['division', 'serviceCenter']),
            mapRow: fn (Output $output) => [
                $output->generated_code,
                $output->output_name,
                $output->division?->name,
                $output->serviceCenter?->name,
                $output->pelaksana,
                $output->status ? 'Aktif' : 'Non Aktif',
            ],
        ));

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

        $this->registerRegionCsvExports();
    }

    /**
     * The /region page: one "export all" (navbar button, matched by route name) plus one partial export per tab.
     * The partial ones use a routeName that is not a real route, so only the in-tab buttons reference them.
     */
    protected function registerRegionCsvExports(): void
    {
        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'region',
            routeName: 'region.index',
            label: 'Export Semua Data Lokasi',
            ability: 'region.view',
            headers: ['Region', 'Provinsi', 'Kabupaten / Kota', 'Tipe', 'Kode'],
            query: fn () => Regency::query()->with(['province', 'region']),
            mapRow: fn (Regency $regency) => [
                $regency->region?->name,
                $regency->province?->name,
                $regency->name,
                $regency->type,
                $regency->code,
            ],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'region-regions',
            routeName: 'region.index#regions',
            label: 'Export Region',
            ability: 'region.view',
            headers: ['Kode', 'Nama Region', 'Jumlah Kabupaten / Kota'],
            query: fn () => Region::query()->withCount(['regencies as kabkota_count' => fn ($query) => $query->where('type', '!=', 'Provinsi')]),
            mapRow: fn (Region $region) => [$region->code, $region->name, $region->kabkota_count],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'region-provinces',
            routeName: 'region.index#provinces',
            label: 'Export Provinsi',
            ability: 'region.view',
            headers: ['Kode', 'Nama Provinsi', 'Region', 'Jumlah Kabupaten / Kota'],
            query: fn () => Province::query()
                ->with('regencies.region')
                ->withCount(['regencies as kabkota_count' => fn ($query) => $query->where('type', '!=', 'Provinsi')]),
            mapRow: fn (Province $province) => [
                $province->code,
                $province->name,
                $province->regencies->pluck('region.name')->filter()->unique()->sort()->implode(', '),
                $province->kabkota_count,
            ],
        ));

        CsvExportRegistry::register(new CsvExportDefinition(
            slug: 'region-regencies',
            routeName: 'region.index#regencies',
            label: 'Export Kabupaten / Kota',
            ability: 'region.view',
            headers: ['Kode', 'Nama', 'Tipe', 'Provinsi', 'Region'],
            query: fn () => Regency::query()->with(['province', 'region']),
            mapRow: fn (Regency $regency) => [
                $regency->code,
                $regency->name,
                $regency->type,
                $regency->province?->name,
                $regency->region?->name,
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
