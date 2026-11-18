<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SizeCurveItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['size_curve_id', 'size', 'quantity'];
}
