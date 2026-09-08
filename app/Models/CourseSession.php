<?php

namespace App\Models;

class CourseSession extends TrainHubModel
{
    protected $table = 'course_session';
    protected $primaryKey = 'sessionID';
    public $timestamps = false;

    public function course()
    {
        return $this->belongsTo(Course::class, 'courseID', 'courseID');
    }

    public function trainerAssignments()
    {
        return $this->hasMany(SessionTrainer::class, 'sessionID', 'sessionID');
    }
}
