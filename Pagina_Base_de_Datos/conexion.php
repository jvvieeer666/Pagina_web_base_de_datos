<?php
// ==========================================
// CONEXIÓN ORACLE CLOUD (WALLET / TCPS)
// ==========================================

// Iniciar búfer por si acaso alguna advertencia intenta imprimirse antes de tiempo
if (ob_get_level() == 0) {
    ob_start();
}

function db_fatal(string $detalle): never
{
    error_log('Oracle Error: ' . $detalle);
    http_response_code(500);
    exit('Error de conexión a la base de datos. Contacte al administrador.');
}

$usuario = getenv('ORACLE_USER') ?: 'ADMIN';
$clave   = getenv('ORACLE_PASSWORD');

if (!$clave) {
    db_fatal('Falta configurar la variable de entorno ORACLE_PASSWORD en Render.');
}

// Cadena de conexión directa especificando la ruta del Wallet
$cadena = "(description= (retry_count=20)(retry_delay=3)"
        . "(address=(protocol=tcps)(port=1522)(host=adb.sa-santiago-1.oraclecloud.com))"
        . "(connect_data=(service_name=g5c7ec52ab3e75c_tallerdb_low.adb.oraclecloud.com))"
        . "(security=(MY_WALLET_DIRECTORY=/opt/oracle/wallet)(ssl_server_dn_match=yes)))";

$conn = @oci_connect($usuario, $clave, $cadena, 'AL32UTF8');

if (!$conn) {
    $e = oci_error();
    db_fatal($e['message'] ?? 'Error desconocido al conectar a la base de datos.');
}

// Esquema opcional
$esquema = getenv('ORACLE_SCHEMA');
if ($esquema) {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_$#]{0,29}$/', $esquema)) {
        db_fatal('ORACLE_SCHEMA no es un nombre válido');
    }
    $st = @oci_parse($conn, 'ALTER SESSION SET CURRENT_SCHEMA = ' . $esquema);
    if (!$st || !@oci_execute($st)) {
        $e = $st ? oci_error($st) : oci_error($conn);
        db_fatal('No se pudo fijar el esquema: ' . ($e['message'] ?? ''));
    }
    oci_free_statement($st);
}

// ---------- Ayudantes de consulta ----------

function db_consultar($conn, string $sql, array $params = []): array
{
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'Error al preparar la consulta.');
    }

    // Binds seguros: pasando el arreglo por referencia viva
    foreach (array_keys($params) as $nombre) {
        oci_bind_by_name($stid, $nombre, $params[$nombre]);
    }

    if (!@oci_execute($stid)) {
        $e = oci_error($stid);
        oci_free_statement($stid);
        throw new RuntimeException($e['message'] ?? 'Error al ejecutar la consulta.');
    }

    $filas = [];
    oci_fetch_all($stid, $filas, 0, -1, OCI_FETCHSTATEMENT_BY_ROW | OCI_ASSOC);
    oci_free_statement($stid);

    return $filas;
}

function db_ejecutar($conn, string $sql, array $params = []): int
{
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'Error al preparar la sentencia.');
    }

    foreach (array_keys($params) as $nombre) {
        oci_bind_by_name($stid, $nombre, $params[$nombre]);
    }

    if (!@oci_execute($stid)) {
        $e = oci_error($stid);
        oci_free_statement($stid);
        throw new RuntimeException($e['message'] ?? 'Error al ejecutar la sentencia.');
    }

    $afectadas = oci_num_rows($stid);
    oci_free_statement($stid);

    return (int)$afectadas;
}
