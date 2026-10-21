<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\QrImageController;
use App\Livewire\Admin\Admins;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\Campaigns;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Participants;
use App\Livewire\Admin\Prizes;
use App\Livewire\Admin\QrCodes;
use App\Livewire\Admin\Rewards;
use App\Livewire\Admin\TwoFactorSetup;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Espace d'administration — préfixe config('game.admin_path'), noms « admin.* »
|--------------------------------------------------------------------------
| Rôles : viewer (lecture) < admin (« manage ») < super_admin (« super »)
*/

Route::get('/connexion', Login::class)->middleware('guest:admin')->name('login');

Route::middleware('auth:admin')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
    Route::get('/securite/2fa', TwoFactorSetup::class)->name('2fa.setup');

    Route::middleware('admin.secure')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');

        // Lecture (tous les rôles)
        Route::get('/campagnes', Campaigns\Index::class)->name('campaigns');
        Route::get('/campagnes/{campaign}/participants', Participants\Index::class)->name('participants');
        Route::get('/participants/{participant}', Participants\Show::class)->name('participants.show');

        // Gestion (admin et super-admin)
        Route::middleware('can:manage')->group(function () {
            Route::get('/campagnes/creer', Campaigns\Form::class)->name('campaigns.create');
            Route::get('/campagnes/{campaign}/modifier', Campaigns\Form::class)->name('campaigns.edit');
            Route::get('/campagnes/{campaign}/qr-codes', QrCodes\Index::class)->name('qr-codes');
            Route::get('/campagnes/{campaign}/gains', Prizes\Index::class)->name('prizes');
            Route::get('/qr-codes/{qr}/image.{format}', [QrImageController::class, 'show'])
                ->whereIn('format', ['png', 'svg'])->name('qr-codes.image');
            Route::get('/campagnes/{campaign}/qr-codes/export.zip', [QrImageController::class, 'zip'])
                ->middleware('throttle:10,1')->name('qr-codes.zip');
            Route::get('/campagnes/{campaign}/qr-codes/export.pdf', [QrImageController::class, 'pdf'])
                ->middleware('throttle:10,1')->name('qr-codes.pdf');
        });

        // Super-admin : récompenses, exports de données personnelles, journal, comptes
        Route::middleware('can:super')->group(function () {
            Route::get('/campagnes/{campaign}/recompenses', Rewards\Index::class)->name('rewards');
            Route::get('/campagnes/{campaign}/exports/participants.csv', [ExportController::class, 'participants'])
                ->middleware('throttle:20,60')->name('exports.participants');
            Route::get('/campagnes/{campaign}/exports/scans.csv', [ExportController::class, 'scans'])
                ->middleware('throttle:20,60')->name('exports.scans');
            Route::get('/campagnes/{campaign}/exports/gagnants.csv', [ExportController::class, 'winners'])
                ->middleware('throttle:20,60')->name('exports.winners');
            Route::get('/journal', AuditLogs::class)->name('audit');
            Route::get('/administrateurs', Admins::class)->name('admins');
        });
    });
});
