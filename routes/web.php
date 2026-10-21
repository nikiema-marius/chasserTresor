<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyDataController;
use App\Http\Controllers\ParticipantAuthController;
use App\Http\Controllers\ParticipantPagesController;
use App\Livewire\Participant\MysteryWordForm;
use App\Http\Controllers\ScanLinkController;
use App\Livewire\Participant\Dashboard;
use App\Livewire\Participant\JoinCampaign;
use App\Livewire\Participant\Login;
use App\Livewire\Participant\MyData;
use App\Livewire\Participant\Register;
use App\Livewire\Participant\Scanner;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Espace public et participant
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'home'])->name('home');
Route::get('/reglement', [HomeController::class, 'regulation'])->name('regulation');
Route::get('/faq', [ParticipantPagesController::class, 'faq'])->name('faq');

// URL encodée dans les QR codes (appareil photo natif)
Route::get('/s/{token}', ScanLinkController::class)
    ->where('token', '[A-Za-z0-9_-]{1,64}')
    ->middleware(['throttle:scan-link', 'participant.current'])
    ->name('scan.link');

Route::middleware('guest:participant')->group(function () {
    Route::get('/inscription', Register::class)->name('register');
    Route::get('/connexion', Login::class)->name('login');
});

Route::middleware('auth:participant')->group(function () {
    Route::middleware('participant.current')->group(function () {
        Route::get('/jeu', Dashboard::class)->name('dashboard');
        Route::get('/jeu/scanner', Scanner::class)->name('scanner');
        Route::get('/jeu/mot-mystere', MysteryWordForm::class)->name('mystery');
        Route::get('/jeu/mes-lettres', [ParticipantPagesController::class, 'letters'])->name('letters');
        Route::get('/jeu/historique', [ParticipantPagesController::class, 'history'])->name('history');
        Route::get('/jeu/profil', [ParticipantPagesController::class, 'profile'])->name('profile');
    });
    // Participant d'un jeu précédent : rejoindre le jeu en cours sans se réinscrire
    Route::get('/jeu/rejoindre', JoinCampaign::class)->name('join');
    Route::get('/mes-donnees', MyData::class)->name('my-data');
    Route::get('/mes-donnees/export', [MyDataController::class, 'export'])
        ->middleware('throttle:5,60')
        ->name('my-data.export');
    Route::post('/deconnexion', [ParticipantAuthController::class, 'logout'])->name('logout');
});
