<?php
session_start();

// Si no hay sesión, lo devolvemos al login
if (!isset($_SESSION['usuario_rut'])) {
    header("Location: index.html");
    exit();
}

$rut_cliente = $_SESSION['usuario_rut'];

// Conexión a Oracle
$conn = oci_connect('ADMIN', getenv('ORACLE_PASSWORD'), 'tallerdb_low');
putenv("TNS_ADMIN=/var/www/html/Wallet");

if (!$conn) {
    die("Error de conexión");
}

// CONSULTA SQL: Traer las órdenes de este cliente
$sql = "SELECT o.id_orden, o.diagnostico, o.estado, v.marca, v.modelo, o.vehiculo_patente
        FROM ORDEN o
        JOIN VEHICULO v ON o.vehiculo_patente = v.patente
        WHERE v.cliente_rut = :rut
        ORDER BY o.id_orden DESC";

$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':rut', $rut_cliente);
oci_execute($stid);

// Variable para acumular el HTML de las órdenes
$html_ordenes = "";

// Recorremos los resultados de Oracle
while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $id = $row['ID_ORDEN'];
    $diag = $row['DIAGNOSTICO'];
    $estado = $row['ESTADO'];
    $auto = $row['MARCA'] . ' ' . $row['MODELO'];
    $patente = $row['VEHICULO_PATENTE'];

    // Lógica para los colores del estado
    $clase_estado = 'estado-pendiente';
    $color_borde = '#f59e0b';
    if (strtoupper($estado) == 'FINALIZADA' || strtoupper($estado) == 'COMPLETADA') {
        $clase_estado = 'estado-completada';
        $color_borde = '#10b981';
    } elseif (strtoupper($estado) == 'EN PROCESO') {
        $clase_estado = 'estado-proceso';
        $color_borde = '#3b82f6';
    }

    // Creamos la tarjeta de la orden
    $html_ordenes .= "
    <div class='orden' style='border-left: 4px solid {$color_borde};'>
        <strong>Orden #{$id}</strong> - {$auto} ({$patente})<br>
        {$diag}<br>
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
  <title>Mi Taller - Cliente</title>
  <style>
    /* Tus estilos de antes */
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
    body { background:#f0f4f8; }
    .navbar { background:#1e293b; color:white; padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
    .container { max-width:1200px; margin:30px auto; padding:0 20px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .panel { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    .panel h2 { margin-bottom:15px; color:#1e293b; }
    .orden { padding:15px; background:#f8fafc; margin-bottom:12px; border-radius:6px; }
    .estado { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; margin-top:8px; }
    .estado-pendiente { background:#fef3c7; color:#92400e; }
    .estado-proceso { background:#dbeafe; color:#1e40af; }
    .estado-completada { background:#d1fae5; color:#065f46; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>🔧 TallerPro</h2>
    <span>Bienvenido, <strong>Cliente #<?php echo $rut_cliente; ?></strong></span>
  </div>
  <div class="container">
    <div class="grid">
      <div class="panel">
        <h2>🚗 Mis Vehículos</h2>
        <!-- Aquí podríamos hacer otra consulta SQL para traer los vehículos -->
        <p><em>Consulta de vehículos pendiente...</em></p>
      </div>
      <div class="panel">
        <h2>📋 Mis Órdenes</h2>
        <?php 
            // Si hay órdenes las mostramos, si no, mostramos un mensaje
            if ($html_ordenes != "") {
                echo $html_ordenes;
            } else {
                echo "<p>No tienes órdenes registradas aún.</p>";
            }
        ?>
      </div>
    </div>
  </div>
</body>
</html>
