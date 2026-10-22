<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Kementrian is national: it has no regency or province, only a region of its own.
        $hasLocation = $this->resource->hasLocation();
        $regionLoaded = $hasLocation
            ? $this->relationLoaded('regency') && $this->regency?->relationLoaded('region')
            : $this->relationLoaded('region');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'jenis' => $this->jenis,
            'regency' => $this->whenLoaded('regency', fn () => $hasLocation ? $this->regency?->name : null),
            'provinsi' => $this->whenLoaded('regency', fn () => $hasLocation ? $this->regency?->province?->name : null),
            'region' => $this->when($regionLoaded, fn () => $this->resource->effectiveRegion()?->name),
            'urusans' => $this->whenLoaded('urusans', fn () => $this->urusans->pluck('urusan')->values()),
        ];
    }
}
