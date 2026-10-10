<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Directory-level employee data only. Personal/sensitive columns (NIK, NPWP, birth date, phone, marital status,
 * KPI/target figures) are deliberately not exposed.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'username' => $this->user?->username,
            'gender' => $this->gender,
            'department' => $this->department?->name,
            'region' => $this->region?->name,
            'position_struktural' => $this->positionStruktural?->name,
            'position_fungsional' => $this->positionFungsional?->name,
            'position_teknis' => $this->positionTeknis?->name,
            'employment_status' => $this->employment_status,
            'is_cltp' => $this->is_cltp,
            'contract_start_date' => $this->contract_start_date?->toDateString(),
            'contract_end_date' => $this->contract_end_date?->toDateString(),
            'status' => $this->status?->value,
        ];
    }
}
