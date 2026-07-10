<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $table = 'pmo_product_categories';

    protected $fillable = ['code', 'name'];
}
