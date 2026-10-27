<?php
// ============================================================
//  utils/dieta.php
//  Validación del plan semanal de comidas y armado del prompt.
//  Estructura esperada:
//  { dias: [{ dia, comidas: [{ tiempo, descripcion, kcal }] }], calorias_dia, nota }
// ============================================================

const DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

/** Mínimo de calorías diarias que la app acepta en un plan (no ofrece dietas muy restrictivas). */
const KCAL_MINIMO_SEGURO = 1400;
const KCAL_MAXIMO        = 4500;

/** Esquema JSON para LM Studio (response_format json_schema). */
function esquemaPlanDieta(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['dias', 'calorias_dia', 'nota'],
        'properties' => [
            'calorias_dia' => ['type' => 'integer'],
            'nota'         => ['type' => 'string'],
            'dias' => [
                'type' => 'array', 'minItems' => 7, 'maxItems' => 7,
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['dia', 'comidas'],
                    'properties' => [
                        'dia' => ['type' => 'string', 'enum' => DIAS_SEMANA],
                        'comidas' => [
                            'type' => 'array', 'minItems' => 3, 'maxItems' => 5,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['tiempo', 'descripcion', 'kcal'],
                                'properties' => [
                                    'tiempo'      => ['type' => 'string'],
                                    'descripcion' => ['type' => 'string'],
                                    'kcal'        => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/** Texto limpio y recortado (sin HTML). */
function textoPlano($v, int $max): string {
    $t = trim(strip_tags((string) $v));
    $t = preg_replace('/\s+/u', ' ', $t);
    return mb_substr($t, 0, $max);
}

/**
 * Valida y normaliza un plan. Devuelve el plan limpio o lanza InvalidArgumentException
 * con el motivo. Nunca confía en lo que mande el cliente o el modelo.
 */
function validarPlanDieta($plan): array {
    if (!is_array($plan) || !isset($plan['dias']) || !is_array($plan['dias'])) {
        throw new InvalidArgumentException('El plan no tiene la lista de días.');
    }
    $dias = [];
    foreach (array_slice($plan['dias'], 0, 7) as $d) {
        if (!is_array($d) || !isset($d['comidas']) || !is_array($d['comidas'])) continue;
        $nombre = textoPlano($d['dia'] ?? '', 12);
        // Acepta "Miercoles"/"miércoles": se normaliza al nombre oficial
        foreach (DIAS_SEMANA as $oficial) {
            if (mb_strtolower(strtr($nombre, 'áéí', 'aei')) === mb_strtolower(strtr($oficial, 'áéí', 'aei'))) { $nombre = $oficial; break; }
        }
        if (!in_array($nombre, DIAS_SEMANA, true)) continue;

        $comidas = [];
        foreach (array_slice($d['comidas'], 0, 6) as $c) {
            if (!is_array($c)) continue;
            $desc = textoPlano($c['descripcion'] ?? '', 160);
            if ($desc === '') continue;
            $kcal = (int) ($c['kcal'] ?? 0);
            $comidas[] = [
                'tiempo'      => textoPlano($c['tiempo'] ?? 'Comida', 30) ?: 'Comida',
                'descripcion' => $desc,
                'kcal'        => max(0, min(2000, $kcal)),
            ];
        }
        if (count($comidas) >= 2) $dias[] = ['dia' => $nombre, 'comidas' => $comidas];
    }
    if (count($dias) < 7) {
        throw new InvalidArgumentException('El plan debe traer los 7 días con al menos 2 comidas cada uno.');
    }

    $kcalDia = (int) ($plan['calorias_dia'] ?? 0);
    if ($kcalDia <= 0) {   // si no viene, se estima con el promedio de los días
        $kcalDia = (int) round(array_sum(array_map(fn($d) => array_sum(array_column($d['comidas'], 'kcal')), $dias)) / 7);
    }
    if ($kcalDia < KCAL_MINIMO_SEGURO || $kcalDia > KCAL_MAXIMO) {
        throw new InvalidArgumentException("Calorías por día fuera de un rango seguro ($kcalDia kcal).");
    }

    return [
        'dias'         => $dias,
        'calorias_dia' => $kcalDia,
        'nota'         => textoPlano($plan['nota'] ?? '', 300),
    ];
}
