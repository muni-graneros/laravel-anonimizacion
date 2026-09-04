<?php

namespace Anonimizacion\Excepciones;

use RuntimeException;

/**
 * Un sistema declaró que maneja datos sensibles y no hay clasificador real.
 *
 * El enlace por defecto de `ClasificadorSensible` es `SinClasificador`, que
 * nunca veta. Para un sistema que solo necesita tapar RUT, teléfono y correo eso
 * está bien y es lo documentado. Para uno que maneja datos del artículo 2 letra
 * g de la Ley 21.719 —salud, origen, situación de discapacidad— no: el texto
 * sale con el diagnóstico entero y solo el RUT tapado, mientras el README habla
 * de «fail-closed».
 *
 * Se prefiere no arrancar antes que dar una protección que no existe.
 */
class ClasificadorAusente extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'anonimizacion.datos_sensibles está en true y no hay ningún ClasificadorSensible '.
            'enlazado: el enlace por defecto (SinClasificador) NUNCA veta, así que los datos '.
            'de salud, origen o discapacidad saldrían tal cual con solo el RUT tapado. '.
            'Enlazá un ClasificadorSensible real en un ServiceProvider de la aplicación, o '.
            'poné datos_sensibles en false si este sistema de verdad no los maneja.'
        );
    }
}
