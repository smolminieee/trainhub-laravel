<?php

namespace App\Models;

class School extends TrainHubModel
{
    protected $table = 'school';
    protected $primaryKey = 'schoolID';

    public function assignments()
    {
        return $this->hasMany(Assign::class, 'schoolID', 'schoolID');
    }

    public function tarbiahActivities()
    {
        return $this->hasMany(Tarbiah::class, 'schoolID', 'schoolID');
    }
}
