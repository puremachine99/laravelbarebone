<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpdResource;
use App\Models\Opd;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OpdController extends Controller
{
    /**
     * Search OPDs by province name keyword, or — for cascading dropdowns — by the exact regency id; optionally
     * narrowed by regency (kabupaten/kota) and OPD name keywords. Used to populate dropdowns.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'provinsi' => ['required_without:regency_id', 'nullable', 'string', 'min:1'],
            'regency_id' => ['nullable', 'integer'],
            'kabupaten' => ['nullable', 'string', 'min:1'],
            'keyword' => ['nullable', 'string', 'min:1'],
        ]);

        $opds = Opd::query()
            ->with(['regency.province'])
            ->when($validated['provinsi'] ?? null, function ($query, $provinsi) {
                $query->whereHas('regency.province', function ($query) use ($provinsi) {
                    $query->where('name', 'like', '%'.$provinsi.'%');
                });
            })
            ->when($validated['regency_id'] ?? null, fn ($query, $regencyId) => $query->where('regency_id', $regencyId))
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

    /**
     * Search OPDs by anything: a free-text "q" matched against every column (name, jenis,
     * regency, province, region, urusan), plus optional per-column filters. Paginated.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'jenis' => ['nullable', 'string', 'max:50'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kabupaten' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'urusan' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['name', 'jenis'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $contains = fn (Builder $query, string $column, string $value) => $query
            ->whereRaw("LOWER({$column}) LIKE ?", ['%'.addcslashes(mb_strtolower($value), '%_\\').'%']);

        $opds = Opd::query()
            ->with(['regency.province', 'regency.region', 'urusans'])
            ->search($validated['q'] ?? null)
            ->when($validated['jenis'] ?? null, fn ($query, $jenis) => $query->whereRaw('LOWER(pmo_opds.jenis) = ?', [mb_strtolower($jenis)]))
            ->when($validated['provinsi'] ?? null, fn ($query, $provinsi) => $query->whereHas('regency.province', fn ($province) => $contains($province, 'provinces.name', $provinsi)))
            ->when($validated['kabupaten'] ?? null, fn ($query, $kabupaten) => $query->whereHas('regency', fn ($regency) => $contains($regency, 'regencies.name', $kabupaten)))
            ->when($validated['region'] ?? null, fn ($query, $region) => $query->whereHas('regency.region', fn ($regionQuery) => $contains($regionQuery, 'regions.name', $region)))
            ->when($validated['urusan'] ?? null, fn ($query, $urusan) => $query->whereHas('urusans', fn ($urusanQuery) => $urusanQuery
                ->where(fn ($match) => $contains($match, 'pmo_urusans.urusan', $urusan))
                ->orWhereRaw('LOWER(pmo_urusans.code) = ?', [mb_strtolower($urusan)])))
            ->orderBy('pmo_opds.'.($validated['sort'] ?? 'name'), $validated['direction'] ?? 'asc')
            ->orderBy('pmo_opds.id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return OpdResource::collection($opds);
    }
}
