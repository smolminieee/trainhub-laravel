<?php

namespace App\Models;

/**
 * Read-only participation link used by TrainHub credit history.
 * A row means the Staff EDU member joined the Tarbiah activity.
 * TrainHub does not provide approval/rejection or remarks actions for this table.
 */
class StaffTarbiahAttendance extends TrainHubModel
{
    protected $table = 'staff_tarbiah_attendance';
    protected $primaryKey = 'staff_tarbiah_attendance_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function tarbiah()
    {
        return $this->belongsTo(Tarbiah::class, 'tarbiah_id', 'tarbiah_id');
    }

    public function staff()
    {
        return $this->belongsTo(StaffEdu::class, 'staffID', 'staffID');
    }
}
