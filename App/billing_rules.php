<?php
// Reglas para traducir (grupo + opción) -> esquema de cobro
// Devuelve: [billing_type, interval_unit, interval_count, next_run]
function infer_billing(array $it, string $periodicidadGlobal = 'mensual'): array {
    $grupo  = strtolower(trim($it['grupo'] ?? ''));
    $opcion = strtolower(trim((string)($it['opcion'] ?? '')));
    $valor  = (float)($it['valor'] ?? 0);

    // Por defecto: recurrente mensual
    $billing_type   = 'recurrente';
    $interval_unit  = 'mensual';
    $interval_count = 1;

    // === Reglas por grupo ===
    switch ($grupo) {
        case 'cuenta':
        case 'publicaciones':
        case 'campañas':
        case 'reposteo':
        case 'stories':
        case 'imprenta':
        case 'ads':
        case 'mkt':
            // todos mensuales por defecto
            break;

        case 'video':
        case 'fotos':
            // Si dice “cada 2 meses” => bimestral
            if (strpos($opcion, '2') !== false) {
                $interval_count = 2;
            }
            // Si es “sí / única vez” => cobro único
            if (in_array($opcion, ['si','sí','unica vez','única vez'], true)) {
                $billing_type   = 'una_vez';
                $interval_unit  = null;
                $interval_count = null;
            }
            break;

        case 'web':
            // En este diseño, la web es mensual (hosting/soporte base)
            // Si tú decides que “web” NO sea mensual, cambia aquí a 'una_vez'
            $billing_type   = 'recurrente';
            $interval_unit  = 'mensual';
            $interval_count = 1;
            break;

        case 'mantenimiento_web':
            // Servicio anual aparte ($2,999)
            $billing_type   = 'recurrente';
            $interval_unit  = 'anual';
            $interval_count = 1;
            break;
    }

    // Si el ítem es $0, no lo programes recurrente para evitar “ruido”
    if ($valor <= 0) {
        $billing_type   = 'una_vez';
        $interval_unit  = null;
        $interval_count = null;
    }

    // Calcula next_run
    $today = new DateTimeImmutable('today');
    $next_run = null;
    if ($billing_type === 'recurrente') {
        if ($interval_unit === 'mensual') {
            $months = max(1, (int)$interval_count);
            $next_run = $today->modify("+{$months} month")->format('Y-m-d');
        } elseif ($interval_unit === 'anual') {
            $next_run = $today->modify('+1 year')->format('Y-m-d');
        } else {
            // fallback: usa periodicidad global por si quieres
            if ($periodicidadGlobal === 'bimestral') {
                $next_run = $today->modify('+2 month')->format('Y-m-d');
            } else {
                $next_run = $today->modify('+1 month')->format('Y-m-d');
            }
        }
    }

    return [$billing_type, $interval_unit, $interval_count, $next_run];
}
