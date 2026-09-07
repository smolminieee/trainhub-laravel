<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class TrainHubModel extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
}
