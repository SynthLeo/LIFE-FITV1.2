<?php
// ============================================================
//  utils/helpers.php
//  Funciones de uso general en todos los módulos
// ============================================================

function generarFolio(string $prefijo = 'VU'): string {
    return strtoupper($prefijo . '-' . substr(md5(uniqid(rand(), true)), 0, 8));
}

function calcularEdad(?string $fechaNacimiento): int {
    if (!$fechaNacimiento) return 20; // edad por defecto si no está registrada
    return (int)(new DateTime($fechaNacimiento))->diff(new DateTime())->y;
}

function calcularIMC(float $pesoKg, int $alturaCm): float {
    if ($alturaCm <= 0) return 0;
    $alturaM = $alturaCm / 100;
    return round($pesoKg / ($alturaM ** 2), 1);
}

function categoriaIMC(float $imc): string {
    return match(true) {
        $imc < 18.5 => 'bajo_peso',
        $imc < 25.0 => 'normal',
        $imc < 30.0 => 'sobrepeso',
        $imc < 35.0 => 'obesidad_I',
        $imc < 40.0 => 'obesidad_II',
        default     => 'obesidad_III',
    };
}

function calcularTMB(float $peso, int $altura, int $edad, ?string $sexo): float {
    // Fórmula Mifflin-St Jeor — si sexo es null, usa masculino como base
    if ($sexo === 'masculino' || !$sexo) {
        return (10 * $peso) + (6.25 * $altura) - (5 * $edad) + 5;
    }
    return (10 * $peso) + (6.25 * $altura) - (5 * $edad) - 161;
}

function interpretarSignosVitales(array $signos): array {
    $result = [];

    if (isset($signos['temperatura_c'])) {
        $t = (float)$signos['temperatura_c'];
        $result['temperatura'] = match(true) {
            $t < 35.0  => ['nivel' => 'rojo',      'msg' => 'Hipotermia — acude al médico'],
            $t < 36.0  => ['nivel' => 'amarillo',  'msg' => 'Temperatura baja'],
            $t <= 37.4 => ['nivel' => 'verde',     'msg' => 'Normal'],
            $t <= 38.0 => ['nivel' => 'amarillo',  'msg' => 'Febrícula — reposo e hidratación'],
            $t <= 39.0 => ['nivel' => 'rojo',      'msg' => 'Fiebre — consulta médica hoy'],
            default    => ['nivel' => 'emergencia','msg' => 'Fiebre alta — urgencias ahora'],
        };
    }

    if (isset($signos['frecuencia_cardiaca'])) {
        $fc = (int)$signos['frecuencia_cardiaca'];
        $result['frecuencia_cardiaca'] = match(true) {
            $fc < 40   => ['nivel' => 'emergencia','msg' => 'Bradicardia severa — urgencias'],
            $fc < 60   => ['nivel' => 'amarillo',  'msg' => 'Baja — normal en deportistas'],
            $fc <= 100 => ['nivel' => 'verde',     'msg' => 'Normal'],
            $fc <= 120 => ['nivel' => 'amarillo',  'msg' => 'Elevada — reposo y revisa'],
            default    => ['nivel' => 'rojo',      'msg' => 'Taquicardia — consulta médica'],
        };
    }

    if (isset($signos['presion_sistolica'], $signos['presion_diastolica'])) {
        $s = (int)$signos['presion_sistolica'];
        $d = (int)$signos['presion_diastolica'];
        $result['presion_arterial'] = match(true) {
            $s < 90 || $d < 60  => ['nivel' => 'amarillo',  'msg' => 'Presión baja — hidratación'],
            $s < 120 && $d < 80 => ['nivel' => 'verde',     'msg' => 'Óptima'],
            $s < 130 && $d < 80 => ['nivel' => 'verde',     'msg' => 'Normal'],
            $s < 140 || $d < 90 => ['nivel' => 'amarillo',  'msg' => 'Normal alta — monitorea'],
            $s < 180            => ['nivel' => 'rojo',      'msg' => 'Hipertensión — médico esta semana'],
            default             => ['nivel' => 'emergencia','msg' => 'Crisis hipertensiva — urgencias YA'],
        };
    }

    if (isset($signos['saturacion_o2'])) {
        $sat = (int)$signos['saturacion_o2'];
        $result['saturacion_o2'] = match(true) {
            $sat >= 95 => ['nivel' => 'verde',     'msg' => 'Normal'],
            $sat >= 90 => ['nivel' => 'amarillo',  'msg' => 'Baja — evalúa con médico'],
            default    => ['nivel' => 'emergencia','msg' => 'Crítica — urgencias inmediato'],
        };
    }

    return $result;
}

function nivelUrgenciaSintomas(array $sintomas, string $dias): string {
    $emergencia = ['dificultad respiratoria','dolor en el pecho','pérdida de conciencia',
                   'convulsiones','parálisis','sangrado abundante'];
    $rojo       = ['fiebre alta','vómito con sangre','confusión mental'];

    foreach ($emergencia as $s) {
        foreach ($sintomas as $sym) {
            if (stripos($sym, $s) !== false) return 'emergencia';
        }
    }
    foreach ($rojo as $s) {
        foreach ($sintomas as $sym) {
            if (stripos($sym, $s) !== false) return 'rojo';
        }
    }

    $cantidad = count($sintomas);
    if ($dias === 'Más de una semana' || $cantidad >= 5) return 'rojo';
    if ($cantidad >= 3 || in_array($dias, ['4-7 días'])) return 'amarillo';
    return 'verde';
}