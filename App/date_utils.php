<?php
// App/date_utils.php

/**
 * Devuelve el fin de un intervalo comenzando en $start.
 * - mensual: +$count meses - 1 día
 * - anual:   +1 año - 1 día
 */
function end_by_interval(DateTimeImmutable $start, string $unit, int $count): DateTimeImmutable {
  if ($unit === 'anual') return $start->modify('+1 year')->modify('-1 day');
  $count = max(1, (int)$count);
  return $start->modify("+{$count} month")->modify('-1 day');
}

/** Primer día del mes (Y-m-01) */
function month_start(int $year, int $month): DateTimeImmutable {
  $month = max(1, min(12, $month));
  return new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
}
