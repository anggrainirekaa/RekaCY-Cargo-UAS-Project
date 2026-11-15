<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        return view('pages.landing');
    }

    public function about(): View
    {
        return view('pages.about');
    }
}
