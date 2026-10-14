<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinceResource;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProvinceController extends Controller
{
    /**
     * List provinces, optionally filtered by a name keyword or by region (provinces with at least one
     * kabupaten/kota in that region — regions are assigned per regency) and by jenis (provinces with an OPD of that
     * jenis; none for Kementrian, which has no location). Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'min:1'],
            'region_id' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'string', 'max:50'],
        ]);

        $provinces = Province::query()
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%']);
            })
            ->when($validated['region_id'] ?? null, function ($query, $regionId) {
                $query->whereHas('regencies', fn ($regencies) => $regencies->where('region_id', $regionId));
            })
            ->when($validated['jenis'] ?? null, function ($query, $jenis) use ($validated) {
                $query->whereHas('regencies', fn ($regencies) => $regencies
                    ->when($validated['region_id'] ?? null, fn ($regencies, $regionId) => $regencies->where('region_id', $regionId))
                    ->whereHas('opds', fn ($opds) => $opds->ofJenis($jenis)->withLocation()));
            })
            ->orderBy('name')
            ->get();

        return ProvinceResource::collection($provinces);
    }
}
