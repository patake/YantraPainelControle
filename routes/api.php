<?php

use App\Http\Controllers\Api\Agent\PingController;
use App\Http\Controllers\Api\Agent\PublicKeyController;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

// Contrato OpenAPI da API do agente.
Route::get('docs', [ApiDocsController::class, 'index'])->name('api.docs');
Route::get('openapi.yaml', [ApiDocsController::class, 'spec'])->name('api.openapi');

// API do agente, versão 1. O equipamento sai do token: as rotas não levam o id do equipamento.
Route::prefix('v1/agent')->name('agent.')->middleware('api.log')->group(function () {
    Route::get('public-key', PublicKeyController::class)->name('public-key');

    Route::middleware(['auth:sanctum', 'agent', 'agent.signed'])->group(function () {
        Route::get('ping', PingController::class)->name('ping');
    });
});
