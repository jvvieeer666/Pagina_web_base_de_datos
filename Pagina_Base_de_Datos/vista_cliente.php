<?php
require_once __DIR__ . '/auth.php';
requerir_rol('cliente');
require_once __DIR__ . '/conexion.php';

$rut_cliente = $_SESSION['usuario_rut'];
$dv_cliente  = $_SESSION['usuario_dv'] ?? '';
$error = '';
$ordenes = [];
$vehiculos = [];

try {
    $ordenes = db_consultar($conn,
        "SELECT o.id_orden, o.diagnostico, o.estado, v.marca, v.modelo, o.vehiculo_patente
         FROM ORDEN o
         JOIN VEHICULO v ON o.vehiculo_patente = v.patente
         WHERE v.cliente_rut = :rut
         ORDER BY o.id_orden DESC",
        [':rut' => $rut_cliente]);

    $vehiculos = db_consultar($conn,
        "SELECT patente, marca, modelo FROM VEHICULO WHERE cliente_rut = :rut ORDER BY patente",
        [':rut' => $rut_cliente]);
} catch (Throwable $e) {
    error_log('vista_cliente: ' . $e->getMessage());
    $error = 'No se pudieron cargar tus datos. Intenta nuevamente.';
}

oci_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Taller - Cliente</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
    body { background:#f0f4f8; }
    .navbar { background:#1e293b; color:white; padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
    .navbar a { color:#cbd5e1; margin-left:20px; font-size:14px; }
    .container { max-width:1200px; margin:30px auto; padding:0 20px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media (max-width:800px) { .grid { grid-template-columns:1fr; } }
    .panel { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    .panel h2 { margin-bottom:15px; color:#1e293b; }
    .orden { padding:15px; background:#f8fafc; margin-bottom:12px; border-radius:6px; }
    .estado { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; margin-top:8px; }
    .estado-pendiente { background:#fef3c7; color:#92400e; }
    .estado-proceso { background:#dbeafe; color:#1e40af; }
    .estado-completada { background:#d1fae5; color:#065f46; }
    .error { background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>🔧 TallerPro</h2>
    <span>Bienvenido, <strong>Cliente <?= h($rut_cliente) ?><?= $dv_cliente !== '' ? '-' . h($dv_cliente) : '' ?></strong>
      <a href="logout.php">Cerrar sesión</a></span>
  </div>
  <div class="container">
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <div class="grid">
      <div class="panel">
        <h2>🚗 Mis Vehículos</h2>
        <?php foreach ($vehiculos as $v): ?>
          <div class="orden"><strong><?= h($v['PATENTE']) ?></strong> - <?= h($v['MARCA']) ?> <?= h($v['MODELO']) ?></div>
        <?php endforeach; ?>
        <?php if (!$vehiculos): ?><p>No tienes vehículos registrados aún.</p><?php endif; ?>
      </div>
      <div class="panel">
        <h2>📋 Mis Órdenes</h2>
        <?php foreach ($ordenes as $o):
            $estado = strtoupper(trim((string)$o['ESTADO']));
            $clase = 'estado-pendiente'; $borde = '#f59e0b';
            if ($estado === 'FINALIZADA' || $estado === 'COMPLETADA') { $clase = 'estado-completada'; $borde = '#10b981'; }
            elseif ($estado === 'EN PROCESO') { $clase = 'estado-proceso'; $borde = '#3b82f6'; }
        ?>
          <div class="orden" style="border-left: 4px solid <?= h($borde) ?>;">
            <strong>Orden #<?= h($o['ID_ORDEN']) ?></strong> - <?= h($o['MARCA']) ?> <?= h($o['MODELO']) ?> (<?= h($o['VEHICULO_PATENTE']) ?>)<br>
            <?= h($o['DIAGNOSTICO']) ?><br>
            <span class="estado <?= h($clase) ?>"><?= h($estado) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$ordenes): ?><p>No tienes órdenes registradas aún.</p><?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
