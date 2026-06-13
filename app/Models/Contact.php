<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function contactPosition()
    {
        return $this->belongsTo(ContactPosition::class);
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class);
    }

    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }

    public function pic()
    {
        return $this->belongsTo(Employee::class, 'pic_employee_id');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function getIsQlAttribute(): bool
    {
        return $this->cards()
            ->whereHas('stage.profile', function ($q) {
                $q->where('pipeline_name', 'Pitching');
            })
            ->where('nominal_value', '>', 0)
            ->exists();
    }
}
