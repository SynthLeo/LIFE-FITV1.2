<?php
// ============================================================
//  ia/crisis.php
//  Detección de crisis EN EL SERVIDOR, antes de llamar al modelo.
//  Si se detecta, el chat responde con un mensaje fijo revisado
//  y NO pregunta al modelo (no dependemos de que el modelo
//  "se porte bien").
//
//  Es una lista de palabras clave: puede dar falsos positivos.
//  En salud mental es preferible pecar de cuidadoso.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';

/** Minúsculas y sin acentos, para comparar sin importar cómo escriban. */
function normalizarTexto(string $t): string {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return preg_replace('/\s+/', ' ', $t);
}

const PATRONES_CRISIS = [
    // Riesgo de autolesión o suicidio
    'autolesion' => [
        '/\bsuicid/', '/\bmatarme\b/', '/\bquitarme la vida\b/', '/\bacabar con mi vida\b/',
        '/\bquiero morir(me)?\b/', '/\bme quiero morir\b/', '/\bno quiero (seguir )?vivir\b/',
        '/\bya no quiero estar aqui\b/', '/\bdesaparecer para siempre\b/',
        '/\b(hacerme|me quiero hacer|me voy a hacer|me hago) dano\b/', '/\blastimarme\b/',
        '/\bcortarme\b/', '/\bme corto\b/', '/\bautolesi/', '/\bno vale la pena vivir\b/',
    ],
    // Preguntas de cantidades de medicamento que pueden indicar sobredosis
    'sobredosis' => [
        '/\bcuantas (pastillas|tabletas|capsulas)\b/', '/\bsobredosis\b/',
        '/\b(pastillas|tabletas) para (no despertar|morir)\b/', '/\btomarme (todas|todo el frasco)\b/',
    ],
    // Emergencia médica
    'emergencia' => [
        '/\bdolor (fuerte |intenso )?(en el|de) pecho\b/', '/\bno puedo respirar\b/', '/\bme estoy (ahogando|desmayando)\b/',
        '/\bconvulsi/', '/\bperdi el conocimiento\b/', '/\bsangr\w* (mucho|sin parar)\b/', '/\bparalisis\b/',
        '/\bno siento (la cara|el brazo|la pierna)\b/',
    ],
    // Señales de trastorno de la conducta alimentaria
    'tca' => [
        '/\bno (he )?comido (en|hace) (varios|\d+) dias\b/', '/\bme provoco el vomito\b/', '/\bvomito a proposito\b/',
        '/\bme purgo\b/', '/\b(laxantes|diureticos) para (bajar|adelgazar)\b/',
    ],
];

/** Devuelve el tipo de crisis detectado o null. */
function detectarCrisis(string $mensaje): ?string {
    $t = normalizarTexto($mensaje);
    foreach (PATRONES_CRISIS as $tipo => $patrones) {
        foreach ($patrones as $p) {
            if (preg_match($p, $t)) return $tipo;
        }
    }
    return null;
}

/** Mensaje fijo, revisado, para cada tipo de crisis. */
function mensajeCrisis(string $tipo): string {
    $linea = LINEA_AYUDA_NOMBRE . ': ' . LINEA_AYUDA_TEL . ' (gratis, 24 horas)';
    return match ($tipo) {
        'autolesion' =>
            "💜 Gracias por contármelo. Lo que sientes es importante y no tienes que pasarlo solo/a.\n\n"
            . "Por favor habla hoy con alguien que pueda ayudarte:\n"
            . "• {$linea}\n"
            . "• Si estás en peligro inmediato, llama al 911.\n\n"
            . "También puedes contárselo a alguien de confianza o pedir una cita en el módulo de Psicología. Aquí sigo para escucharte.",
        'sobredosis' =>
            "No puedo darte cantidades de medicamentos. Tomar de más puede ser muy peligroso, incluso con medicamentos comunes.\n\n"
            . "Si tienes dudas sobre una dosis, pregúntale a tu médico o farmacéutico. "
            . "Si estás pensando en hacerte daño, habla hoy con alguien:\n"
            . "• {$linea}\n• Emergencias: 911",
        'emergencia' =>
            "🚨 Lo que describes puede ser una emergencia médica. Llama al 911 o ve a urgencias ahora. "
            . "Si estás haciendo ejercicio, detente de inmediato y no te quedes solo/a.",
        'tca' =>
            "Gracias por contarlo. Dejar de comer o provocarte el vómito puede dañar mucho tu cuerpo, y no tienes que enfrentarlo solo/a.\n\n"
            . "Te recomiendo hablar con un profesional de salud o pedir una cita en Psicología o Clínico. "
            . "Si sientes que necesitas ayuda ahora: {$linea}.",
        default => "Si necesitas ayuda ahora: {$linea}. Emergencias: 911.",
    };
}
