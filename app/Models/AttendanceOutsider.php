<?php

namespace App\Models;

class AttendanceOutsider extends TrainHubModel
{
    protected $table = 'attendance_outsider';
    protected $primaryKey = 'attendOutsider_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function outsider()
    {
        return $this->belongsTo(Outsider::class, 'outsider_id', 'outsider_id');
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
