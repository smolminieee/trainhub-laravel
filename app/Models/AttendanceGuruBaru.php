<?php

namespace App\Models;

class AttendanceGuruBaru extends TrainHubModel
{
    protected $table = 'attendance_guru_baru';
    protected $primaryKey = 'attendGuruBaru_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function guruNew()
    {
        return $this->belongsTo(GuruNew::class, 'gn_id', 'gn_id');
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
