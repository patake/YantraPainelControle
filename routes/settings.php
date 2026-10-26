<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // O primeiro cadastro do 2FA fica no assistente de primeiro acesso (R-ACE-15).
    Route::middleware('two-factor.required')->group(function () {
        Route::livewire('settings/security', 'pages::settings.security')
            ->middleware(['password.confirm'])
            ->name('security.edit');
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
        Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
    });
});
