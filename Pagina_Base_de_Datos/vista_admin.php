<?php
require_once __DIR__ . '/auth.php';
requerir_rol('admin');
require_once __DIR__ . '/conexion.php';

$error = '';
$repuestos = [];
$ordenes = [];

try {
    $repuestos = db_consultar($conn, "SELECT * FROM REPUESTO ORDER BY nombre");
    $ordenes = db_consultar($conn,
        "SELECT o.id_orden, o.diagnostico, o.estado, v.marca, v.modelo, v.cliente_rut
         FROM ORDEN o
         JOIN VEHICULO v ON o.vehiculo_patente = v.patente
         ORDER BY o.id_orden DESC");
} catch (Throwable $e) {
    error_log('vista_admin: ' . $e->getMessage());
    $error = 'No se pudieron cargar los datos. Intenta nuevamente.';
}

oci_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Admin</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
    body { background:#f4f6f9; }
    .navbar { background:#1e293b; color:white; padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
    .navbar a { color:#cbd5e1; margin-left:20px; font-size:14px; }
    .container { max-width:1200px; margin:30px auto; padding:0 20px; }
    .panel { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:30px; overflow-x:auto; }
    table { width:100%; border-collapse: collapse; margin-top:15px; }
    th { background:#f1f5f9; padding:12px; text-align:left; font-size:14px; }
    td { padding:12px; border-top:1px solid #e2e8f0; font-size:14px; }
    .badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .badge-ok { background:#d1fae5; color:#065f46; }
    .badge-low { background:#fef3c7; color:#92400e; }
    .badge-out { background:#fee2e2; color:#991b1b; }
    .error { background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
    h2 { color:#1e293b; margin-bottom:10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .navbar h2 { color:white; border:none; padding:0; margin:0; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>🔧 Panel de Administración</h2>
    <span>Admin: <strong><?= h($_SESSION['usuario_rut']) ?></strong> <a href="logout.php">Cerrar sesión</a></span>
  </div>
  <div class="container">
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <div class="panel">
      <h2>📦 Inventario de Repuestos</h2>
      <table>
        <thead><tr><th>ID</th><th>Repuesto</th><th>Stock</th><th>Precio</th></tr></thead>
        <tbody>
          <?php foreach ($repuestos as $r): ?>
            <tr>
              <td><?= h($r['ID_REPUESTO'] ?? 'N/A') ?></td>
              <td><?= h($r['NOMBRE'] ?? 'Sin nombre') ?></td>
              <td><?= h($r['STOCK'] ?? '0') ?></td>
              <td>$<?= h(number_format((float)($r['PRECIO'] ?? 0), 0, ',', '.')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$repuestos): ?><tr><td colspan="4">No hay repuestos registrados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2>📋 Todas las Órdenes del Taller</h2>
      <table>
        <thead><tr><th>Orden</th><th>Vehículo</th><th>Cliente</th><th>Diagnóstico</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($ordenes as $o):
              $estado = strtoupper(trim((string)$o['ESTADO']));
              $badge = $estado === 'FINALIZADA' ? 'badge-ok' : ($estado === 'EN PROCESO' ? 'badge-low' : 'badge-out');
          ?>
            <tr>
              <td>#<?= h($o['ID_ORDEN']) ?></td>
              <td><?= h($o['MARCA']) ?> <?= h($o['MODELO']) ?></td>
              <td>RUT: <?= h($o['CLIENTE_RUT']) ?></td>
              <td><?= h($o['DIAGNOSTICO']) ?></td>
              <td><span class="badge <?= h($badge) ?>"><?= h($estado) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ordenes): ?><tr><td colspan="5">No hay órdenes registradas.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
