<?php

namespace App\Models;

class FeedbackResponse extends TrainHubModel
{
    protected $table = 'feedback_response';
    protected $primaryKey = 'responseID';

    public function question()
    {
        return $this->belongsTo(FeedbackQuestion::class, 'questionID', 'questionID');
    }

    public function participant()
    {
        return $this->belongsTo(CourseParticipant::class, 'participantID', 'participantID');
    }

    public function staff()
    {
        return $this->belongsTo(StaffEdu::class, 'staffID', 'staffID');
    }
}
