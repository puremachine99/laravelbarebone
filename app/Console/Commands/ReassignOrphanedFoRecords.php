<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('fo:reassign-orphans {email : Email akun karyawan tujuan} {--from-email= : Pindahkan dari akun karyawan lama ini (bukan yatim NULL)} {--from= : Batas awal created_at (Y-m-d)} {--until= : Batas akhir created_at (Y-m-d, inklusif)} {--execute : Simpan perubahan (default: dry-run)}')]
#[Description('Pasang ulang kontak/funnel/aktivitas FO yatim (employee_id NULL akibat akun terhapus) ke karyawan tujuan')]
class ReassignOrphanedFoRecords extends Command
{
    public function handle(): int
    {
        $employee = Employee::whereHas('user', fn ($q) => $q->where('email', $this->argument('email')))->first();

        if (! $employee) {
            $this->error('Karyawan dengan email tersebut tidak ditemukan (atau belum punya profil karyawan).');

            return self::FAILURE;
        }

        $source = null;

        if ($this->option('from-email')) {
            $source = Employee::withTrashed()->whereHas('user', fn ($q) => $q->withTrashed()->where('email', $this->option('from-email')))->first();

            if (! $source || $source->is($employee)) {
                $this->error('Karyawan lama tidak ditemukan, atau sama dengan karyawan tujuan.');

                return self::FAILURE;
            }
        } elseif (! $this->option('from') || ! $this->option('until')) {
            $this->error('Tanpa --from-email, opsi --from dan --until wajib diisi supaya data yatim milik orang lain tidak ikut terpasang.');

            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('from') ?: '1970-01-01')->startOfDay();
        $until = Carbon::parse($this->option('until') ?: 'now')->endOfDay();

        $scope = fn (Builder $query) => $query->withTrashed()
            ->when($source, fn ($q) => $q->where('employee_id', $source->id), fn ($q) => $q->whereNull('employee_id'))
            ->whereBetween('created_at', [$from, $until]);

        $contacts = $scope(FoContact::query());
        $funnels = $scope(FoFunnel::query());
        $activities = $scope(FoFunnelActivity::query());

        $this->info("Tujuan: {$employee->user->name} (employee #{$employee->id})");
        $this->info("Rentang created_at: {$from->toDateTimeString()} s/d {$until->toDateTimeString()}");
        $this->table(['Tabel', 'Jumlah yatim'], [
            ['fo_contacts', $contacts->count()],
            ['fo_funnels', $funnels->count()],
            ['fo_funnel_activities', $activities->count()],
        ]);
        $this->table(
            ['ID', 'Nama', 'Dibuat'],
            $scope(FoContact::query())->orderBy('created_at')->get(['id', 'nama', 'created_at'])->map(fn ($c) => [$c->id, $c->nama, $c->created_at])->all(),
        );

        if (! $this->option('execute')) {
            $this->warn('Dry-run: tidak ada yang diubah. Tambahkan --execute setelah daftar di atas dipastikan benar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($contacts, $funnels, $activities, $employee) {
            $contacts->update(['employee_id' => $employee->id]);
            $funnels->update(['employee_id' => $employee->id]);
            $activities->update(['employee_id' => $employee->id]);
        });

        $this->info('Selesai: data yatim sudah dipasang ke karyawan tujuan.');

        return self::SUCCESS;
    }
}
