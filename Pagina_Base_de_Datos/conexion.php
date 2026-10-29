<?php
// ==========================================
// CONEXIÓN ORACLE CLOUD (CON WALLET / TCPS)
// ==========================================
// Requiere en el contenedor:
//   - TNS_ADMIN=/opt/oracle/wallet
//   - /opt/oracle/wallet/{cwallet.sso, ewallet.pem, tnsnames.ora, sqlnet.ora}
//   - sqlnet.ora con DIRECTORY="/opt/oracle/wallet"
// ==========================================

function db_fatal(string $detalle): never
{
    error_log('Oracle: ' . $detalle);
    http_response_code(500);
    exit('Error Oracle: ' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8'));
}

$usuario = getenv('ORACLE_USER') ?: 'ADMIN';
$clave   = getenv('ORACLE_PASSWORD');

// Alias del tnsnames.ora del wallet (NO el descriptor manual)
// Ejemplo típico en ADB: "tallerdb_low", "tallerdb_high", "tallerdb_tp", etc.
$alias   = getenv('ORACLE_TNS_ALIAS') ?: 'tallerdb_low';

if (!$clave) {
    db_fatal('Falta configurar la variable de entorno ORACLE_PASSWORD en Render.');
}

if (!getenv('TNS_ADMIN')) {
    db_fatal('Falta TNS_ADMIN (debe apuntar al directorio del wallet, ej. /opt/oracle/wallet).');
}

$conn = @oci_connect($usuario, $clave, $alias, 'AL32UTF8');

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

    // Binds por REFERENCIA: la variable debe seguir viva hasta oci_execute
    foreach ($params as $nombre => &$valor) {
        oci_bind_by_name($stid, $nombre, $valor);
    }
    unset($valor); // romper la referencia al salir del bucle

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

    foreach ($params as $nombre => &$valor) {
        oci_bind_by_name($stid, $nombre, $valor);
    }
    unset($valor);

    if (!@oci_execute($stid)) {
        $e = oci_error($stid);
        oci_free_statement($stid);
        throw new RuntimeException($e['message'] ?? 'Error al ejecutar la sentencia.');
    }

    $afectadas = oci_num_rows($stid);
    oci_free_statement($stid);

    return (int)$afectadas;
}
