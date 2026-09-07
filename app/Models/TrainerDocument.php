<?php

namespace App\Models;

class TrainerDocument extends TrainHubModel
{
    protected $table = 'trainer_document';
    protected $primaryKey = 'document_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    public function trainer()
    {
        return $this->belongsTo(Trainer::class, 'trainerID', 'trainerID');
    }

    public function sender()
    {
        return $this->belongsTo(StaffEdu::class, 'sentByStaffID', 'staffID');
    }
}
