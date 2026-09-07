<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;

class SessionTrainer extends TrainHubModel
{
    use HasCompositePrimaryKey;

    protected $table = 'session_trainer';
    protected $primaryKey = ['trainerID', 'sessionID'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class, 'trainerID', 'trainerID');
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'sessionID', 'sessionID');
    }
}
