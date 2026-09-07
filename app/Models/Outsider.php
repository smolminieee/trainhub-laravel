<?php

namespace App\Models;

class Outsider extends TrainHubModel
{
    protected $table = 'outsider';
    protected $primaryKey = 'outsider_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function enrollments()
    {
        return $this->hasMany(EnrollOutsider::class, 'outsider_id', 'outsider_id');
    }

    public function attendances()
    {
        return $this->hasMany(AttendanceOutsider::class, 'outsider_id', 'outsider_id');
    }
}
