<?php

namespace App\Support;

/**
 * Ready-made GET commands for the "Coba API (Playground)" tab of /tech/documentation. "expect" is the HTTP status
 * the command should return; the playground stops checking a row once the visitor edits it.
 */
class ApiPlaygroundPresets
{
    /**
     * @var array<string, array<int, array{0: string, 1: string, 2: int}>>
     */
    private const GROUPS = [
        'Jenis' => [
            ['List semua jenis (+ has_location)', '/api/opd-types', 200],
        ],
        'Region' => [
            ['List semua region', '/api/regions', 200],
            ['Region yang punya Unit Kesehatan', '/api/regions?jenis=Unit Kesehatan', 200],
        ],
        'Provinsi' => [
            ['List semua provinsi', '/api/provinces', 200],
            ['Filter keyword (case-insensitive)', '/api/provinces?keyword=jawa', 200],
            ['Provinsi di satu region', '/api/provinces?region_id=1', 200],
            ['Region + jenis', '/api/provinces?region_id=1&jenis=Unit Kesehatan', 200],
        ],
        'Kabupaten / Kota' => [
            ['Kabupaten/kota di satu provinsi (nama)', '/api/regencies?provinsi=Jawa Timur', 200],
            ['+ keyword nama kabupaten', '/api/regencies?provinsi=Jawa Timur&keyword=Malang', 200],
            ['Kabupaten/kota di satu region', '/api/regencies?region_id=1', 200],
            ['Region + provinsi (id)', '/api/regencies?region_id=3&province_id=21', 200],
            ['Region + jenis', '/api/regencies?region_id=1&jenis=Unit Kesehatan', 200],
            ['Provinsi, tanpa entri tingkat provinsi', '/api/regencies?province_id=21&opd_lv=1', 200],
        ],
        'Kementrian (tanpa lokasi)' => [
            ['Semua kementerian, tanpa provinsi/kabupaten', '/api/opds?jenis=Kementrian', 200],
            ['+ keyword nama', '/api/opds?jenis=Kementrian&keyword=Keuangan', 200],
        ],
        'OPD (dropdown)' => [
            ['OPD tingkat provinsi (tanpa kabupaten)', '/api/opds?province_id=21&jenis=OPD', 200],
            ['Semua OPD se-provinsi (semua kabupaten/kota)', '/api/opds?province_id=21&regency_id=all&jenis=OPD', 200],
            ['Unit Kesehatan di satu kabupaten/kota (id)', '/api/opds?regency_id=315&jenis=Unit Kesehatan', 200],
            ['OPD di satu kabupaten/kota (id)', '/api/opds?regency_id=315', 200],
            ['Provinsi saja (nama)', '/api/opds?provinsi=Jawa Timur', 200],
            ['+ kabupaten', '/api/opds?provinsi=Jawa Timur&kabupaten=Malang', 200],
            ['+ keyword nama OPD', '/api/opds?provinsi=Jawa Timur&kabupaten=Malang&keyword=Pendidikan', 200],
            ['Provinsi tidak ada → data kosong', '/api/opds?provinsi=Atlantis', 200],
        ],
        'OPD search (paginasi)' => [
            ['Tanpa parameter (halaman 1, 25 item)', '/api/opds/search', 200],
            ['Free text multi-keyword', '/api/opds/search?q=dinas pendidikan malang', 200],
            ['Filter jenis', '/api/opds/search?jenis=Dinas', 200],
            ['Filter region', '/api/opds/search?region=Region 1', 200],
            ['Filter urusan', '/api/opds/search?urusan=Pendidikan', 200],
            ['Kombinasi q + filter', '/api/opds/search?q=kesehatan&provinsi=Jawa Timur&jenis=Dinas', 200],
            ['Sort jenis menurun', '/api/opds/search?sort=jenis&direction=desc', 200],
            ['Paginasi: 5 per halaman, halaman 2', '/api/opds/search?q=dinas&per_page=5&page=2', 200],
            ['Case-insensitive', '/api/opds/search?provinsi=JAWA TIMUR', 200],
        ],
    ];

    /**
     * @return array<int, array{group: string, label: string, path: string, expect: int}>
     */
    public static function all(): array
    {
        return collect(self::GROUPS)
            ->flatMap(fn (array $rows, string $group) => collect($rows)->map(fn (array $row) => [
                'group' => $group,
                'label' => $row[0],
                'path' => $row[1],
                'expect' => $row[2],
            ]))
            ->values()
            ->all();
    }
}
