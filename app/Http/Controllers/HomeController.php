<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function home(): View
    {
        $campaign = Campaign::current();

        return view('home', [
            'campaign' => $campaign,
            'availability' => $campaign?->availabilityAt(now()),
        ]);
    }

    public function regulation(): View
    {
        return view('regulation', ['campaign' => Campaign::current()]);
    }
}
