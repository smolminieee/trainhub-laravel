<?php

namespace App\Models;

class Course extends TrainHubModel
{
    protected $table = 'course';
    protected $primaryKey = 'courseID';

    public function sessions()
    {
        return $this->hasMany(CourseSession::class, 'courseID', 'courseID');
    }

    public function participants()
    {
        return $this->hasMany(CourseParticipant::class, 'courseID', 'courseID');
    }
}
