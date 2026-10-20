<?php
session_start();
if (!isset($_SESSION['usuario_rut']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: index.html");
    exit();
}

// Conexión a Oracle
putenv("TNS_ADMIN=/var/www/html/Wallet");
$conn = oci_connect('ADMIN', getenv('ORACLE_PASSWORD'), 'tallerdb_low');

if (!$conn) die("Error de conexión");

// 1. Consultar TODOS los repuestos (Inventario)
$sql_repuestos = "SELECT * FROM REPUESTO ORDER BY nombre"; 
$stid_rep = oci_parse($conn, $sql_repuestos);
oci_execute($stid_rep);
$html_repuestos = "";
while ($row = oci_fetch_array($stid_rep, OCI_ASSOC)) {
    // Asumiendo que tu tabla REPUESTO tiene estas columnas (ajústalas si se llaman distinto)
    $id_rep = $row['ID_REPUESTO'] ?? 'N/A';
    $nombre = $row['NOMBRE'] ?? 'Sin nombre';
    $stock = $row['STOCK'] ?? '0';
    $precio = $row['PRECIO'] ?? '0';
    
    $html_repuestos .= "<tr>
        <td>{$id_rep}</td>
        <td>{$nombre}</td>
        <td>{$stock}</td>
        <td>$" . number_format($precio, 0, ',', '.') . "</td>
    </tr>";
}

// 2. Consultar TODAS las órdenes
$sql_ordenes = "SELECT o.id_orden, o.diagnostico, o.estado, v.marca, v.modelo, v.cliente_rut
                FROM ORDEN o
                JOIN VEHICULO v ON o.vehiculo_patente = v.patente
                ORDER BY o.id_orden DESC";
$stid_ord = oci_parse($conn, $sql_ordenes);
oci_execute($stid_ord);
$html_ordenes = "";
while ($row = oci_fetch_array($stid_ord, OCI_ASSOC)) {
    $estado = strtoupper($row['ESTADO']);
    $badge = $estado == 'FINALIZADA' ? 'badge-ok' : ($estado == 'EN PROCESO' ? 'badge-low' : 'badge-out');
    
    $html_ordenes .= "<tr>
        <td>#{$row['ID_ORDEN']}</td>
        <td>{$row['MARCA']} {$row['MODELO']}</td>
        <td>RUT: {$row['CLIENTE_RUT']}</td>
        <td>{$row['DIAGNOSTICO']}</td>
        <td><span class='badge {$badge}'>{$estado}</span></td>
    </tr>";
}

oci_free_statement($stid_rep);
oci_free_statement($stid_ord);
oci_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Admin</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
    body { background:#f4f6f9; }
    .navbar { background:#1e293b; color:white; padding:20px 40px; display:flex; justify-content:space-between; }
    .container { max-width:1200px; margin:30px auto; padding:0 20px; }
    .panel { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:30px; }
    table { width:100%; border-collapse: collapse; margin-top:15px; }
    th { background:#f1f5f9; padding:12px; text-align:left; font-size:14px; }
    td { padding:12px; border-top:1px solid #e2e8f0; font-size:14px; }
    .badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .badge-ok { background:#d1fae5; color:#065f46; }
    .badge-low { background:#fef3c7; color:#92400e; }
    .badge-out { background:#fee2e2; color:#991b1b; }
    h2 { color:#1e293b; margin-bottom:10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>🔧 Panel de Administración</h2>
    <span>Admin: <strong><?php echo $_SESSION['usuario_rut']; ?></strong></span>
  </div>
  <div class="container">
    
    <div class="panel">
      <h2>📦 Inventario de Repuestos</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>Repuesto</th><th>Stock</th><th>Precio</th></tr>
        </thead>
        <tbody>
          <?php echo $html_repuestos != "" ? $html_repuestos : "<tr><td colspan='4'>No hay repuestos registrados.</td></tr>"; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2>📋 Todas las Órdenes del Taller</h2>
      <table>
        <thead>
          <tr><th>Orden</th><th>Vehículo</th><th>Cliente</th><th>Diagnóstico</th><th>Estado</th></tr>
        </thead>
        <tbody>
          <?php echo $html_ordenes != "" ? $html_ordenes : "<tr><td colspan='5'>No hay órdenes registradas.</td></tr>"; ?>
        </tbody>
      </table>
    </div>

  </div>
</body>
</html>
