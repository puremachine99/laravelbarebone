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
        // Kementrian is national: its regency is only a placeholder, so no location is exposed.
        $hasLocation = $this->resource->hasLocation();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'jenis' => $this->jenis,
            'regency' => $this->whenLoaded('regency', fn () => $hasLocation ? $this->regency->name : null),
            'provinsi' => $this->whenLoaded('regency', fn () => $hasLocation ? $this->regency->province->name : null),
            'region' => $this->when($this->relationLoaded('regency') && $this->regency->relationLoaded('region'), fn () => $hasLocation ? $this->regency->region?->name : null),
            'urusans' => $this->whenLoaded('urusans', fn () => $this->urusans->pluck('urusan')->values()),
        ];
    }
}
