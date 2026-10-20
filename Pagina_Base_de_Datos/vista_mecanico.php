<?php
session_start();
if (!isset($_SESSION['usuario_rut']) || $_SESSION['usuario_rol'] !== 'mecanico') {
    header("Location: index.html");
    exit();
}

$rut_mecanico = $_SESSION['usuario_rut'];

// Conexión a Oracle
putenv("TNS_ADMIN=/var/www/html/Wallet");
$conn = oci_connect('ADMIN', getenv('ORACLE_PASSWORD'), 'tallerdb_low');

if (!$conn) die("Error de conexión");

// Consulta: Órdenes asignadas a este mecánico
$sql = "SELECT o.id_orden, o.diagnostico, o.estado, v.marca, v.modelo, o.vehiculo_patente
        FROM ORDEN o
        JOIN VEHICULO v ON o.vehiculo_patente = v.patente
        WHERE o.mecanico_rut = :rut
        ORDER BY o.id_orden DESC";

$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':rut', $rut_mecanico);
oci_execute($stid);

$html_ordenes = "";
$total = 0;

while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $total++;
    $id = $row['ID_ORDEN'];
    $diag = $row['DIAGNOSTICO'];
    $estado = strtoupper($row['ESTADO']);
    $auto = $row['MARCA'] . ' ' . $row['MODELO'];
    $patente = $row['VEHICULO_PATENTE'];

    $clase_estado = 'estado-pendiente';
    $color_borde = '#f59e0b';
    if ($estado == 'FINALIZADA' || $estado == 'COMPLETADA') {
        $clase_estado = 'estado-completada';
        $color_borde = '#10b981';
    } elseif ($estado == 'EN PROCESO') {
        $clase_estado = 'estado-proceso';
        $color_borde = '#3b82f6';
    }

    $html_ordenes .= "
    <div class='orden' style='border-left: 4px solid {$color_borde};'>
        <strong>Orden #{$id}</strong> - {$auto} ({$patente})<br>
        <b>Falla reportada:</b> {$diag}<br>
        <span class='estado {$clase_estado}'>{$estado}</span>
    </div>";
}
oci_free_statement($stid);
oci_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Mecánico</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
    body { background:#f0f4f8; }
    .navbar { background:#0f766e; color:white; padding:20px 40px; display:flex; justify-content:space-between; }
    .container { max-width:900px; margin:30px auto; padding:0 20px; }
    .cards { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:30px; }
    .card { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    .card h3 { color:#64748b; font-size:14px; margin-bottom:8px; }
    .card .num { font-size:28px; font-weight:bold; color:#0f766e; }
    .orden { background:white; padding:20px; border-radius:12px; margin-bottom:15px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    .estado { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; margin-top:8px; }
    .estado-pendiente { background:#fef3c7; color:#92400e; }
    .estado-proceso { background:#dbeafe; color:#1e40af; }
    .estado-completada { background:#d1fae5; color:#065f46; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>🔧 Panel Mecánico</h2>
    <span>Mecánico: <strong>RUT <?php echo $rut_mecanico; ?></strong></span>
  </div>
  <div class="container">
    <div class="cards">
      <div class="card"><h3>Órdenes Asignadas</h3><div class="num"><?php echo $total; ?></div></div>
    </div>
    <h2 style="margin-bottom:20px;">Mis Órdenes de Trabajo</h2>
    <?php echo $html_ordenes != "" ? $html_ordenes : "<p>No tienes órdenes asignadas.</p>"; ?>
  </div>
</body>
</html>
