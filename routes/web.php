<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio');

Route::middleware(['auth', 'active', 'two-factor.required'])->group(function () {
    Route::get('inicio', HomeController::class)->name('home');

    // Área Staff Weega: administrador e suporte.
    Route::prefix('staff')->name('staff.')->middleware('role:admin,support')->group(function () {
        Route::livewire('/', 'pages::staff.dashboard')->name('dashboard');

        Route::livewire('clientes', 'pages::staff.clients.index')->name('clients.index');
        Route::livewire('clientes/novo', 'pages::staff.clients.form')->name('clients.create');
        Route::livewire('clientes/{client}', 'pages::staff.clients.show')->name('clients.show');
        Route::livewire('clientes/{client}/editar', 'pages::staff.clients.form')->name('clients.edit');

        Route::livewire('equipamentos', 'pages::staff.devices.index')->name('devices.index');
        Route::livewire('sistemas', 'pages::staff.systems.index')->name('systems.index');
        Route::livewire('versoes', 'pages::staff.releases.index')->name('releases.index');

        Route::livewire('usuarios', 'pages::staff.users.index')->name('users.index');
        Route::livewire('usuarios/novo', 'pages::staff.users.form')->name('users.create');
        Route::livewire('usuarios/{user}/editar', 'pages::staff.users.form')->name('users.edit');

        Route::livewire('auditoria', 'pages::staff.logs.audit')->name('logs.audit');
        Route::livewire('auditoria/requisicoes', 'pages::staff.logs.requests')->name('logs.requests');
    });

    // Área Cliente: operador.
    Route::prefix('app')->name('app.')->middleware('role:operator')->group(function () {
        Route::livewire('/', 'pages::app.dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
