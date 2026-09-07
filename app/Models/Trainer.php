<?php

namespace App\Models;

class Trainer extends TrainHubModel
{
    protected $table = 'trainer';
    protected $primaryKey = 'trainerID';

    public function sessionAssignments()
    {
        return $this->hasMany(SessionTrainer::class, 'trainerID', 'trainerID');
    }

    public function documents()
    {
        return $this->hasMany(TrainerDocument::class, 'trainerID', 'trainerID');
    }
}
