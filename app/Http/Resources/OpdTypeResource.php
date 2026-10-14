<?php

namespace App\Http\Resources;

use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpdTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{name: string, has_location: bool, opds_count: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'has_location' => Opd::jenisHasLocation($this->name),
            'opds_count' => (int) $this->opds_count,
        ];
    }
}
