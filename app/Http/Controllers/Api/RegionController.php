<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    /**
     * List the business regions (the Region → Provinsi → Kabupaten/Kota → OPD dropdowns), with how many regencies
     * each covers. With "jenis" (from /api/opd-types), only regions and regencies that have an OPD of that jenis.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'jenis' => ['nullable', 'string', 'max:50'],
        ]);

        $jenis = $validated['jenis'] ?? null;
        $regenciesOfJenis = fn ($regencies) => $regencies->whereHas('opds', fn ($opds) => $opds->ofJenis($jenis)->withLocation());

        $regions = Region::query()
            ->when($jenis, fn ($query) => $query->whereHas('regencies', $regenciesOfJenis))
            ->withCount(['regencies' => fn ($regencies) => $regencies->when($jenis, $regenciesOfJenis)])
            ->orderBy('name')
            ->get();

        return RegionResource::collection($regions);
    }
}
