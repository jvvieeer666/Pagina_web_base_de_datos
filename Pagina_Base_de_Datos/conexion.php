<?php
// Conexión única a Oracle Autonomous Database. Todas las páginas hacen require_once de este archivo.

$wallet = getenv('TNS_ADMIN') ?: '/opt/oracle/wallet';
if (!is_dir($wallet) && is_dir(__DIR__ . '/Wallet')) {
    $wallet = __DIR__ . '/Wallet'; // solo para pruebas locales
}
putenv('TNS_ADMIN=' . $wallet);

function db_fatal(string $detalle): never
{
    error_log('Oracle: ' . $detalle);
    http_response_code(500);
    exit(getenv('APP_DEBUG') === '1'
        ? 'Error Oracle: ' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8')
        : 'No se pudo conectar con la base de datos.');
}

$usuario  = getenv('ORACLE_USER');
$clave    = getenv('ORACLE_PASSWORD');
$host     = getenv('ORACLE_HOST') ?: 'adb.sa-santiago-1.oraclecloud.com';
$servicio = getenv('ORACLE_SERVICE_NAME') ?: 'g5c7ec52ab3e75c_tallerdb_low.adb.oraclecloud.com';

if (!$usuario || !$clave) {
    db_fatal('Faltan las variables ORACLE_USER / ORACLE_PASSWORD');
}

$cadena = "(DESCRIPTION=(ADDRESS=(PROTOCOL=tcps)(HOST=$host)(PORT=1522))"
        . "(CONNECT_DATA=(SERVICE_NAME=$servicio))"
        . "(SECURITY=(SSL_SERVER_DN_MATCH=yes)))";

$conn = @oci_connect($usuario, $clave, $cadena, 'AL32UTF8');

if (!$conn) {
    $e = oci_error();
    db_fatal(($e['message'] ?? 'error desconocido') . ' | wallet: ' . $wallet);
}

// Si la app usa un usuario propio (APP_TALLER), las tablas se resuelven en el esquema dueño (ADMIN)
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

// ---------- Ayudantes de consulta (siempre con parámetros enlazados) ----------

// SELECT: devuelve un arreglo de filas asociativas (claves en MAYÚSCULAS)
function db_consultar($conn, string $sql, array $params = []): array
{
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'Error al preparar la consulta.');
    }

    $vars = $params;
    foreach ($vars as $nombre => $_) {
        oci_bind_by_name($stid, $nombre, $vars[$nombre]);
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

// INSERT / UPDATE / DELETE: devuelve las filas afectadas (confirma automáticamente)
function db_ejecutar($conn, string $sql, array $params = []): int
{
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'Error al preparar la sentencia.');
    }

    $vars = $params;
    foreach ($vars as $nombre => $_) {
        oci_bind_by_name($stid, $nombre, $vars[$nombre]);
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
