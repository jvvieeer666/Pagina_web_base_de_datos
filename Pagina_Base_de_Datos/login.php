<?php
// Recibir los datos del formulario
$rut = $_POST['rut'] ?? '';
$password = $_POST['password'] ?? '';
$rol = $_POST['rol'] ?? '';

// ==========================================
// CONFIGURACIÓN ORACLE CLOUD
// ==========================================
$usuario_oracle = 'ADMIN';
$clave_oracle = getenv('ORACLE_PASSWORD'); // Lee la variable de entorno
$cadena_conexion = 'tallerdb_low';
$wallet_path = getenv('TNS_ADMIN'); // Lee la ruta de la wallet
putenv("TNS_ADMIN=" . $wallet_path);

// Conectar a Oracle Cloud
$conn = oci_connect($usuario_oracle, $clave_oracle, $cadena_conexion);

if (!$conn) {
    $e = oci_error();
    die("Error de conexión a Oracle Cloud: " . $e['message']);
}

// ==========================================
// LÓGICA DE AUTENTICACIÓN
// ==========================================

try {
    if ($rol === 'admin') {
        // En ADMINISTRADOR el RUT está junto con guion. OJO: PASSWORD va con comillas dobles y escapadas \"
        $sql = "SELECT * FROM ADMINISTRADOR WHERE RUT = :rut AND \"PASSWORD\" = :pass";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':rut', $rut);
        oci_bind_by_name($stid, ':pass', $password);
    } 
    elseif ($rol === 'mecanico') {
        // En MECANICO el RUT está separado
        $partes = explode('-', $rut);
        $rut_num = $partes[0] ?? '';
        $rut_dv = $partes[1] ?? '';

        $sql = "SELECT * FROM MECANICO WHERE RUT = :rut AND RUT_DV = :dv AND \"PASSWORD\" = :pass";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':rut', $rut_num);
        oci_bind_by_name($stid, ':dv', $rut_dv);
        oci_bind_by_name($stid, ':pass', $password);
    } 
    elseif ($rol === 'cliente') {
        // En CLIENTE el RUT está separado
        $partes = explode('-', $rut);
        $rut_num = $partes[0] ?? '';
        $rut_dv = $partes[1] ?? '';

        $sql = "SELECT * FROM CLIENTE WHERE RUT = :rut AND RUT_DV = :dv AND \"PASSWORD\" = :pass";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':rut', $rut_num);
        oci_bind_by_name($stid, ':dv', $rut_dv);
        oci_bind_by_name($stid, ':pass', $password);
    }

    // Ejecutar
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC);

    if ($row) {
        // ¡Login correcto!
        if ($rol === 'admin') {
            header("Location: admin.html");
        } elseif ($rol === 'mecanico') {
            header("Location: mecanico.html");
        } elseif ($rol === 'cliente') {
            header("Location: cliente.html");
        }
        exit();
    } else {
        // Login incorrecto
        echo "<script>
                alert('RUT o contraseña incorrectos. Verifica tus datos.');
                window.location.href = 'index.html';
              </script>";
    }

} catch (Exception $e) {
    echo "Error en la consulta: " . $e->getMessage();
}

if (isset($stid)) { oci_free_statement($stid); }
oci_close($conn);
?>
