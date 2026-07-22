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
     * Search regencies (kabupaten/kota) by required province name keyword. Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'provinsi' => ['required', 'string', 'min:1'],
            'keyword' => ['nullable', 'string', 'min:1'],
        ]);

        $regencies = Regency::query()
            ->with(['province'])
            ->whereHas('province', function ($query) use ($validated) {
                $query->where('name', 'like', '%'.$validated['provinsi'].'%');
            })
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->orderBy('name')
            ->get();

        return RegencyResource::collection($regencies);
    }
}
