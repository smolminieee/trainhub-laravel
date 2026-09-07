<?php

namespace App\Models;

class TarbiahAttendance extends TrainHubModel
{
    protected $table = 'tarbiah_attendance';
    protected $primaryKey = 'tarbiah_attendance_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function tarbiah()
    {
        return $this->belongsTo(Tarbiah::class, 'tarbiah_id', 'tarbiah_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacherID');
    }
}
