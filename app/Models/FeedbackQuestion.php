<?php

namespace App\Models;

class FeedbackQuestion extends TrainHubModel
{
    protected $table = 'feedback_question';
    protected $primaryKey = 'questionID';

    public function category()
    {
        return $this->belongsTo(FeedbackCategory::class, 'categoryID', 'categoryID');
    }

    public function responses()
    {
        return $this->hasMany(FeedbackResponse::class, 'questionID', 'questionID');
    }
}
