<?php

return [
    // Cuánto vive el mapeo marcador → valor real. Cubre una respuesta asíncrona
    // por cola sin dejar los datos del vecino vivos más de lo necesario.
    'ttl_boveda' => (int) env('ANONIMIZACION_TTL_BOVEDA', 900),

    // Store de caché donde vive la bóveda. Debe ser una base Redis SEPARADA de
    // colas y caché: compartida, un FLUSHALL de mantención se lleva la bóveda
    // por delante, y un dump de la cola arrastra datos personales.
    'store_boveda' => env('ANONIMIZACION_STORE_BOVEDA', 'redis'),

    // Formato del número de seguimiento de esta instalación. Vacío = sin folios.
    'patron_folio' => env('ANONIMIZACION_PATRON_FOLIO', ''),

    'api' => [
        'habilitada' => (bool) env('ANONIMIZACION_API_HABILITADA', false),

        // "sistema:token,otro:token" → tokens por consumidor, rotables y
        // revocables por separado.
        'tokens' => env('ANONIMIZACION_TOKENS', ''),

        // Consumidores autorizados a llamar /restaurar, la operación que
        // devuelve datos reales. Lista separada por comas.
        'pueden_restaurar' => env('ANONIMIZACION_PUEDEN_RESTAURAR', ''),
    ],
];
