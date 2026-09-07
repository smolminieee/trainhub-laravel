<?php

namespace App\Models;

class EnrollGuruBaru extends TrainHubModel
{
    protected $table = 'enroll_guru_baru';
    protected $primaryKey = 'enrollment_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public function guruNew()
    {
        return $this->belongsTo(GuruNew::class, 'gn_id', 'gn_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseID');
    }
}
