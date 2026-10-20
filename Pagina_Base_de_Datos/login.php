<?php
// INICIAR SESIÓN (Súper importante)
session_start();

// Recibir los datos del formulario
$rut = $_POST['rut'] ?? '';
$password = $_POST['password'] ?? '';
$rol = $_POST['rol'] ?? '';

// ==========================================
// CONFIGURACIÓN ORACLE CLOUD
// ==========================================
$usuario_oracle = 'ADMIN';
$clave_oracle = getenv('ORACLE_PASSWORD');
$cadena_conexion = 'tallerdb_low';
$wallet_path = '/var/www/html/Wallet'; 
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
        $sql = "SELECT * FROM ADMINISTRADOR WHERE RUT = :rut AND \"PASSWORD\" = :pass";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':rut', $rut);
        oci_bind_by_name($stid, ':pass', $password);
    } 
    elseif ($rol === 'mecanico') {
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
        $partes = explode('-', $rut);
        $rut_num = $partes[0] ?? '';
        $rut_dv = $partes[1] ?? '';

        $sql = "SELECT * FROM CLIENTE WHERE RUT = :rut AND RUT_DV = :dv AND \"PASSWORD\" = :pass";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':rut', $rut_num);
        oci_bind_by_name($stid, ':dv', $rut_dv);
        oci_bind_by_name($stid, ':pass', $password);
    }

    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC);

    if ($row) {
        // GUARDAMOS LOS DATOS EN LA SESIÓN
        $_SESSION['usuario_rut'] = $row['RUT'];
        $_SESSION['usuario_rol'] = $rol;
        
        // Si es cliente, guardamos el DV también porque lo necesitamos para la consulta
        if ($rol === 'cliente') {
            $_SESSION['usuario_dv'] = $row['RUT_DV']; 
        }

        // Redirigimos
        if ($rol === 'admin') {
            header("Location: vista_admin.html");
        } elseif ($rol === 'mecanico') {
            header("Location: vista_mecanico.html");
        } elseif ($rol === 'cliente') {
            header("Location: vista_cliente.php"); // AHORA ES .PHP
        }
        exit();
    } else {
        echo "<script>alert('RUT o contraseña incorrectos.'); window.location.href = 'index.html';</script>";
    }

} catch (Exception $e) {
    echo "Error en la consulta: " . $e->getMessage();
}
if (isset($stid)) { oci_free_statement($stid); }
oci_close($conn);
?>
