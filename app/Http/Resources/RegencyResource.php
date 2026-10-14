<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegencyResource extends JsonResource
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
            'type' => $this->type,
            'province_id' => $this->province_id,
            'provinsi' => $this->whenLoaded('province', fn () => $this->province->name),
            'region_id' => $this->region_id,
            'region' => $this->whenLoaded('region', fn () => $this->region?->name),
        ];
    }
}
