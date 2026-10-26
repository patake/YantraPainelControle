<?php

use App\Http\Controllers\Api\Agent\ActivationController;
use App\Http\Controllers\Api\Agent\ConfigController;
use App\Http\Controllers\Api\Agent\LatestUpdateController;
use App\Http\Controllers\Api\Agent\LicenseController;
use App\Http\Controllers\Api\Agent\PingController;
use App\Http\Controllers\Api\Agent\PublicKeyController;
use App\Http\Controllers\Api\Agent\RegisterController;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

// Contrato OpenAPI da API do agente.
Route::get('docs', [ApiDocsController::class, 'index'])->name('api.docs');
Route::get('openapi.yaml', [ApiDocsController::class, 'spec'])->name('api.openapi');

// API do agente, versão 1. O equipamento sai do token: as rotas não levam o id do equipamento.
Route::prefix('v1/agent')->name('agent.')->middleware('api.log')->group(function () {
    Route::get('public-key', PublicKeyController::class)->name('public-key');

    // Ativação por CNPJ, antes de o equipamento ter token (R-LIC-8: 10 tentativas por hora por IP).
    Route::middleware(['throttle:agent-activation', 'agent.signed'])->group(function () {
        Route::get('activation/{cnpj}', ActivationController::class)->name('activation');
        Route::post('register', RegisterController::class)->name('register');
    });

    Route::middleware(['auth:sanctum', 'agent', 'agent.signed'])->group(function () {
        Route::get('ping', PingController::class)->name('ping');
        Route::get('license', LicenseController::class)->name('license');
        Route::get('updates/latest', LatestUpdateController::class)->name('updates.latest');

        // Rotas de trabalho: só para equipamento em trial ou ativo.
        Route::middleware('agent.licensed')->group(function () {
            Route::get('config', ConfigController::class)->name('config');
        });
    });
});
