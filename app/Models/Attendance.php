<?php

namespace App\Models;

class Attendance extends TrainHubModel
{
    protected $table = 'attendance';
    protected $primaryKey = 'attendance_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacherID');
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'session_id', 'sessionID');
    }

    public function approver()
    {
        return $this->belongsTo(StaffEdu::class, 'approvedByStaff', 'staffID');
    }
}
