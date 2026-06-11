<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPosition extends Model
{
    protected $guarded = ['id'];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
