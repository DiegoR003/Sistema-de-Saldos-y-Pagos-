<?php
// Modules/usuarios.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';
$pdo = db();

/**
 * 1) Identificar usuario actual
 *    Ajusta esta parte a tu sistema de login:
 *    - si guardas el id en $_SESSION['usuario_id'], esto ya funciona.
 *    - si usas otra variable, cámbiala aquí.
 */

// Obtener usuario actual del sistema de auth
$currentUser = current_user();
$usuarioId = $currentUser ? $currentUser['id'] : 0;

// Para que puedas ver la pantalla aunque aún no tengas login montado,
// si $usuarioId es 0 probará con el usuario con id = 1 (si existe).
if ($usuarioId <= 0) {
    $usuarioId = 1;
}

// 2) Leer usuario + roles
$st = $pdo->prepare("
  SELECT
    u.id,
    u.nombre,
    u.correo,
    u.activo,
    u.creado_en,
    GROUP_CONCAT(r.nombre ORDER BY r.id SEPARATOR ',') AS roles_raw
  FROM usuarios u
  LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
  LEFT JOIN roles r       ON r.id = ur.rol_id
  WHERE u.id = ?
  GROUP BY u.id, u.nombre, u.correo, u.activo, u.creado_en
");
$st->execute([$usuarioId]);
$usuario = $st->fetch(PDO::FETCH_ASSOC) ?: null;

function fmt_fecha(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    return date('d/m/Y', $ts);
}

// roles en array limpio
$roles = [];
if (!empty($usuario['roles_raw'])) {
    foreach (explode(',', $usuario['roles_raw']) as $r) {
        $r = trim($r);
        if ($r !== '') $roles[] = $r;
    }
}

// inicial para el circulito del avatar
$inicial = $usuario && $usuario['nombre']
    ? mb_strtoupper(mb_substr($usuario['nombre'], 0, 1, 'UTF-8'), 'UTF-8')
    : 'U';
?>
<style>
.perfil-page h3{
  font-weight:600;
}
.perfil-avatar{
  width:80px;
  height:80px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:2.5rem;
  font-weight:700;
  color:#fff;
  background:linear-gradient(135deg,#f39c12,#e74c3c);
}
.perfil-role-badge{
  font-size:.75rem;
  font-weight:600;
  border-radius:999px;
  padding:.2rem .7rem;
}
.perfil-role-admin{
  background:#c0392b;
  color:#fff;
}
.perfil-role-operador{
  background:#2980b9;
  color:#fff;
}
.perfil-role-cliente{
  background:#27ae60;
  color:#fff;
}
.perfil-role-otro{
  background:#7f8c8d;
  color:#fff;
}
</style>

<div class="container-fluid perfil-page">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Usuarios <span class="text-muted fs-6">Perfil</span></h3>

    <!-- En el futuro aquí puedes poner botón "Administrar usuarios" solo para admin -->
  </div>

  <?php if (!$usuario): ?>
    <div class="alert alert-warning">
      Aún no hay usuario registrado (tabla <code>usuarios</code> está vacía).
      Crea uno manualmente en la base de datos para probar esta sección.
    </div>
    <?php return; ?>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Columna izquierda: Avatar + datos básicos -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="perfil-avatar flex-shrink-0">
            <?= htmlspecialchars($inicial) ?>
          </div>
          <div class="flex-grow-1">
            <h5 class="mb-1"><?= htmlspecialchars($usuario['nombre'] ?? 'Usuario') ?></h5>
            <div class="small text-muted mb-1">
              <?= htmlspecialchars($usuario['correo'] ?? 'sin correo') ?>
            </div>

            <div class="d-flex flex-wrap gap-1 mb-1">
              <?php if ($roles): ?>
                <?php foreach ($roles as $rol): 
                  $rolLower = strtolower($rol);
                  $cls = 'perfil-role-otro';
                  if ($rolLower === 'admin')    $cls = 'perfil-role-admin';
                  elseif ($rolLower === 'operador') $cls = 'perfil-role-operador';
                  elseif ($rolLower === 'cliente')  $cls = 'perfil-role-cliente';
                ?>
                  <span class="perfil-role-badge <?= $cls ?>">
                    <?= htmlspecialchars(ucfirst($rol)) ?>
                  </span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="perfil-role-badge perfil-role-otro">Sin rol</span>
              <?php endif; ?>
            </div>

            <div class="small">
              Estado:
              <?php if ((int)$usuario['activo'] === 1): ?>
                <span class="badge bg-success">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php endif; ?>
            </div>
            <div class="small text-muted mt-1">
              Miembro desde <?= fmt_fecha($usuario['creado_en'] ?? null) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
          Foto de perfil
        </div>
        <div class="card-body">
          <p class="small text-muted mb-2">
            Aquí podrás subir una foto para tu perfil. Por ahora solo es de demostración,
            luego conectamos el guardado real.
          </p>
          <form action="#" method="post" enctype="multipart/form-data">
            <input type="file" class="form-control mb-2" name="avatar" disabled>
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
              Subir foto (próximamente)
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Columna derecha: formularios (solo interfaz) -->
    <div class="col-12 col-lg-8">
      <!-- Datos de cuenta -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
          Datos de cuenta
        </div>
        <div class="card-body">
          <p class="small text-muted">
            Esta sección está pensada para que el usuario (admin u operador) pueda
            actualizar su nombre y correo. De momento el formulario no guarda nada;
            después creamos el endpoint para hacerlo seguro.
          </p>
          <form action="#" method="post">
            <div class="mb-3">
              <label class="form-label">Nombre completo</label>
              <input type="text"
                     class="form-control"
                     name="nombre"
                     value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
                     disabled>
            </div>
            <div class="mb-3">
              <label class="form-label">Correo electrónico</label>
              <input type="email"
                     class="form-control"
                     name="correo"
                     value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>"
                     disabled>
            </div>

            <button type="button" class="btn btn-primary" disabled>
              Guardar cambios (próximamente)
            </button>
          </form>
        </div>
      </div>

      <!-- Cambio de contraseña -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
          Cambiar contraseña
        </div>
        <div class="card-body">
          <p class="small text-muted">
            Para producción habrá que validar la contraseña actual y actualizar
            el <code>pass_hash</code> en la tabla <code>usuarios</code>.
            Por ahora es solo la plantilla visual.
          </p>
          <form action="#" method="post">
            <div class="mb-3">
              <label class="form-label">Contraseña actual</label>
              <input type="password" class="form-control" name="old_pass" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label">Nueva contraseña</label>
              <input type="password" class="form-control" name="new_pass" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmar nueva contraseña</label>
              <input type="password" class="form-control" name="new_pass2" disabled>
            </div>
            <button type="button" class="btn btn-outline-primary" disabled>
              Actualizar contraseña (próximamente)
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
