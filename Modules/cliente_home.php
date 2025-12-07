<?php
// Modules/cliente_home.php
require_once __DIR__ . '/../App/bd.php';
$pdo = db();

// 1. Identificar al cliente usando su correo de usuario
$u = current_user();
$st = $pdo->prepare("SELECT * FROM clientes WHERE correo = ? LIMIT 1");
$st->execute([$u['correo']]);
$cliente = $st->fetch(PDO::FETCH_ASSOC);

// Si el usuario no tiene ficha de cliente asociada
if (!$cliente) {
    echo '<div class="container-fluid py-5"><div class="alert alert-warning text-center">
        <i class="bi bi-person-exclamation fs-1"></i><br><br>
        <strong>Cuenta no vinculada.</strong><br>
        Tu usuario existe, pero no encontramos una ficha de cliente con el correo <u>'.htmlspecialchars($u['correo']).'</u>.
        <br>Por favor contacta a soporte.
    </div></div>';
    return;
}

// 2. Calcular Saldo Real (Cargos emitidos o vencidos que NO están pagados)
$stSaldo = $pdo->prepare("
    SELECT COALESCE(SUM(cg.total), 0) 
    FROM cargos cg
    JOIN ordenes o ON o.id = cg.orden_id
    WHERE o.cliente_id = ? AND cg.estatus IN ('emitido', 'vencido')
");
$stSaldo->execute([$cliente['id']]);
$saldo = (float)$stSaldo->fetchColumn();
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h2 class="fw-bold" style="color: #444;">Hola, <?= htmlspecialchars($cliente['empresa']) ?> 👋</h2>
        <p class="text-muted">Bienvenido a tu portal de servicios.</p>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm h-100 overflow-hidden">
                <div class="card-body p-4 position-relative">
                    <h6 class="text-uppercase text-muted small fw-bold mb-3">Saldo por Pagar</h6>
                    
                    <div class="display-4 fw-bold mb-3 <?= $saldo > 0 ? 'text-danger' : 'text-success' ?>">
                        $<?= number_format($saldo, 2) ?>
                    </div>

                    <?php if ($saldo > 0): ?>
                        <div class="alert alert-danger d-inline-flex align-items-center py-2 px-3 mb-0">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <span>Tienes pagos pendientes.</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success d-inline-flex align-items-center py-2 px-3 mb-0">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <span>¡Estás al corriente! Gracias.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="text-uppercase text-muted small fw-bold mb-3">Accesos Directos</h6>
                    <div class="d-grid gap-3 d-sm-flex">
                        <a href="?m=usuarios" class="btn btn-outline-secondary px-4 py-3">
                            <i class="bi bi-person-gear fs-4 d-block mb-2"></i>
                            Mi Perfil
                        </a>
                        <button class="btn btn-light px-4 py-3 text-muted" disabled>
                            <i class="bi bi-clock-history fs-4 d-block mb-2"></i>
                            Historial (Pronto)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>