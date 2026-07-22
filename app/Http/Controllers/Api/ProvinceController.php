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
     * List provinces, optionally filtered by a name keyword. Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'min:1'],
        ]);

        $provinces = Province::query()
            ->when($validated['keyword'] ?? null, function ($query, $keyword) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%']);
            })
            ->orderBy('name')
            ->get();

        return ProvinceResource::collection($provinces);
    }
}
