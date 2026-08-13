<?php

namespace App\Models;

use App\Enums\ActivityLogAction;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'action' => ActivityLogAction::class,
        'changes' => 'array',
    ];

    public function loggable()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * Satu-satunya titik masuk penulisan log biar bentuk JSON-nya konsisten di semua modul:
     * $before/$after cuma diisi kolom yang relevan (bukan seluruh model), jadi payload-nya
     * ringkas dan gampang dirender. $before null saat create, $after null saat delete.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(Model $loggable, ActivityLogAction $action, ?array $before = null, ?array $after = null): self
    {
        return self::create([
            'loggable_type' => $loggable->getMorphClass(),
            'loggable_id' => $loggable->getKey(),
            'causer_id' => auth()->id(),
            'owner_employee_id' => $loggable->getAttribute('employee_id'),
            'action' => $action,
            'changes' => array_filter([
                'before' => $before,
                'after' => $after,
            ], fn ($value) => $value !== null),
        ]);
    }
}
