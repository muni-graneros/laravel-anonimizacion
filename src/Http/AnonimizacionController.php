<?php

namespace Anonimizacion\Http;

use Anonimizacion\Anonimizador;
use Anonimizacion\BovedaId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnonimizacionController
{
    public function __construct(private readonly Anonimizador $anonimizador) {}

    public function amordazar(Request $request): JsonResponse
    {
        $datos = $request->validate(['texto' => ['required', 'string', 'max:20000']]);

        $resultado = $this->anonimizador->amordazar($datos['texto']);

        return response()->json([
            'veredicto' => $resultado->veredicto->value,
            'categoria' => $resultado->categoria,
            'texto_seguro' => $resultado->textoSeguro,
            'boveda_id' => $resultado->boveda?->valor,
            'tipos' => $resultado->tipos,
        ]);
    }

    public function restaurar(Request $request): JsonResponse
    {
        // Restaurar devuelve datos reales: tener un token válido no alcanza,
        // hace falta el permiso explícito.
        $autorizados = array_filter(array_map(
            'trim',
            explode(',', (string) config('anonimizacion.api.pueden_restaurar'))
        ));

        if (! in_array($request->attributes->get('consumidor'), $autorizados, true)) {
            return response()->json(['error' => 'sin permiso para restaurar'], 403);
        }

        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:20000'],
            'boveda_id' => ['required', 'string'],
        ]);

        return response()->json([
            'texto' => $this->anonimizador->restaurar($datos['texto'], new BovedaId($datos['boveda_id'])),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'servicio' => 'anonimizacion',
            'tokens_cargados' => count(VerificarTokenDeServicio::cargarTokens()),
        ]);
    }
}
