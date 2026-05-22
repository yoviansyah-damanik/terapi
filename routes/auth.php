<?php

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');

    // OAuth RS — Authorization Code Flow
    Route::get('/auth/oauth/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/auth/oauth/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
