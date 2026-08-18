<?php

/**
 * Corpus de evaluación: mensajes como los que escribe un vecino, con lo que
 * DEBERÍA detectarse en cada uno.
 *
 * TODOS LOS DATOS SON INVENTADOS. Nunca se pega acá un mensaje real ni un RUT
 * de una persona: el corpus se versiona, y versionar datos de un vecino sería
 * exactamente lo que este paquete existe para evitar. Los RUT son válidos en
 * dígito verificador pero no corresponden a nadie.
 *
 * Cada caso: [mensaje, [tipos que deben detectarse], [valores exactos]].
 */
return [
    // --- Estructurados: lo que el ciclo 1A debe resolver ---
    ['Hola, soy Juan Pérez, RUT 12.345.678-5, quiero saber de mi retiro de escombros',
        ['rut'], ['12.345.678-5']],
    ['mi rut es 20347878-K y necesito un certificado',
        ['rut'], ['20347878-K']],
    ['Llámenme al +56912345678 por favor',
        ['telefono'], ['+56912345678']],
    ['mi numero es 9 8765 4321 gracias',
        ['telefono'], ['9 8765 4321']],
    ['escríbanme a maria.gonzalez@correo.cl',
        ['email'], ['maria.gonzalez@correo.cl']],
    ['Soy Pedro, RUT 15.678.234-3, fono 987654321, correo pedro@mail.cl',
        ['rut', 'telefono', 'email'], ['15.678.234-3', '987654321', 'pedro@mail.cl']],

    // --- Casos que NO deben marcarse (falsos positivos) ---
    ['El monto a pagar es 12.345.678-9 según la boleta', [], []],
    ['La reunión fue en 2026 y asistieron 45000 personas', [], []],
    ['El decreto 1234-5 no me quedó claro', [], []],
    ['Quiero saber el horario de atención de la municipalidad', [], []],

    // --- Lo que el ciclo 1A NO cubre todavía: nombres y direcciones ---
    // Están anotados a propósito para que la medición muestre el hueco real.
    ['Soy Juan Pérez y vivo en Los Aromos 234',
        ['nombre', 'direccion'], ['Juan Pérez', 'Los Aromos 234']],
    ['Mi vecina Rosa Muñoz de calle Bernardo O\'Higgins 1450 tiene el mismo problema',
        ['nombre', 'direccion'], ['Rosa Muñoz', 'Bernardo O\'Higgins 1450']],

    // --- Categoría sensible: debe VETARSE, no tokenizarse (ciclo 1B) ---
    ['Necesito la credencial de discapacidad de mi hijo que tiene autismo',
        ['SENSIBLE'], []],
    ['Solicito ayuda social porque estoy con tratamiento de cáncer',
        ['SENSIBLE'], []],
    ['Mi señora quedó postrada y necesito el subsidio',
        ['SENSIBLE'], []],
];
