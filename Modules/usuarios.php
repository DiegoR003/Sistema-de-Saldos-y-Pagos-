<?php
// Modules/usuarios.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';

$pdo = db();

$currentUser = current_user();
if (!$currentUser) {
    echo '<div class="container-fluid"><div class="alert alert-danger">Debes iniciar sesión.</div></div>';
    exit;
}

// Paginación
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 2; 
$offset = ($page - 1) * $limit;

// Obtener total
$totalUsers = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

$usuarioId = (int)$currentUser['id'];

$sqlMe = "
  SELECT u.id, u.nombre, u.correo, u.activo, u.creado_en, u.foto_url,
         COALESCE(r.nombre, '') AS rol
  FROM usuarios u
  LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
  LEFT JOIN roles r       ON r.id = ur.rol_id
  WHERE u.id = ?
  LIMIT 1
";
$st = $pdo->prepare($sqlMe);
$st->execute([$usuarioId]);
$me = $st->fetch(PDO::FETCH_ASSOC);

if (!$me) {
    echo '<div class="container-fluid"><div class="alert alert-danger">Usuario no encontrado.</div></div>';
    exit;
}

$rolActual = strtolower(trim((string)$me['rol']));
$isAdmin   = ($rolActual === 'admin');

// Lista de usuarios (solo admin)
$usuarios = [];
if ($isAdmin) {
    $sqlUsers = "
      SELECT
        u.id, u.nombre, u.correo, u.activo, u.creado_en, u.foto_url,
        COALESCE(r.nombre,'—') AS rol,
        r.id as rol_id
      FROM usuarios u
      LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
      LEFT JOIN roles r        ON r.id = ur.rol_id
      ORDER BY u.creado_en DESC
      LIMIT $limit OFFSET $offset
    ";
    $st = $pdo->query($sqlUsers);
    $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Roles disponibles
$roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

function fmt_fecha(?string $d): string {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}
?>

<style>
.usuarios-page .profile-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 1rem;
  padding: 2rem;
  color: white;
  margin-bottom: 1.5rem;
}
.usuarios-page .avatar-big {
  width: 100px; height: 100px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2.5rem; font-weight: 700;
  background: rgba(255,255,255,.2);
  border: 4px solid rgba(255,255,255,.3);
  position: relative; overflow: hidden;
  cursor: pointer; transition: all .3s;
}
.usuarios-page .avatar-big:hover {
  transform: scale(1.05); border-color: rgba(255,255,255,.5);
}
.usuarios-page .avatar-big img { width: 100%; height: 100%; object-fit: cover; }
.usuarios-page .avatar-upload-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,.6);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .3s;
}
.usuarios-page .avatar-big:hover .avatar-upload-overlay { opacity: 1; }
.usuarios-page .card { border-radius: 1rem; border: none; }
.usuarios-page .accordion-button:not(.collapsed) { background: #f8f9fa; color: #000; }
.usuarios-page .badge-rol { padding: 0.35rem 0.75rem; border-radius: 999px; font-weight: 600; font-size: 0.75rem; }
.usuarios-page .table-usuarios td { vertical-align: middle; padding: 0.75rem 0.5rem; }
.usuarios-page .avatar-sm {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem; font-weight: 600;
  background: linear-gradient(135deg, #f39c12, #e74c3c); color: white;
}
@media (max-width: 768px) {
  .usuarios-page .table-usuarios thead { display: none; }
  .usuarios-page .table-usuarios td { display: block; width: 100%; text-align: right; border-bottom: 1px solid #eee; }
  .usuarios-page .table-usuarios td::before { content: attr(data-label); float: left; font-weight: bold; color: #666; }
  .usuarios-page .table-usuarios td:first-child { text-align: center; }
}
</style>

<div class="container-fluid usuarios-page">
  <div class="profile-header shadow-sm">
    <div class="row align-items-center">
      <div class="col-auto">
        <div class="avatar-big" data-bs-toggle="modal" data-bs-target="#modalFoto">
          <?php if (!empty($me['foto_url'])): ?>
            <img src="<?= htmlspecialchars($me['foto_url']) ?>" alt="Foto">
          <?php else: ?>
            <?= strtoupper(mb_substr($me['nombre'] ?? 'U', 0, 1)) ?>
          <?php endif; ?>
          <div class="avatar-upload-overlay"><i class="bi bi-camera fs-3"></i></div>
        </div>
      </div>
      <div class="col">
        <h3 class="mb-1 fw-bold"><?= htmlspecialchars($me['nombre']) ?></h3>
        <div class="mb-2 opacity-90"><?= htmlspecialchars($me['correo']) ?></div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <span class="badge bg-light text-dark badge-rol text-capitalize"><i class="bi bi-shield-check me-1"></i><?= $rolActual ?: 'Sin rol' ?></span>
          <?php if ($me['activo']): ?>
            <span class="badge bg-success badge-rol"><i class="bi bi-check-circle me-1"></i>Activo</span>
          <?php else: ?>
            <span class="badge bg-secondary badge-rol">Inactivo</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="accordion mb-4" id="accordionConfig">
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingAccount">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccount" aria-expanded="false" aria-controls="collapseAccount">
          <i class="bi bi-person-circle me-2"></i> Datos de cuenta
        </button>
      </h2>
      <div id="collapseAccount" class="accordion-collapse collapse" aria-labelledby="headingAccount" data-bs-parent="#accordionConfig">
        <div class="accordion-body">
          <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_actualizar.php" method="post" 
                onsubmit="confirmarAccion(event, '¿Guardar cambios?', 'Se actualizarán tus datos personales.', 'Sí, guardar', '#0d6efd')">
            <input type="hidden" name="id" value="<?= $me['id'] ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nombre completo</label>
                <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($me['nombre']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Correo electrónico</label>
                <input type="email" class="form-control" name="correo" value="<?= htmlspecialchars($me['correo']) ?>" required>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Guardar cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="headingPassword">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePassword" aria-expanded="false" aria-controls="collapsePassword">
          <i class="bi bi-key me-2"></i> Cambiar contraseña
        </button>
      </h2>
      <div id="collapsePassword" class="accordion-collapse collapse" aria-labelledby="headingPassword" data-bs-parent="#accordionConfig">
        <div class="accordion-body">
          <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_cambiar_password.php" method="post"
                onsubmit="confirmarAccion(event, '¿Cambiar contraseña?', 'Deberás usar la nueva contraseña en tu próximo inicio de sesión.', 'Sí, cambiar', '#0d6efd')">
            <input type="hidden" name="id" value="<?= $me['id'] ?>">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Contraseña actual</label>
                <input type="password" class="form-control" name="password_actual" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Nueva contraseña</label>
                <input type="password" class="form-control" name="password_nueva" required minlength="6">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Confirmar nueva</label>
                <input type="password" class="form-control" name="password_confirmar" required>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-lock me-1"></i> Actualizar contraseña</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <h5 class="mb-0"><i class="bi bi-people me-2"></i>Administrar usuarios</h5>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
        <i class="bi bi-plus-lg me-1"></i> Nuevo usuario
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-usuarios mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 50px;"></th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Alta</th><th style="width: 100px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($usuarios)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay usuarios registrados</td></tr>
          <?php else: ?>
            <?php foreach ($usuarios as $u): ?>
              <tr>
                <td>
                  <div class="avatar-sm">
                    <?php if (!empty($u['foto_url'])): ?>
                      <img src="<?= htmlspecialchars($u['foto_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                      <?= strtoupper(mb_substr($u['nombre'], 0, 1)) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td data-label="Nombre" class="fw-semibold"><?= htmlspecialchars($u['nombre']) ?></td>
                <td data-label="Correo" class="text-muted"><?= htmlspecialchars($u['correo']) ?></td>
                <td data-label="Rol"><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($u['rol']) ?></span></td>
                <td data-label="Estado">
                  <?php if ($u['activo']): ?><span class="badge bg-success">Activo</span>
                  <?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                </td>
                <td data-label="Alta" class="text-muted small"><?= fmt_fecha($u['creado_en']) ?></td>
                <td data-label="Acciones">
                  <button class="btn btn-sm btn-outline-secondary" onclick='abrirModalEditar(<?= json_encode($u) ?>)' title="Editar"><i class="bi bi-pencil"></i></button>
                  
                  <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_eliminar.php" method="POST" class="d-inline"
                        onsubmit="confirmarAccion(event, '¿Eliminar usuario?', 'Se eliminará permanentemente a <?= htmlspecialchars($u['nombre']) ?>.', 'Sí, eliminar', '#dc3545')">
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <?php if ($totalPages > 1): ?>
  <nav class="mt-3 pb-3">
    <ul class="pagination justify-content-center pagination-sm">
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?m=usuarios&p=<?= $page - 1 ?>">Anterior</a></li>
      <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li>
      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>"><a class="page-link" href="?m=usuarios&p=<?= $page + 1 ?>">Siguiente</a></li>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="modal fade" id="modalFoto" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Cambiar foto de perfil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_foto.php" method="post" enctype="multipart/form-data">
        <div class="modal-body text-center">
          <div class="mb-3"><img id="previewFoto" src="<?= $me['foto_url'] ?: 'https://via.placeholder.com/200' ?>" class="rounded-circle" style="width: 200px; height: 200px; object-fit: cover;"></div>
          <input type="file" class="form-control" name="foto" accept="image/*" required onchange="previewImage(event)">
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Subir foto</button></div>
      </form>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Nuevo usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_crear.php" method="post">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" required></div>
          <div class="mb-3"><label class="form-label">Correo</label><input type="email" class="form-control" name="correo" required></div>
          <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" class="form-control" name="password" required></div>
          <div class="mb-3"><label class="form-label">Rol</label>
            <select class="form-select" name="rol_id" required>
              <?php foreach ($roles as $rol): ?><option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="activo" value="1" checked id="chkAct"><label class="form-check-label" for="chkAct">Activo</label></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Crear</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Editar usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="/Sistema-de-Saldos-y-Pagos-/Public/api/usuario_editar_admin.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" id="edit_nombre" required></div>
          <div class="mb-3"><label class="form-label">Correo</label><input type="email" class="form-control" name="correo" id="edit_correo" required></div>
          <div class="mb-3"><label class="form-label">Rol</label>
            <select class="form-select" name="rol_id" id="edit_rol_id" required>
              <?php foreach ($roles as $rol): ?><option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Nueva Contraseña (Opcional)</label><input type="password" class="form-control" name="password" placeholder="Dejar vacío para mantener actual"></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="activo" value="1" id="edit_activo"><label class="form-check-label" for="edit_activo">Activo</label></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>



<script>
// Previsualización de foto
function previewImage(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => { document.getElementById('previewFoto').src = e.target.result; };
    reader.readAsDataURL(file);
  }
}

// Abrir modal de edición con datos
function abrirModalEditar(user) {
  document.getElementById('edit_id').value = user.id;
  document.getElementById('edit_nombre').value = user.nombre;
  document.getElementById('edit_correo').value = user.correo;
  const rolSelect = document.getElementById('edit_rol_id');
  if(user.rol_id) rolSelect.value = user.rol_id;
  document.getElementById('edit_activo').checked = (user.activo == 1);
  new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
}

// Función SweetAlert segura
function confirmarAccion(event, titulo, texto, btnTexto, colorBtn) {
  event.preventDefault(); // Detener envío
  const form = event.target;
  Swal.fire({
    title: titulo || '¿Estás seguro?',
    text: texto || "No podrás revertir esto",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: colorBtn || '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: btnTexto || 'Sí, hacerlo',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit(); // Enviar formulario
    }
  });
}
</script>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>