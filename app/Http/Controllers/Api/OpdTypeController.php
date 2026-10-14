<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpdTypeResource;
use App\Models\Opd;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpdTypeController extends Controller
{
    /**
     * List the jenis (OPD, Unit Kesehatan, Kementrian, …) with how many entries each has — the first dropdown, before
     * Region / Provinsi. A jenis with "has_location" false (Kementrian) skips the location dropdowns entirely.
     */
    public function index(): AnonymousResourceCollection
    {
        $types = Opd::query()
            ->whereNotNull('jenis')
            ->selectRaw('jenis as name, count(*) as opds_count')
            ->groupBy('jenis')
            ->orderBy('jenis')
            ->toBase()
            ->get();

        return OpdTypeResource::collection($types);
    }
}
