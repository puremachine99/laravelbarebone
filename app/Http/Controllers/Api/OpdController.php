<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpdResource;
use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpdController extends Controller
{
    /**
     * Search OPDs by province name (required keyword), optionally narrowed by
     * regency (kabupaten/kota) and OPD name keywords. Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'provinsi' => ['required', 'string', 'min:1'],
            'kabupaten' => ['nullable', 'string', 'min:1'],
            'keyword' => ['nullable', 'string', 'min:1'],
        ]);

        $opds = Opd::query()
            ->with(['regency.province'])
            ->whereHas('regency.province', function ($query) use ($validated) {
                $query->where('name', 'like', '%'.$validated['provinsi'].'%');
            })
            ->when($validated['kabupaten'] ?? null, function ($query, $kabupaten) {
                $query->whereHas('regency', function ($query) use ($kabupaten) {
                    $query->where('name', 'like', '%'.$kabupaten.'%');
                });
            })
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->orderBy('name')
            ->get();

        return OpdResource::collection($opds);
    }
}
