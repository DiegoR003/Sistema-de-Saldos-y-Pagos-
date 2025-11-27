<?php
// Modules/pagos_atrasados.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';

$pdo = db();

/**
 * Pagos atrasados:
 *  - órdenes activas
 *  - cargos emitidos, aún no pagados
 *  - cuyo periodo_fin ya pasó (vencidos)
 */
$hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

$sql = "
SELECT
  c.id               AS cliente_id,
  c.empresa          AS nombre,
  c.correo,
  c.telefono,
  o.id               AS orden_id,
  o.total,
  o.periodicidad,
  COUNT(cg.id)       AS cargos_vencidos,
  MIN(cg.periodo_inicio) AS primer_inicio,
  MAX(cg.periodo_fin)    AS ultimo_fin
FROM cargos cg
JOIN ordenes o   ON o.id = cg.orden_id
JOIN clientes c  ON c.id = o.cliente_id
WHERE
  o.estado = 'activa'
  AND cg.estatus = 'emitido'
  AND cg.periodo_fin < :hoy
GROUP BY
  c.id, c.empresa, c.correo, c.telefono,
  o.id, o.total, o.periodicidad
ORDER BY c.empresa ASC
";

$st = $pdo->prepare($sql);
$st->execute([':hoy' => $hoy]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function money_mx($v): string {
    return '$' . number_format((float)$v, 2, '.', ',');
}

function fmt_date(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function prettify_periodicidad(?string $p): string {
    switch ($p) {
        case 'mensual':   return 'Mensual';
        case 'bimestral': return 'Bimestral';
        case 'unico':     return 'Único';
        default:          return 'Servicio';
    }
}
?>

<style>
  /* ===== Pagos atrasados ===== */
  .overdues .topbar{ gap:.5rem; }
  .overdues .search-card .card-body{ padding:1rem; }
  .overdues .search-card .form-control{ height:44px; }
  .overdues .search-card .btn-search{
    height:44px; display:inline-flex; align-items:center;
    background:#e74c3c; border-color:#e74c3c; color:#fff;
  }
  .overdues .search-card .btn-search:hover{ filter:brightness(.95); }

  .overdues .cliente-card{ border-left:4px solid #fde68a; }
  .overdues .cliente-card .card-body{ padding:1.1rem; }
  .overdues .cliente-card .name{ font-weight:700; font-size:1.05rem; }
  .overdues .cliente-card .meta{ color:#6b7280; }
  .overdues .badge-overdue{
    background:#dc3545; color:#fff; font-weight:700; font-size:.75rem;
    border-radius:999px; padding:.25rem .6rem;
  }
  .overdues .actions .btn{ padding:.35rem .6rem; }
  .overdues .btn-cobrar{ background:#28a745; border-color:#28a745; color:#fff; }
  .overdues .btn-det{ background:#17a2b8; border-color:#17a2b8; color:#fff; }
</style>

<div class="container-fluid overdues">
  <!-- Título + "Mostrar" -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">
      Pagos atrasados <span class="text-muted fs-6">Control panel</span>
    </h3>

    <div class="dropdown">
      <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">
        Mostrar
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#">Todos</a></li>
        <li><a class="dropdown-item" href="#">Últimos 7 días</a></li>
        <li><a class="dropdown-item" href="#">Últimos 30 días</a></li>
      </ul>
    </div>
  </div>

  <!-- Buscador (front-end) -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <div class="input-group">
        <input id="qOver" type="text" class="form-control" placeholder="Buscar por nombre o teléfono…">
        <button id="btnSearchOver" class="btn btn-search" type="button">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </div>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info">
      No hay pagos vencidos por el momento 🎉
    </div>
  <?php else: ?>

    <?php foreach ($rows as $r): 
      $nombre    = $r['nombre'];
      $telefono  = $r['telefono'] ?: '—';
      $correo    = $r['correo']   ?: '—';
      $monto     = (float)$r['total'];
      $periodo   = prettify_periodicidad($r['periodicidad']);
      $vencidos  = (int)$r['cargos_vencidos'];
      $desdeTxt  = fmt_date($r['primer_inicio']);
      $hastaTxt  = fmt_date($r['ultimo_fin']);
    ?>
      <div class="card border-0 shadow-sm mb-3 cliente-card"
           data-name="<?= htmlspecialchars(mb_strtolower($nombre)) ?>"
           data-phone="<?= htmlspecialchars(mb_strtolower($telefono)) ?>">
        <div class="card-body">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
              <div class="name mb-1"><?= htmlspecialchars($nombre) ?></div>
              <div class="meta">
                <!-- <div>Dirección: <span class="text-dark">—</span></div>-->
                <div>Teléfono: <span class="text-dark"><?= htmlspecialchars($telefono) ?></span></div>
                <div>Servicio contratado:
                  <span class="text-decoration-none"><?= htmlspecialchars($periodo) ?></span>
                  <span class="text-muted"><?= money_mx($monto) ?></span>
                  <?php if ($vencidos > 1): ?>
                    <span class="text-muted">
                      · <?= $vencidos ?> periodos vencidos
                    </span>
                  <?php endif; ?>
                </div>
                <div class="mt-1">
                  Periodo vencido: <span class="text-dark"><?= $desdeTxt ?> – <?= $hastaTxt ?></span>
                </div>
                <div class="mt-1">
                  Estado de pago: <span class="badge-overdue">Vencida</span>
                </div>
              </div>
            </div>

            <div class="actions d-flex align-items-start gap-2">
              <!-- Aquí tú puedes cambiar la URL/acción de cobro -->
              <a href="/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>"
                 class="btn btn-sm btn-cobrar">
                <i class="bi bi-cash-coin me-1"></i> Cobrar
              </a>

              <button type="button"
                      class="btn btn-sm btn-det"
                      data-bs-toggle="modal"
                      data-bs-target="#clienteDetModal"
                      data-nombre="<?= htmlspecialchars($nombre) ?>"
                      data-telefono="<?= htmlspecialchars($telefono) ?>"
                      data-correo="<?= htmlspecialchars($correo) ?>"
                      data-servicio="<?= htmlspecialchars($periodo . ' ' . money_mx($monto)) ?>"
                      data-periodo="<?= $desdeTxt . ' – ' . $hastaTxt ?>"
                      data-vencidos="<?= $vencidos ?>">
                <i class="bi bi-person-badge me-1"></i> Detalles Cliente
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<!-- Modal Detalles Cliente -->
<div class="modal fade" id="clienteDetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalles del cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p><strong>Cliente:</strong> <span id="detNombre"></span></p>
        <p><strong>Correo:</strong> <span id="detCorreo"></span></p>
        <p><strong>Teléfono:</strong> <span id="detTelefono"></span></p>
        <p><strong>Servicio:</strong> <span id="detServicio"></span></p>
        <p><strong>Periodo vencido:</strong> <span id="detPeriodo"></span></p>
        <p><strong>Pagos vencidos:</strong> <span id="detVencidos"></span></p>
        <small class="text-muted">
          Desde aquí sólo es informativo. La acción de cobro se realiza con el botón verde “Cobrar”.
        </small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
/* Filtro simple por nombre o teléfono (front-end) */
(function(){
  const q = document.getElementById('qOver');
  const btn = document.getElementById('btnSearchOver');
  const cards = Array.from(document.querySelectorAll('.overdues .cliente-card'));

  function filtrar(){
    const term = (q.value || '').toLowerCase().trim();
    cards.forEach(c => {
      const name  = (c.getAttribute('data-name')  || '').toLowerCase();
      const phone = (c.getAttribute('data-phone') || '').toLowerCase();
      c.style.display = (!term || name.includes(term) || phone.includes(term)) ? '' : 'none';
    });
  }
  q.addEventListener('input', filtrar);
  btn.addEventListener('click', filtrar);
})();

/* Rellenar el modal Detalles Cliente */
(function(){
  const modal = document.getElementById('clienteDetModal');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', function (event) {
    const button   = event.relatedTarget;
    if (!button) return;

    const nombre   = button.getAttribute('data-nombre')   || '—';
    const correo   = button.getAttribute('data-correo')   || '—';
    const telefono = button.getAttribute('data-telefono') || '—';
    const servicio = button.getAttribute('data-servicio') || '—';
    const periodo  = button.getAttribute('data-periodo')  || '—';
    const vencidos = button.getAttribute('data-vencidos') || '1';

    modal.querySelector('#detNombre').textContent   = nombre;
    modal.querySelector('#detCorreo').textContent   = correo;
    modal.querySelector('#detTelefono').textContent = telefono;
    modal.querySelector('#detServicio').textContent = servicio;
    modal.querySelector('#detPeriodo').textContent  = periodo;
    modal.querySelector('#detVencidos').textContent = vencidos;
  });
})();
</script>
