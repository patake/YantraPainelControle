<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // A tela de segurança fica fora do 2FA obrigatório: é nela que o usuário cadastra o 2FA.
    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware(['password.confirm'])
        ->name('security.edit');

    Route::middleware('two-factor.required')->group(function () {
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
        Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
    });
});
