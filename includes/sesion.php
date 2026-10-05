<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';

date_default_timezone_set('America/Argentina/Buenos_Aires'); // Fijar hora local a la de ARG.

// -- Seguridad de sesión --
// - use_strict_mode: evita session fixation (rechaza IDs generados afuera) (Buscar para mas info).
// - use_only_cookies: el ID nunca viaja en la URL (?PHPSESSID=...)
// - session_name: cambia el nombre de la cookie de sesión (por defecto: PHPSESSID) por uno custom. 
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('schooldefend_sesion'); 

// Parametros de la cookie
session_set_cookie_params([
    'httponly' => true, // Cookie no accesible desde JS. Bloquea ataques XSS (Buscar para mas info).
    'samesite' => 'Lax', // Evita CSRF (Si otro sitio manda request, Cookie no va adjunta)
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // Solo envía la cookie por HTTPS 
    // (con la condicion, el sistema funcionara tambien en local [http])
]);
session_start();
header('Cache-Control: no-store'); // No deja al navegador guardar la pagina en cache

function cerrarSesion()
{
    $_SESSION = [];

    // - Pide a PHP los mismos parámetros del arrance. 
    //   para borrar la cookie, con las mismas condiciones.
    $configuracionCookie = session_get_cookie_params(); 
    setcookie(session_name(), '', [
        // Al ponerle una fecha en el pasado (time() - 3600), el navegador la expira inmediatamente.
        // "Borrar la cookie de sesion ahora mismo"
        'expires' => time() - 3600, 
        'path' => $configuracionCookie['path'],
        'domain' => $configuracionCookie['domain'],
        'secure' => $configuracionCookie['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function usuarioActual()
{   
    // Si no hay usuario logeado, corta la ejecucion de la funcion
    if (empty($_SESSION['id_usuario'])) {
        return null;
    }
    
    // - Valida contra BD en cada request para que bajas de usuario 
    //   o cambios de rol apliquen al instante sin esperar a que expire la sesión
    $pdo = getConexion();
    $sqlUsuario = "
        SELECT id_usuario, nombre, nombre_usuario, rol
        FROM usuarios
        WHERE id_usuario = ? AND activo = 1";

    $consultaUsuario = $pdo->prepare($sqlUsuario);
    $consultaUsuario->execute([$_SESSION['id_usuario']]);
    $usuario = $consultaUsuario->fetch();

    $roles = ['administrador', 'tecnico', 'profesor'];
    if (!$usuario || !in_array($usuario['rol'], $roles, true)) {
        cerrarSesion();
        return null;
    }

    return $usuario;
}

function exigirSesion($rutaLogin = 'login.php')
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        header('Location: ' . $rutaLogin);
        exit;
    }

    return $usuario;
}

// Cada futura página restringida llamará esta función, no solo ocultará botones.
function exigirRoles($usuario, $rolesPermitidos)
{
    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        exit('No tenés permiso para realizar esta operación.');
    }
}


