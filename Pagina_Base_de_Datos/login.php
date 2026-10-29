<?php
require_once __DIR__ . '/auth.php';
iniciar_sesion_segura();

function volver_al_login(int $codigo): never
{
    header('Location: index.html?error=' . $codigo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$rol      = $_POST['rol'] ?? '';
$rutIn    = (string)($_POST['rut'] ?? '');
$password = (string)($_POST['password'] ?? '');

if (!in_array($rol, ['admin', 'mecanico', 'cliente'], true) || $rutIn === '' || $password === '' || strlen($password) > 200) {
    volver_al_login(1);
}

// Normalizar el RUT: acepta 12.345.678-9, 12345678-9 o 123456789
$rutLimpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rutIn));
if (strlen($rutLimpio) < 2) {
    volver_al_login(1);
}
$rutNum = substr($rutLimpio, 0, -1);
$rutDv  = substr($rutLimpio, -1);

$claveIntentos = $rol . '|' . $rutLimpio;
if (login_bloqueado($claveIntentos)) {
    volver_al_login(2);
}

require_once __DIR__ . '/conexion.php';

try {
    // Se busca solo por RUT; la contraseña se verifica en PHP (nunca se compara en el SQL)
    if ($rol === 'admin') {
        $sql = "SELECT ROWID AS FILA, RUT, \"PASSWORD\" AS CLAVE FROM ADMINISTRADOR
                WHERE REPLACE(REPLACE(UPPER(TO_CHAR(RUT)), '.', ''), ' ', '') IN (:r1, :r2)";
        $params = [':r1' => $rutNum . '-' . $rutDv, ':r2' => $rutNum];
        $tabla = 'ADMINISTRADOR';
    } else {
        $tabla = ($rol === 'mecanico') ? 'MECANICO' : 'CLIENTE';
        $sql = "SELECT ROWID AS FILA, RUT, RUT_DV, \"PASSWORD\" AS CLAVE FROM $tabla
                WHERE TO_CHAR(RUT) = :rut AND UPPER(RUT_DV) = :dv";
        $params = [':rut' => $rutNum, ':dv' => $rutDv];
    }

    $filas = db_consultar($conn, $sql, $params);
    $row = $filas[0] ?? null;

    // Hash falso para que tardar lo mismo exista o no el usuario
    $guardada = $row ? rtrim((string)$row['CLAVE']) : '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
    $esHash = str_starts_with($guardada, '$2y$') || str_starts_with($guardada, '$argon2');

    if ($esHash) {
        $valida = password_verify($password, $guardada);
    } else {
        // Contraseña antigua en texto plano: se acepta una última vez y se convierte en hash
        $valida = $row !== null && hash_equals($guardada, $password);
    }

    if (!$row || !$valida) {
        login_fallo($claveIntentos);
        volver_al_login(1);
    }

    // Migración transparente a hash
    if (!$esHash || password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
        try {
            db_ejecutar(
                $conn,
                "UPDATE $tabla SET \"PASSWORD\" = :h WHERE ROWID = CHARTOROWID(:fila)",
                [':h' => password_hash($password, PASSWORD_DEFAULT), ':fila' => $row['FILA']]
            );
        } catch (Throwable $e) {
            error_log('No se pudo migrar la contraseña a hash: ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Error en login: ' . $e->getMessage());
    volver_al_login(3);
}

login_ok($claveIntentos);
session_regenerate_id(true);

$_SESSION['usuario_rut'] = (string)$row['RUT'];
$_SESSION['usuario_dv']  = $rol === 'admin' ? '' : (string)$row['RUT_DV'];
$_SESSION['usuario_rol'] = $rol;

$destino = [
    'admin'    => 'vista_admin.php',
    'mecanico' => 'vista_mecanico.php',
    'cliente'  => 'vista_cliente.php',
][$rol];

header('Location: ' . $destino);
exit;
