<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assinatura das respostas para o agente
    |--------------------------------------------------------------------------
    |
    | O painel assina as respostas da API do agente com Ed25519. O agente tem a
    | chave pública no instalador e rejeita respostas com assinatura inválida.
    | Gere o par de chaves com: php artisan yantra:signing-key --write
    |
    */

    'signing' => [
        'key_id' => env('YANTRA_SIGNING_KEY_ID', 'dev'),
        'secret_key' => env('YANTRA_SIGNING_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinal de vida do agente
    |--------------------------------------------------------------------------
    |
    | Intervalo mínimo, em segundos, entre duas gravações de devices.last_seen_at.
    |
    */

    'last_seen_interval' => (int) env('YANTRA_LAST_SEEN_INTERVAL', 30),

];
