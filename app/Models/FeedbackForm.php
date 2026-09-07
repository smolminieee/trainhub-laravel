<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class FeedbackForm extends TrainHubModel
{
    protected $table = 'feedback_form';
    protected $primaryKey = 'formID';

    public function categories()
    {
        return $this->hasMany(FeedbackCategory::class, 'formID', 'formID');
    }

    public function questions()
    {
        return $this->hasManyThrough(
            FeedbackQuestion::class,
            FeedbackCategory::class,
            'formID',
            'categoryID',
            'formID',
            'categoryID'
        );
    }

    /**
     * feedback_response is linked to a form through
     * response -> question -> category -> form, so it is not a direct hasMany.
     */
    public function responsesQuery(): Builder
    {
        return FeedbackResponse::query()->whereHas('question.category', function (Builder $query): void {
            $query->where('formID', $this->getAttribute('formID'));
        });
    }
}
