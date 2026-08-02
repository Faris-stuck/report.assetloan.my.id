<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SeoController extends Controller
{
    public function bullyingGuide(): View
    {
        return view('public.seo.bullying-guide');
    }

    public function faq(): View
    {
        return view('public.seo.faq');
    }
}
