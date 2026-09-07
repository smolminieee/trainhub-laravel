<?php

namespace App\Models;

class LoginSession extends TrainHubModel
{
    protected $table = 'login_session';
    protected $primaryKey = 'sessionID';
    public $incrementing = true;
    protected $keyType = 'int';
}
