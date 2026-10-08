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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'jenis' => $this->jenis,
            'regency' => $this->whenLoaded('regency', fn () => $this->regency->name),
            'provinsi' => $this->whenLoaded('regency', fn () => $this->regency->province->name),
            'region' => $this->when($this->relationLoaded('regency') && $this->regency->relationLoaded('region'), fn () => $this->regency->region?->name),
            'urusans' => $this->whenLoaded('urusans', fn () => $this->urusans->pluck('urusan')->values()),
        ];
    }
}
