<?php

namespace App\Models;

class AuditLog extends TrainHubModel
{
    protected $table = 'audit_log';
    protected $primaryKey = 'logID';
    public $incrementing = true;
    protected $keyType = 'int';
}
