<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\ProveedorExterno;

/**
 * Sostiene el invariante del sistema: ningún texto llega al proveedor sin pasar
 * por amordazar(), y ninguna respuesta vuelve al ciudadano sin pasar por
 * restaurar(). Los sistemas consumen SIEMPRE este decorador, nunca el proveedor
 * desnudo; así el invariante no depende de la disciplina de quien programa.
 */
class ProveedorAnonimizado implements ProveedorExterno
{
    public function __construct(
        private readonly ProveedorExterno $interno,
        private readonly Anonimizador $anonimizador,
    ) {}

    public function preguntar(string $texto): string
    {
        $resultado = $this->anonimizador->amordazar($texto);

        if ($resultado->veredicto === Veredicto::Vetado || $resultado->boveda === null) {
            // El paquete no decide el fallback: devuelve vacío y el sistema
            // resuelve si cae a la capa de preguntas frecuentes, al modelo
            // local o a atención humana.
            return '';
        }

        $respuesta = $this->interno->preguntar($resultado->textoSeguro);

        return $this->anonimizador->restaurar($respuesta, $resultado->boveda);
    }

    public function veredictoDe(string $texto): Veredicto
    {
        return $this->anonimizador->amordazar($texto)->veredicto;
    }
}
