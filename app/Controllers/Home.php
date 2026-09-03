<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return render('welcome_message');
    }

    public function results(): string
    {
        return render('results', ['bodyClass' => 'page-results']);
    }
}
