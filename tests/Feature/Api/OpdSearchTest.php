<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

test('provinsi keyword is required', function () {
    $response = $this->getJson('/api/opds');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('provinsi');
});

test('returns opds matching the given provinsi keyword', function () {
    $jatim = Province::factory()->create(['name' => 'Jawa Timur']);
    $jabar = Province::factory()->create(['name' => 'Jawa Barat']);

    $regencyJatim = Regency::factory()->create(['province_id' => $jatim->id]);
    $regencyJabar = Regency::factory()->create(['province_id' => $jabar->id]);

    $matching = Opd::factory()->create(['regency_id' => $regencyJatim->id, 'name' => 'Dinas Pendidikan']);
    Opd::factory()->create(['regency_id' => $regencyJabar->id, 'name' => 'Dinas Kesehatan']);

    $response = $this->getJson('/api/opds?provinsi=Jawa Timur');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('data.0.provinsi', 'Jawa Timur');
});

test('matches provinsi keyword partially and case-insensitively', function () {
    $province = Province::factory()->create(['name' => 'Jawa Timur']);
    $regency = Regency::factory()->create(['province_id' => $province->id]);
    Opd::factory()->create(['regency_id' => $regency->id]);

    $response = $this->getJson('/api/opds?provinsi=timur');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('further narrows results with optional kabupaten and keyword filters', function () {
    $province = Province::factory()->create(['name' => 'Jawa Timur']);
    $malang = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Malang']);
    $sidoarjo = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Sidoarjo']);

    $matching = Opd::factory()->create(['regency_id' => $malang->id, 'name' => 'Dinas Pendidikan']);
    Opd::factory()->create(['regency_id' => $malang->id, 'name' => 'Dinas Kesehatan']);
    Opd::factory()->create(['regency_id' => $sidoarjo->id, 'name' => 'Dinas Pendidikan']);

    $response = $this->getJson('/api/opds?provinsi=Jawa Timur&kabupaten=Malang&keyword=Pendidikan');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

describe('GET /api/opds/search', function () {
    beforeEach(function () {
        $region = Region::factory()->create(['name' => 'Wilayah Dua']);
        $province = Province::factory()->create(['name' => 'Jawa Timur']);
        $this->malang = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Malang', 'region_id' => $region->id]);
        $this->sidoarjo = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Sidoarjo', 'region_id' => null]);

        $this->dinas = Opd::factory()->create(['regency_id' => $this->malang->id, 'name' => 'Dinas Pendidikan', 'jenis' => 'OPD']);
        $this->kementerian = Opd::factory()->create(['regency_id' => $this->sidoarjo->id, 'name' => 'Kementerian Keuangan', 'jenis' => 'Kementrian']);

        $urusanId = DB::table('pmo_urusans')->insertGetId(['code' => 'U7', 'urusan' => 'Pendidikan Dasar', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pmo_opd_urusan')->insert(['opd_id' => $this->dinas->id, 'urusan_id' => $urusanId]);
    });

    test('works without any parameter and is paginated', function () {
        $this->getJson('/api/opds/search')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 25);
    });

    test('free text q matches every column', function (string $keyword, string $expectedName) {
        $this->getJson('/api/opds/search?q='.urlencode($keyword))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $expectedName);
    })->with([
        'name' => ['keuangan', 'Kementerian Keuangan'],
        'jenis' => ['kementrian', 'Kementerian Keuangan'],
        'regency' => ['malang', 'Dinas Pendidikan'],
        'region' => ['wilayah dua', 'Dinas Pendidikan'],
        'urusan' => ['pendidikan dasar', 'Dinas Pendidikan'],
        'urusan code' => ['u7', 'Dinas Pendidikan'],
        'several keywords in different columns' => ['dinas jawa timur malang', 'Dinas Pendidikan'],
    ]);

    test('returns nothing when keywords do not all match', function () {
        $this->getJson('/api/opds/search?q=dinas+sidoarjo')->assertOk()->assertJsonCount(0, 'data');
    });

    test('per column filters narrow the result and can be combined', function () {
        $this->getJson('/api/opds/search?jenis=kementrian')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->kementerian->id);
        $this->getJson('/api/opds/search?provinsi=timur&kabupaten=sidoarjo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->kementerian->id);
        $this->getJson('/api/opds/search?region=dua')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->dinas->id);
        $this->getJson('/api/opds/search?urusan=pendidikan')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->dinas->id);
        $this->getJson('/api/opds/search?jenis=opd&provinsi=timur')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->dinas->id);
    });

    test('returns the full OPD detail', function () {
        $this->getJson('/api/opds/search?q=dinas')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $this->dinas->id,
                'name' => 'Dinas Pendidikan',
                'jenis' => 'OPD',
                'regency' => 'Kabupaten Malang',
                'provinsi' => 'Jawa Timur',
                'region' => 'Wilayah Dua',
                'urusans' => ['Pendidikan Dasar'],
            ]);
    });

    test('paginates and sorts', function () {
        $this->getJson('/api/opds/search?per_page=1&sort=name&direction=desc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kementerian Keuangan')
            ->assertJsonPath('meta.last_page', 2);
    });

    test('rejects invalid parameters', function () {
        $this->getJson('/api/opds/search?per_page=500&sort=id')->assertStatus(422)->assertJsonValidationErrors(['per_page', 'sort']);
    });

    test('treats percent as a literal character', function () {
        $this->getJson('/api/opds/search?q=%25')->assertOk()->assertJsonCount(0, 'data');
    });
});
