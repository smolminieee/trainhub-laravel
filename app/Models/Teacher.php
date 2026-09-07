<?php

namespace App\Models;

class Teacher extends TrainHubModel
{
    protected $table = 'teacher';
    protected $primaryKey = 'teacherID';

    public function assignments()
    {
        return $this->hasMany(Assign::class, 'teacherID', 'teacherID');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'teacher_id', 'teacherID');
    }
}
