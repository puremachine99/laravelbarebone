<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    /**
     * List the business regions (first level of the Region → Provinsi → Kabupaten/Kota → OPD dropdowns), with how
     * many regencies each covers.
     */
    public function index(): AnonymousResourceCollection
    {
        $regions = Region::query()
            ->withCount('regencies')
            ->orderBy('name')
            ->get();

        return RegionResource::collection($regions);
    }
}
