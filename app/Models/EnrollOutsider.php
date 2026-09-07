<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;

class EnrollOutsider extends TrainHubModel
{
    use HasCompositePrimaryKey;

    protected $table = 'enroll_outsider';
    protected $primaryKey = ['outsider_id', 'course_id'];

    public function outsider()
    {
        return $this->belongsTo(Outsider::class, 'outsider_id', 'outsider_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseID');
    }
}
