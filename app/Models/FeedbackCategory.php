<?php

namespace App\Models;

class FeedbackCategory extends TrainHubModel
{
    protected $table = 'feedback_category';
    protected $primaryKey = 'categoryID';

    public function form()
    {
        return $this->belongsTo(FeedbackForm::class, 'formID', 'formID');
    }

    public function questions()
    {
        return $this->hasMany(FeedbackQuestion::class, 'categoryID', 'categoryID');
    }
}
