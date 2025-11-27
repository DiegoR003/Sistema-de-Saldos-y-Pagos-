<?php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/billing_rules.php';

function back(string $msg, bool $ok): never {
  $url='/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
  header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
  exit;
}

function norm(?string $s): string { return strtolower(trim((string)$s)); }

try{
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); exit('Método no permitido');
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) back('ID inválido', false);

  $periodicidadGlobal = $_POST['periodicidad'] ?? 'mensual';
  if (!in_array($periodicidadGlobal,['unico','mensual','bimestral'],true)) $periodicidadGlobal='mensual';

  // overrides desde UI
  $overrides = json_decode($_POST['billing_json'] ?? '{}', true);
  if (!is_array($overrides)) $overrides = [];

 


  // === RFC emisor (OBLIGATORIO) ===
  // Debe venir del formulario (hidden aprRfcId) y existir en company_rfcs
 $rfcId = 0;
if (isset($_POST['rfc_id'])) {
    $rfcId = (int)$_POST['rfc_id'];
} elseif (isset($_POST['aprRfcId'])) {
    $rfcId = (int)$_POST['aprRfcId'];
}

if ($rfcId <= 0) {
  back('Selecciona el RFC emisor antes de aprobar.', false);
}

  $pdo = db();
  $pdo->beginTransaction();

  // valida que el RFC exista
  $st = $pdo->prepare("SELECT id, rfc, razon_social FROM company_rfcs WHERE id = ? LIMIT 1");
  $st->execute([$rfcId]);
  $emisor = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emisor) back('RFC emisor inválido.', false);

  // 1) cotización
  $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id=? FOR UPDATE");
  $st->execute([$id]);
  $cot = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cot) back('Cotización no encontrada', false);
  if ($cot['estado']!=='pendiente') back('La cotización no está pendiente', false);

  // 2) cliente (por correo)
  $st=$pdo->prepare("SELECT id FROM clientes WHERE correo=? LIMIT 1");
  $st->execute([$cot['correo']]);
  $clienteId = (int)$st->fetchColumn();
  if ($clienteId<=0){
    $st=$pdo->prepare("INSERT INTO clientes(empresa,correo) VALUES(?,?)");
    $st->execute([$cot['empresa'],$cot['correo']]);
    $clienteId=(int)$pdo->lastInsertId();
  }

  // 3) aprobar cotización
  $st=$pdo->prepare("UPDATE cotizaciones SET estado='aprobada', cliente_id=? WHERE id=?");
  $st->execute([$clienteId,$id]);

  // 4) orden
  $vence=null;
  if ($periodicidadGlobal==='mensual')   $vence=date('Y-m-d',strtotime('+30 days'));
  if ($periodicidadGlobal==='bimestral') $vence=date('Y-m-d',strtotime('+60 days'));

  // ✅ guardamos rfc_id en la orden
  $st=$pdo->prepare("
    INSERT INTO ordenes
      (cotizacion_id, cliente_id, rfc_id, total, saldo, estado, periodicidad, vence_en)
    VALUES
      (:cid, :clid, :rfc, :tot, :sal, 'activa', :per, :vence)
  ");
  $st->execute([
    ':cid'=>$id,
    ':clid'=>$clienteId,
    ':rfc'=>$rfcId,
    ':tot'=>(float)$cot['total'],
    ':sal'=>(float)$cot['total'],
    ':per'=>$periodicidadGlobal,
    ':vence'=>$vence
  ]);
  $ordenId=(int)$pdo->lastInsertId();

  // 5) items
  $it=$pdo->prepare("SELECT id, grupo, opcion, valor FROM cotizacion_items WHERE cotizacion_id=? ORDER BY id ASC");
  $it->execute([$id]);

  $ins=$pdo->prepare("
    INSERT INTO orden_items
      (orden_id, concepto, monto, billing_type, interval_unit, interval_count, next_run, end_at, prorate)
    VALUES
      (:oid, :concepto, :monto, :bt, :iu, :ic, :nr, NULL, 0)
  ");

  foreach($it as $r){
    $grupo = norm($r['grupo'] ?? '');
    $op    = norm($r['opcion'] ?? '');
    $monto = (float)($r['valor'] ?? 0);
    $concepto = trim(($r['grupo'] ?? '').' - '.($r['opcion'] ?? ''));

    // defaults por reglas (tu helper en App/billing_rules.php)
    [$bt,$iu,$ic,$nr] = infer_billing($r, $periodicidadGlobal);

    // override de UI si existe para este grupo
    if (isset($overrides[$grupo]) && is_array($overrides[$grupo])) {
      $ov = $overrides[$grupo];
      $type  = ($ov['type']  ?? $bt) ?: $bt;
      $unit  = $ov['unit']   ?? $iu;
      $count = $ov['count']  ?? $ic;

      // normaliza/valida
      $type  = in_array($type, ['una_vez','recurrente'], true) ? $type : $bt;
      if ($type==='recurrente') {
        if (!in_array($unit, ['mensual','anual'], true)) $unit = 'mensual';
        $count = (int)($count ?? 1);
        if ($unit==='mensual' && $count<1) $count=1;
      } else {
        $unit=null; $count=null;
      }
      // recomputa next_run
      $today = new DateTimeImmutable('today');
      if ($type==='recurrente') {
        if ($unit==='anual')      $nr = $today->modify('+1 year')->format('Y-m-d');
        elseif ($count>=2)        $nr = $today->modify('+'.((int)$count).' month')->format('Y-m-d');
        else                      $nr = $today->modify('+1 month')->format('Y-m-d');
      } else {
        $nr = null;
      }

      $bt=$type; $iu=$unit; $ic=$count;
    }

    // inserta el ítem principal
    $ins->execute([
      ':oid'=>$ordenId,
      ':concepto'=>$concepto,
      ':monto'=>$monto,
      ':bt'=>$bt,
      ':iu'=>$iu,
      ':ic'=>$ic,
      ':nr'=>$nr
    ]);

    // mantenimiento web anual opcional
    if ($grupo==='web' && !empty($overrides['web']['maint'])) {
      $mConcepto = 'Mantenimiento web anual';
      $mMonto    = 2999.00;
      $mType     = 'recurrente';
      $mUnit     = 'anual';
      $mCount    = 1;
      $mNext     = (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d');

      $ins->execute([
        ':oid'=>$ordenId,
        ':concepto'=>$mConcepto,
        ':monto'=>$mMonto,
        ':bt'=>$mType,
        ':iu'=>$mUnit,
        ':ic'=>$mCount,
        ':nr'=>$mNext
      ]);
    }
  }

  $pdo->commit();
  back('Cotización aprobada', true);

} catch(Throwable $e){
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  back($e->getMessage(), false);
}
