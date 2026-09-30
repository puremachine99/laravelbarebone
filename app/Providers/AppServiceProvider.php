<?php

namespace App\Providers;

use App\Enums\UserStatus;
use App\Models\Contact;
use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\Output;
use App\Models\Urusan;
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

        Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);

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
