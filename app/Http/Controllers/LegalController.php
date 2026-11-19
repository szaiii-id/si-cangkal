<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LegalController extends Controller
{
    public function privacy(): InertiaResponse
    {
        return Inertia::render('Legal/Privacy');
    }

    public function terms(): InertiaResponse
    {
        return Inertia::render('Legal/Terms');
    }

    public function refundPolicy(): InertiaResponse
    {
        return Inertia::render('Legal/RefundPolicy');
    }

    public function cookiePolicy(): InertiaResponse
    {
        return Inertia::render('Legal/CookiePolicy');
    }

    public function mitraRules(): InertiaResponse
    {
        return Inertia::render('Legal/MitraRules');
    }

    public function acceptableUse(): InertiaResponse
    {
        return Inertia::render('Legal/AcceptableUse');
    }
}
