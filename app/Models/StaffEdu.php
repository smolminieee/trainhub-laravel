<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class StaffEdu extends Authenticatable
{
    protected $table = 'staff_edu';
    protected $primaryKey = 'staffID';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['password'];

    public function tarbiahAttendances()
    {
        return $this->hasMany(StaffTarbiahAttendance::class, 'staffID', 'staffID');
    }

    public function getAuthIdentifierName(): string
    {
        return 'staffID';
    }
}
