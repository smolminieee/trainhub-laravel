<?php

namespace App\Models;

class Certificate extends TrainHubModel
{
    protected $table = 'certificate';
    protected $primaryKey = 'certificateID';

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'templateID', 'templateID');
    }
}
