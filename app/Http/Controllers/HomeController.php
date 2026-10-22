<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Support\GamesList;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /** Accueil : tous les jeux en ligne, avec la progression du participant connecté. */
    public function home(): View
    {
        return view('home', [
            'games' => GamesList::for(Auth::guard('participant')->user()),
        ]);
    }

    /**
     * Règlement d'un jeu : celui demandé, sinon celui du participant connecté,
     * sinon la liste des jeux en ligne.
     */
    public function regulation(?Campaign $campaign = null): View
    {
        $campaign ??= Auth::guard('participant')->user()?->campaign;
        $online = Campaign::online();

        if (! $campaign && $online->count() === 1) {
            $campaign = $online->first();
        }

        return view('regulation', ['campaign' => $campaign, 'online' => $online]);
    }
}
