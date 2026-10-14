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
     * kabupaten/kota in that region — regions are assigned per regency). Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'min:1'],
            'region_id' => ['nullable', 'integer'],
        ]);

        $provinces = Province::query()
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%']);
            })
            ->when($validated['region_id'] ?? null, function ($query, $regionId) {
                $query->whereHas('regencies', fn ($regencies) => $regencies->where('region_id', $regionId));
            })
            ->orderBy('name')
            ->get();

        return ProvinceResource::collection($provinces);
    }
}
