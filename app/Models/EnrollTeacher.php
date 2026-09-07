<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;

class EnrollTeacher extends TrainHubModel
{
    use HasCompositePrimaryKey;

    protected $table = 'enroll_teacher';
    protected $primaryKey = ['teacherID', 'course_id'];
    public $timestamps = true;

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacherID', 'teacherID');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseID');
    }
}
