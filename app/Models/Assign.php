<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;

class Assign extends TrainHubModel
{
    use HasCompositePrimaryKey;

    protected $table = 'assign';
    protected $primaryKey = ['schoolID', 'teacherID'];

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolID', 'schoolID');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacherID', 'teacherID');
    }
}
