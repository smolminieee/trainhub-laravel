<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('trainhub:about', function () {
    $this->info('TrainHub Al Amin - Laravel 12 migration');
})->purpose('Show TrainHub migration information');
