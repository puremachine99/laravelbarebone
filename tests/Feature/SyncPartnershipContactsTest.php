<?php

use App\Actions\PartnershipContactTransformer;
use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('phone sanitization formatting', function () {
    $transformer = new PartnershipContactTransformer;

    expect($transformer->sanitizePhone('0812-3456-7890'))->toBe('+6281234567890');
    expect($transformer->sanitizePhone('812 3456 7890'))->toBe('+6281234567890');
    expect($transformer->sanitizePhone('+6281234567890'))->toBe('+6281234567890');
    expect($transformer->sanitizePhone('6281234567890'))->toBe('+6281234567890');
    expect($transformer->sanitizePhone(''))->toBeNull();
    expect($transformer->sanitizePhone(null))->toBeNull();
});

test('category mapping logic', function () {
    $transformer = new PartnershipContactTransformer;

    expect($transformer->mapCategory('Dinas Kesehatan Malang'))->toBe('Pemerintah Daerah');
    expect($transformer->mapCategory('Pemda Probolinggo'))->toBe('Pemerintah Daerah');
    expect($transformer->mapCategory('Kementerian Pertahanan'))->toBe('Kementerian / Lembaga');
    expect($transformer->mapCategory('Kemenkeu RI'))->toBe('Kementerian / Lembaga');
    expect($transformer->mapCategory('PT Maju Bersama (Swasta)'))->toBe('Non Pemerintah');
    expect($transformer->mapCategory(null))->toBe('Non Pemerintah');
});

test('source mapping logic', function () {
    $transformer = new PartnershipContactTransformer;

    expect($transformer->mapSource('Visit dari Klien'))->toBe('Visit/ Pitching');
    expect($transformer->mapSource('Medsos Instagram'))->toBe('Media Sosial Instansi');
    expect($transformer->mapSource('Rekomendasi internal'))->toBe('Referal');
    expect($transformer->mapSource('Random Source'))->toBe('SDS Umum');
});

test('geography mapping matches names to database IDs', function () {
    $transformer = new PartnershipContactTransformer;

    $region = Region::create([
        'name' => 'Region Jawa',
        'code' => 'REG-JAWA',
    ]);
    $province = Province::create([
        'name' => 'JAWA TIMUR',
    ]);
    $regency = Regency::create([
        'province_id' => $province->id,
        'region_id' => $region->id,
        'name' => 'KABUPATEN MALANG',
        'code' => '3507',
    ]);

    // Match regency code & region code
    $geo1 = $transformer->mapGeography('JAWA TIMUR', '3507', 'REG-JAWA');
    expect($geo1['regency_id'])->toBe($regency->id)
        ->and($geo1['region_id'])->toBe($region->id);

    // Match regency code & region id
    $geo2 = $transformer->mapGeography(null, '3507', (string) $region->id);
    expect($geo2['regency_id'])->toBe($regency->id)
        ->and($geo2['region_id'])->toBe($region->id);

    // Cleaned/Fuzzy Fallback Match
    $geo3 = $transformer->mapGeography('Provinsi Jawa Timur', 'Malang', null);
    expect($geo3['regency_id'])->toBe($regency->id)
        ->and($geo3['region_id'])->toBe($region->id);
});

test('pic matching finds employee user', function () {
    $transformer = new PartnershipContactTransformer;

    $user = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@smartid.co.id',
        'password' => bcrypt('password'),
    ]);
    $employee = Employee::create([
        'user_id' => $user->id,
    ]);

    $picId = $transformer->mapPic('Budi Santoso');
    expect($picId)->toBe($employee->id);

    $picIdFuzzy = $transformer->mapPic('Budi');
    expect($picIdFuzzy)->toBe($employee->id);

    $picIdNull = $transformer->mapPic('Seseorang Yang Tidak Ada');
    expect($picIdNull)->toBeNull();
});

test('position matching finds contact position id', function () {
    $transformer = new PartnershipContactTransformer;

    $position = ContactPosition::create([
        'name' => 'Kepala Dinas Kabupaten/Kota',
        'kelompok_jabatan' => 'JA',
        'eselon' => 'III.a',
        'tingkat' => 'Kab/Kota',
    ]);

    $sekPos = ContactPosition::create([
        'name' => 'Sekretaris Dinas/Badan',
        'kelompok_jabatan' => 'JA',
        'eselon' => 'III.a',
        'tingkat' => 'Daerah',
    ]);

    $posId = $transformer->mapPosition('Kepala Dinas Kabupaten/Kota');
    expect($posId)->toBe($position->id);

    $posIdFuzzy = $transformer->mapPosition('Kepala Dinas');
    expect($posIdFuzzy)->toBe($position->id);

    $posIdNull = $transformer->mapPosition('Jabatan Aneh');
    expect($posIdNull)->toBeNull();

    // CSV translation tests
    // Kadis -> Kepala Dinas -> matches "Kepala Dinas Kabupaten/Kota" (fuzzy)
    $posIdKadis = $transformer->mapPosition('Kadis');
    expect($posIdKadis)->toBe($position->id);

    // Sekdis -> Sekretaris -> matches "Sekretaris Dinas/Badan" (fuzzy)
    $posIdSekdis = $transformer->mapPosition('Sekdis');
    expect($posIdSekdis)->toBe($sekPos->id);

    // Pensiun -> null
    $posIdPensiun = $transformer->mapPosition('Pensiun');
    expect($posIdPensiun)->toBeNull();
});
