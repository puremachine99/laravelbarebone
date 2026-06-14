<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Output extends Model
{
    protected $fillable = [
        'division_id',
        'service_center_id',
        'category_code',
        'output_name',
        'pelaksana',
        'status',
        'sequence',
        'generated_code',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sequence' => 'integer',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function serviceCenter()
    {
        return $this->belongsTo(ServiceCenter::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_code', 'code');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $model) {
            // Re-load relationships if needed to get codes
            $division = $model->division ?? Division::find($model->division_id);
            $serviceCenter = $model->serviceCenter ?? ServiceCenter::find($model->service_center_id);

            if ($division && $serviceCenter) {
                // Check if sequence needs to be assigned or recalculated
                $isNewOrChanged = ! $model->exists ||
                    $model->isDirty('division_id') ||
                    $model->isDirty('service_center_id') ||
                    $model->isDirty('category_code');

                if ($isNewOrChanged) {
                    $existing = self::where('division_id', $model->division_id)
                        ->where('service_center_id', $model->service_center_id)
                        ->where('category_code', $model->category_code)
                        ->when($model->id, fn ($q) => $q->where('id', '!=', $model->id))
                        ->pluck('sequence')
                        ->toArray();

                    $seq = 1;
                    while (in_array($seq, $existing)) {
                        $seq++;
                    }

                    $model->sequence = $seq;
                }

                // Format: [DivisiCode].[LayananCode].[KategoriCode].[Urutan]
                // e.g. 1.1.A.2
                $model->generated_code = sprintf(
                    '%s.%s.%s.%d',
                    $division->code,
                    $serviceCenter->code,
                    $model->category_code,
                    $model->sequence
                );
            }
        });
    }
}
