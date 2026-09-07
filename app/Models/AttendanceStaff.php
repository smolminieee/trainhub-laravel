<?php

namespace App\Models;

class AttendanceStaff extends TrainHubModel
{
    protected $table = 'attendance_staff';
    protected $primaryKey = 'attendanceStaff_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;
}
