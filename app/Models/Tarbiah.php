<?php

namespace App\Models;

class Tarbiah extends TrainHubModel
{
    protected $table = 'tarbiah';
    protected $primaryKey = 'tarbiah_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolID', 'schoolID');
    }

    public function attendances()
    {
        return $this->hasMany(TarbiahAttendance::class, 'tarbiah_id', 'tarbiah_id');
    }

    public function staffAttendances()
    {
        return $this->hasMany(StaffTarbiahAttendance::class, 'tarbiah_id', 'tarbiah_id');
    }
}
