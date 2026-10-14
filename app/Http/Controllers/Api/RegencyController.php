<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegencyResource;
use App\Models\Regency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegencyController extends Controller
{
    /**
     * Search regencies (kabupaten/kota) by province name keyword, or — for cascading dropdowns — by region and/or
     * province id. At least one of provinsi, province_id or region_id is required. "jenis" keeps only regencies with
     * an OPD of that jenis (none for Kementrian, which has no location). "opd_lv" (kabupaten/kota level only) drops the per-province
     * "Provinsi" placeholder regencies, whose OPDs a dropdown already shows once a province is picked.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'provinsi' => ['required_without_all:province_id,region_id', 'nullable', 'string', 'min:1'],
            'province_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'min:1'],
            'jenis' => ['nullable', 'string', 'max:50'],
            'opd_lv' => ['nullable', 'boolean'],
        ]);

        $regencies = Regency::query()
            ->with(['province', 'region'])
            ->when($validated['provinsi'] ?? null, function ($query, $provinsi) {
                $query->whereHas('province', function ($query) use ($provinsi) {
                    $query->where('name', 'like', '%'.$provinsi.'%');
                });
            })
            ->when($validated['province_id'] ?? null, fn ($query, $provinceId) => $query->where('province_id', $provinceId))
            ->when($validated['region_id'] ?? null, fn ($query, $regionId) => $query->where('region_id', $regionId))
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->when($validated['jenis'] ?? null, fn ($query, $jenis) => $query->whereHas('opds', fn ($opds) => $opds->ofJenis($jenis)->withLocation()))
            ->when($request->boolean('opd_lv'), fn ($query) => $query->where(fn ($type) => $type->whereNull('type')->orWhere('type', '!=', Regency::TYPE_PROVINCE)))
            ->orderBy('name')
            ->get();

        return RegencyResource::collection($regencies);
    }
}
