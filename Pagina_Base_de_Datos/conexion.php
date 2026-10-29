<?php
// ==========================================
// CONEXIÓN DIRECTA ORACLE CLOUD (SIN WALLET)
// ==========================================

function db_fatal(string $detalle): never
{
    error_log('Oracle: ' . $detalle);
    http_response_code(500);
    exit('Error Oracle: ' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8'));
}

$usuario  = getenv('ORACLE_USER') ?: 'ADMIN';
$clave    = getenv('ORACLE_PASSWORD');
$host     = getenv('ORACLE_HOST') ?: 'adb.sa-santiago-1.oraclecloud.com';
$servicio = getenv('ORACLE_SERVICE_NAME') ?: 'g5c7ec52ab3e75c_tallerdb_low.adb.oraclecloud.com';

if (!$clave) {
    db_fatal('Falta configurar la variable de entorno ORACLE_PASSWORD en Render.');
}

$cadena = "(DESCRIPTION="
        . "(ADDRESS=(PROTOCOL=tcps)(HOST=$host)(PORT=1522))"
        . "(CONNECT_DATA=(SERVICE_NAME=$servicio)))";

$conn = @oci_connect($usuario, $clave, $cadena, 'AL32UTF8');

if (!$conn) {
    $e = oci_error();
    db_fatal($e['message'] ?? 'Error desconocido al conectar a la base de datos.');
}

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
}

// ---------- Ayudantes de consulta corregidos (sin error de bind) ----------

function db_consultar($conn, string $sql, array $params = []): array
{
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'Error al preparar la consulta.');
    }

    // Mantener referencias vivas para oci_bind_by_name
    foreach ($params as $nombre => $valor) {
        // Aseguramos que pasamos la variable por referencia correctamente
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
.    }

    foreach ($params as $nombre => $valor) {
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
