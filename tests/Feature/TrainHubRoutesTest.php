<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrainHubRoutesTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login.php');
    }
}
