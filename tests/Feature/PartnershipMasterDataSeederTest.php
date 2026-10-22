<?php

use App\Enums\OpdJenis;
use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Database\Seeders\PartnershipMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedPartnershipMaster(): void
{
    test()->artisan('db:seed', ['--class' => PartnershipMasterDataSeeder::class])
        ->doesntExpectOutputToContain('Wilayah tidak cocok')
        ->assertSuccessful();
}

it('seeds provinces and regencies where every regency has one province and one region', function () {
    seedPartnershipMaster();

    $bantul = Regency::where('name', 'Kab. Bantul')->with(['province', 'region'])->sole();

    expect(Province::count())->toBe(38)
        ->and(Region::whereIn('code', ['REG-1', 'REG-2', 'REG-3', 'REG-4', 'REG-5'])->count())->toBe(5)
        ->and(Regency::whereNull('province_id')->orWhereNull('region_id')->count())->toBe(0)
        ->and($bantul->province->name)->toBe('Prov. DI Yogyakarta')
        ->and($bantul->region->code)->toBe('REG-3')
        ->and(Province::where('name', 'Prov. Kepulauan Bangka Belitung')->sole()->regencies()->count())->toBe(8)
        ->and(Regency::where('name', 'Prov. Jawa Timur')->value('type'))->toBe(Regency::TYPE_PROVINCE);
});

it('seeds Kementerian on a region without any province or regency', function () {
    seedPartnershipMaster();

    $regionThree = Region::where('code', 'REG-3')->sole();

    expect(Opd::kementerian()->count())->toBe(122)
        ->and(Opd::kementerian()->whereNotNull('regency_id')->count())->toBe(0)
        ->and($regionThree->kementerians()->count())->toBe(122)
        ->and($regionThree->kementerians()->where('name', 'Kementerian Keuangan')->exists())->toBeTrue();
});

it('seeds OPD and Unit Kesehatan on their kabupaten/kota', function () {
    seedPartnershipMaster();

    $surabaya = Regency::where('name', 'Kota Surabaya')->sole();
    $jatimLevel = Regency::where('name', 'Prov. Jawa Timur')->sole();

    expect(Opd::whereIn('jenis', [OpdJenis::Opd->value, OpdJenis::UnitKesehatan->value])->whereNull('regency_id')->count())->toBe(0)
        ->and(Opd::whereNotNull('region_id')->where('jenis', '<>', OpdJenis::Kementerian->value)->count())->toBe(0)
        ->and(Opd::unitKesehatan()->count())->toBe(530)
        ->and($surabaya->unitKesehatans()->pluck('name')->sort()->values()->all())->toBe(['RSUD Bhakti Dharma Husada', 'RSUD dr. Soewandhie'])
        ->and($surabaya->pemdaOpds()->count())->toBeGreaterThan(0)
        ->and($jatimLevel->pemdaOpds()->count())->toBe(51)
        ->and($jatimLevel->unitKesehatans()->pluck('name')->all())->toBe(['RSUD Dr. Saiful Anwar']);
});

it('matches kabupaten/kota spelled differently across the CSV files', function () {
    seedPartnershipMaster();

    $sleman = Regency::where('name', 'Kab. Sleman')->sole();
    $kendari = Regency::where('name', 'Kota Kendari')->sole();

    expect($sleman->pemdaOpds()->count())->toBe(30)
        ->and(Regency::where('name', 'Kab. Mukomuko')->sole()->pemdaOpds()->count())->toBe(29)
        ->and(Regency::where('name', 'Kab. Ogan Komering Ulu Selatan')->sole()->unitKesehatans()->count())->toBe(1)
        ->and($kendari->unitKesehatans()->pluck('name')->all())->toBe(['RSUD Abunawas']);
});

it('is idempotent and keeps existing ids', function () {
    seedPartnershipMaster();

    $counts = [Province::count(), Regency::count(), Opd::count()];
    $kemenkeuId = Opd::kementerian()->where('name', 'Kementerian Keuangan')->value('id');

    seedPartnershipMaster();

    expect([Province::count(), Regency::count(), Opd::count()])->toBe($counts)
        ->and(Opd::kementerian()->where('name', 'Kementerian Keuangan')->value('id'))->toBe($kemenkeuId);
});

it('updates existing instansi in place so contacts keep their link', function () {
    $province = Province::factory()->create(['name' => 'Prov. Daerah Istimewa Yogyakarta']);
    $sleman = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kab. Sleman', 'region_id' => null]);
    $opd = Opd::factory()->create(['regency_id' => $sleman->id, 'name' => 'Rsud  Sleman', 'jenis' => null]);

    seedPartnershipMaster();

    expect($province->fresh()->name)->toBe('Prov. DI Yogyakarta')
        ->and($sleman->fresh()->region->code)->toBe('REG-3')
        ->and($opd->fresh()->jenis)->toBe(OpdJenis::UnitKesehatan->value)
        ->and(Opd::where('regency_id', $sleman->id)->whereRaw('LOWER(name) LIKE ?', ['%rsud%sleman%'])->count())->toBe(1);
});

it('moves contacts off the old Kementerian/Lembaga pseudo-province', function () {
    $pseudoProvince = Province::factory()->create(['name' => PartnershipMasterDataSeeder::KEMENTERIAN_PSEUDO_PROVINCE]);
    $pseudoRegency = Regency::factory()->create(['province_id' => $pseudoProvince->id, 'name' => 'Kementerian Keuangan', 'type' => 'Lembaga']);
    $contactId = DB::table('pmo_contacts')->insertGetId([
        'name' => 'Budi', 'sumber' => 'SDS Umum', 'category' => 'Kementerian / Lembaga', 'regency_id' => $pseudoRegency->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    seedPartnershipMaster();

    $contact = DB::table('pmo_contacts')->find($contactId);

    expect($contact->regency_id)->toBeNull()
        ->and(Opd::find($contact->opd_id)->name)->toBe('Kementerian Keuangan')
        ->and(Province::where('name', PartnershipMasterDataSeeder::KEMENTERIAN_PSEUDO_PROVINCE)->exists())->toBeFalse()
        ->and(Regency::find($pseudoRegency->id))->toBeNull();
});

it('matches old Kementerian names loosely and creates the ones missing from the CSV', function () {
    $pseudoProvince = Province::factory()->create(['name' => PartnershipMasterDataSeeder::KEMENTERIAN_PSEUDO_PROVINCE]);
    $contactIds = collect(['Badan KepegawaianNegara', 'DPR RI', 'Lembaga Yang Sudah Bubar'])->mapWithKeys(function (string $name) use ($pseudoProvince) {
        $regency = Regency::factory()->create(['province_id' => $pseudoProvince->id, 'name' => $name, 'type' => 'Lembaga']);

        return [$name => DB::table('pmo_contacts')->insertGetId([
            'name' => "Kontak {$name}", 'sumber' => 'SDS Umum', 'category' => 'Kementerian / Lembaga', 'regency_id' => $regency->id,
            'created_at' => now(), 'updated_at' => now(),
        ])];
    });

    seedPartnershipMaster();

    $opdOf = fn (string $name) => Opd::find(DB::table('pmo_contacts')->where('id', $contactIds[$name])->value('opd_id'));

    expect($opdOf('Badan KepegawaianNegara')->name)->toBe('Badan Kepegawaian Negara (BKN)')
        ->and($opdOf('DPR RI')->name)->toBe('Dewan Perwakilan Rakyat (DPR)')
        ->and($opdOf('Lembaga Yang Sudah Bubar')->jenis)->toBe(OpdJenis::Kementerian->value)
        ->and($opdOf('Lembaga Yang Sudah Bubar')->regency_id)->toBeNull()
        ->and(Opd::kementerian()->count())->toBe(123)
        ->and(DB::table('pmo_contacts')->whereNotNull('regency_id')->count())->toBe(0)
        ->and(Province::where('name', PartnershipMasterDataSeeder::KEMENTERIAN_PSEUDO_PROVINCE)->exists())->toBeFalse();
});

it('keeps Kementerian on a region and every other jenis on a regency when saved', function () {
    $regionThree = Region::factory()->create(['code' => 'REG-3']);
    $regency = Regency::factory()->create();

    $kementerian = Opd::create(['name' => 'Kementerian Uji', 'jenis' => 'kementerian', 'regency_id' => $regency->id]);
    $pendidikan = Opd::create(['name' => 'SMA Uji', 'jenis' => OpdJenis::Pendidikan->value, 'regency_id' => $regency->id, 'region_id' => $regionThree->id]);

    expect($kementerian->jenis)->toBe(OpdJenis::Kementerian->value)
        ->and($kementerian->regency_id)->toBeNull()
        ->and($kementerian->region_id)->toBe($regionThree->id)
        ->and($pendidikan->region_id)->toBeNull()
        ->and($pendidikan->effectiveRegion()->is($regency->region))->toBeTrue()
        ->and($regency->pendidikans()->pluck('id')->all())->toBe([$pendidikan->id])
        ->and(Opd::inRegion($regionThree->id)->pluck('id')->all())->toBe([$kementerian->id])
        ->and(Opd::inRegion($regency->region_id)->pluck('id')->all())->toBe([$pendidikan->id]);
});
