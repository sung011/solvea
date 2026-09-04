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

    public function favorites(): string
    {
        return render('favorites', [
            'bodyClass' => 'page-favorites',
            'pageTitle' => '저장한 정책 · 나도대상?',
        ]);
    }
}
