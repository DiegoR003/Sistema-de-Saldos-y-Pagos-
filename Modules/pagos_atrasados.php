<?php
// Modules/pagos_atrasados.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/notifications.php';

// Cargar Mailer si existe
if (file_exists(__DIR__ . '/../App/mailer.php')) {
    require_once __DIR__ . '/../App/mailer.php';
}

$pdo = db();
$hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

/* =============================================================================
   LÓGICA DE NOTIFICACIÓN AUTOMÁTICA AL STAFF (ADMIN / OPERADOR)
   - Se ejecuta al cargar la vista.
   - Revisa órdenes vencidas.
   - Si no se ha notificado hoy, envía alerta y correo al staff.
   ============================================================================= */
function ejecutarNotificacionesAutomaticas($pdo, $hoy) {
    // 1. Buscar órdenes con cargos vencidos
    $sqlVencidos = "
        SELECT 
            o.id AS orden_id,
            c.empresa,
            c.id AS cliente_id,
            COUNT(cg.id) as cargos_vencidos,
            SUM(cg.total) as total_deuda
        FROM cargos cg
        JOIN ordenes o ON o.id = cg.orden_id
        JOIN clientes c ON c.id = o.cliente_id
        WHERE o.estado = 'activa'
          AND cg.estatus = 'emitido'
          AND cg.periodo_fin < :hoy
        GROUP BY o.id
    ";
    $st = $pdo->prepare($sqlVencidos);
    $st->execute([':hoy' => $hoy]);
    $deudores = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($deudores)) return;

    // 2. Obtener Staff (Admins y Operadores)
    $sqlStaff = "
        SELECT DISTINCT u.id, u.nombre, u.correo 
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id = u.id
        JOIN roles r ON r.id = ur.rol_id
        WHERE r.nombre IN ('admin', 'operador') AND u.activo = 1
    ";
    $staff = $pdo->query($sqlStaff)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Procesar cada deudor
    foreach ($deudores as $d) {
        $ordenId = $d['orden_id'];
        
        // --- FILTRO ANTI-SPAM (24 HORAS) ---
        // Verificamos si ya notificamos sobre ESTA orden en las últimas 24 horas
        // Usamos 'ref_tipo' = 'alerta_vencimiento'
        $stCheck = $pdo->prepare("
            SELECT id FROM notificaciones 
            WHERE ref_tipo = 'alerta_vencimiento' 
              AND ref_id = ? 
              AND creado_en > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        $stCheck->execute([$ordenId]);
        
        if ($stCheck->fetch()) {
            continue; // Ya avisamos hoy, saltar.
        }

        // --- ENVIAR AVISOS AL STAFF ---
        $deudaFmt = number_format((float)$d['total_deuda'], 2);
        
        foreach ($staff as $usuario) {
            // A) Campanita
            $notifData = [
                'tipo'       => 'sistema',
                'canal'      => 'interna',
                'titulo'     => '⚠️ Pago Atrasado Detectado',
                'cuerpo'     => "El cliente {$d['empresa']} tiene {$d['cargos_vencidos']} cargos vencidos. Total: $ {$deudaFmt}",
                'usuario_id' => $usuario['id'],
                'cliente_id' => $d['cliente_id'],
                'ref_tipo'   => 'alerta_vencimiento', // Clave para el filtro anti-spam
                'ref_id'     => $ordenId,
                'estado'     => 'pendiente' // Pendiente de leer
            ];
            // Al guardar esto, se crea el registro que bloquea el spam por 24h
            enviar_notificacion($pdo, $notifData, true);

            // B) Correo
            if (function_exists('enviar_correo_sistema')) {
                $asunto = "Alerta de Cobranza: {$d['empresa']}";
                $html = "
                <div style='font-family: Arial; padding: 20px; border: 1px solid #eee;'>
                    <h3 style='color: #dc3545;'>Alerta de Sistema: Pago Vencido</h3>
                    <p>Se ha detectado un cliente con pagos retrasados en el sistema.</p>
                    <ul>
                        <li><strong>Cliente:</strong> {$d['empresa']}</li>
                        <li><strong>Cargos vencidos:</strong> {$d['cargos_vencidos']}</li>
                        <li><strong>Deuda Total:</strong> $ {$deudaFmt}</li>
                    </ul>
                    <p>Por favor revisa el módulo de <strong>Pagos Atrasados</strong> para gestionar el cobro.</p>
                    <p><small>Este es un aviso automático generado al revisar la lista de deudores.</small></p>
                </div>";
                
                enviar_correo_sistema($usuario['correo'], $usuario['nombre'], $asunto, $html);
            }
        }
    }
}

// Ejecutamos la función silenciosamente al cargar la página
try {
    ejecutarNotificacionesAutomaticas($pdo, $hoy);
} catch (Exception $e) {
    // Si falla, no rompemos la vista, solo lo registramos en logs o ignoramos
    // error_log($e->getMessage());
}

/* =============================================================================
   CONSULTA PRINCIPAL PARA LA VISTA (Tu código original)
   ============================================================================= */

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
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">
      Pagos atrasados <span class="text-muted fs-6">Control panel</span>
    </h3>
  </div>

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
              
              <a href="/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>"
                 class="btn btn-sm btn-cobrar">
                <i class="bi bi-cash-coin me-1"></i> Cobrar
              </a>

               <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/pago_recordatorio.php"
                    onsubmit="confirmarAccion(event, '¿Reenviar recordatorio?', 'Se enviará un correo manual al cliente.', 'Sí, enviar', '#0dcaf0')">
                  <input type="hidden" name="orden_id" value="<?= (int)$r['orden_id'] ?>">
                  <button class="btn btn-sm btn-info text-white" title="Reenviar recordatorio manual">
                    <i class="bi bi-envelope-paper-fill"></i>
                  </button>
              </form>

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
                <i class="bi bi-person-badge me-1"></i> Detalles
              </button>

            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<div class="modal fade" id="clienteDetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalles del cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>Cliente:</strong> <span id="detNombre"></span></p>
        <p><strong>Correo:</strong> <span id="detCorreo"></span></p>
        <p><strong>Teléfono:</strong> <span id="detTelefono"></span></p>
        <p><strong>Servicio:</strong> <span id="detServicio"></span></p>
        <p><strong>Periodo vencido:</strong> <span id="detPeriodo"></span></p>
        <p><strong>Pagos vencidos:</strong> <span id="detVencidos"></span></p>
        <small class="text-muted">La alerta automática ya fue procesada si correspondía.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Filtro
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
  if(q) q.addEventListener('input', filtrar);
  if(btn) btn.addEventListener('click', filtrar);

  // Modal
  const modal = document.getElementById('clienteDetModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      modal.querySelector('#detNombre').textContent   = button.getAttribute('data-nombre') || '—';
      modal.querySelector('#detCorreo').textContent   = button.getAttribute('data-correo') || '—';
      modal.querySelector('#detTelefono').textContent = button.getAttribute('data-telefono') || '—';
      modal.querySelector('#detServicio').textContent = button.getAttribute('data-servicio') || '—';
      modal.querySelector('#detPeriodo').textContent  = button.getAttribute('data-periodo') || '—';
      modal.querySelector('#detVencidos').textContent = button.getAttribute('data-vencidos') || '0';
    });
  }
})();
</script>