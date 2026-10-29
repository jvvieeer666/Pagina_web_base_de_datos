<?php
// Utilidades de sesión y seguridad compartidas por todas las páginas.

const LOGIN_MAX_INTENTOS = 5;      // intentos fallidos permitidos
const LOGIN_BLOQUEO_SEG  = 900;    // 15 minutos de bloqueo
const SESION_MAX_INACTIVA = 1800; // 30 minutos sin actividad

// Escapa texto antes de mostrarlo en HTML (evita XSS)
function h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function es_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    return false;
}

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Cabeceras de seguridad básicas
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
    }

    $is_secure = es_https();

    // Configuración segura de la cookie de sesión adaptada a Render
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    
    session_start();

    if (isset($_SESSION['ultimo_acceso']) && time() - $_SESSION['ultimo_acceso'] > SESION_MAX_INACTIVA) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    $_SESSION['ultimo_acceso'] = time();
}

// Exige sesión iniciada con el rol indicado; si no, vuelve al login
function requerir_rol(string $rol): void
{
    iniciar_sesion_segura();

    if (empty($_SESSION['usuario_rut']) || ($_SESSION['usuario_rol'] ?? '') !== $rol) {
        header('Location: index.php'); // Asegurado a .php o la ruta de tu login
        exit;
    }
}

// ---------- Límite de intentos de login ----------
function _archivo_intentos(string $clave): string
{
    return sys_get_temp_dir() . '/login_' . hash('sha256', $clave) . '.json';
}

function _leer_intentos(string $clave): array
{
    $f = _archivo_intentos($clave);
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    return is_array($d) ? $d : ['n' => 0, 't' => time()];
}

function login_bloqueado(string $clave): bool
{
    $d = _leer_intentos($clave);
    if (time() - ($d['t'] ?? 0) > LOGIN_BLOQUEO_SEG) {
        @unlink(_archivo_intentos($clave));
        return false;
    }
    return ($d['n'] ?? 0) >= LOGIN_MAX_INTENTOS;
}

function login_fallo(string $clave): void
{
    $d = _leer_intentos($clave);
    if (time() - ($d['t'] ?? 0) > LOGIN_BLOQUEO_SEG) {
        $d = ['n' => 0, 't' => time()];
    }
    $d['n'] = ($d['n'] ?? 0) + 1;
    $d['t'] = time();
    @file_put_contents(_archivo_intentos($clave), json_encode($d), LOCK_EX);
}

function login_ok(string $clave): void
{
    @unlink(_archivo_intentos($clave));
}
