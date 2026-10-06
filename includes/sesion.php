<?php

//N: Carga de dependencias y configuracion de la sesion del usuario, incluyendo seguridad y manejo de cookies

require_once __DIR__ . '/conexion.php';           #Requiere el archivo de funciones para poder usar sus funciones en este archivo, requiere si el archivo no existe falla y si ya fue requerido no lo vuelve a requerir por once
require_once __DIR__ . '/funciones.php';          #Dir devuelve la carpeta actual

date_default_timezone_set('America/Argentina/Buenos_Aires');

ini_set('session.use_strict_mode', '1');   //? Fijacion de la configuracion de la sesion para que solo se acepten identificadores de sesion generados por el servidor, evitando ataques de fijacion de sesion
ini_set('session.use_only_cookies', '1'); //? Fijacion de la configuracion de la sesion para que solo se acepten cookies para almacenar el identificador de sesion, evitando ataques de fijacion de sesion
session_name('schooldefend_sesion');     //? Cambiar el nombre no es una protección fuerte; principalmente permite identificar la cookie del sistema.

session_set_cookie_params([                                                 //* Configuracion de cookies de sesion para mejorar la seguridad y privacidad del usuario      
    'httponly' => true,                                                     //* Indica que JavaScript del navegador no puede leer normalmente la cookie.
    'samesite' => 'Lax',                                                    //* Indica que la cookie solo se enviará en solicitudes de primer nivel, evitando ataques CSRF.
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',   //* Indica que la cookie solo se enviará a través de conexiones HTTPS, evitando ataques de intercepción de cookies.
]);

session_start();                   //N2: Busca la cookie y carga sus datos en el array $_SESSION. carga la sesion del usuario si existe, sino crea una nueva sesion.
header('Cache-Control: no-store'); //N2: Evita que el navegador almacene en caché las páginas, lo que ayuda a proteger la información sensible del usuario.

function cerrarSesion()    //N3: Funcion para cerrar la sesion del usuario, destruyendo la sesion y eliminando la cookie de sesion
{ 
    $_SESSION = [];                                       //?: Vacía el array, eliminando todos los datos de la sesión.
    $configuracionCookie = session_get_cookie_params();   //?: Obtiene la configuración de la cookie de sesión actual, incluyendo el path, dominio y seguridad.
    setcookie(session_name(), '', [                       //?: Vencimiento de la cookie de sesión, eliminando la cookie del navegador.
        'expires' => time() - 3600,
        'path' => $configuracionCookie['path'],
        'domain' => $configuracionCookie['domain'],
        'secure' => $configuracionCookie['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();                                   //?: Destruye la sesión en el servidor, eliminando todos los datos asociados a la sesión.
}

function usuarioActual()  //N3: Funcion para obtener el usuario actual de la sesion, si no hay usuario logueado devuelve null
{
    if (empty($_SESSION['id_usuario'])) {      //?: Si empty es verdadero significa que no hay usuario, es null o str, false o 0
        return null;
    }

    $pdo = getConexion();         //n2: Consuta a la base de datos para obtener la conexion PDO, que permite ejecutar consultas SQL y obtener resultados de manera segura y eficiente.
    $sqlUsuario = "               
        SELECT id_usuario, nombre, nombre_usuario, rol
        FROM usuarios
        WHERE id_usuario = ?
          AND activo = 1";

    $consultaUsuario = $pdo->prepare($sqlUsuario);            //n4: Prepara la consulta SQL para su ejecución, evitando inyecciones SQL y mejorando el rendimiento al reutilizar la consulta preparada.
    $consultaUsuario->execute([$_SESSION['id_usuario']]);     //n4: Ejecuta la consulta SQL con el parámetro del ID de usuario almacenado en la sesión, obteniendo los datos del usuario actual de la base de datos.
    $usuario = $consultaUsuario->fetch();                     //n4: Obtiene la primera fila de resultados de la consulta como un array asociativo, que contiene los datos del usuario actual.
 
    if (!$usuario || !in_array($usuario['rol'], ['administrador', 'tecnico', 'profesor'], true)) { //*: Verifica si el usuario no existe o el rol del usuario no está en la lista de roles permitidos, en cuyo caso se cierra la sesión y se devuelve null.
        cerrarSesion();
        return null;
    }

    return $usuario; //* Sino devuelve el array asociativo con los datos del usuario actual, incluyendo id_usuario, nombre, nombre_usuario y rol.
}

function exigirSesion($rutaLogin = 'login.php')  //N3: Funcion sobre autenticacion de usuario, que verifica si hay un usuario logueado y si no lo hay redirige a la pagina de login (Protege a las paginas que requieren autenticacion)
{
    $usuario = usuarioActual();             //n2: llama a la funcion usuarioActual para obtener el usuario actual de la sesion, si no hay usuario logueado devuelve null
 
    if ($usuario === null) {                //n2: Si no hay usuario logueado, redirige a la pagina de login y termina la ejecucion del script
        http_response_code(302);            //n2: Establece el código de respuesta HTTP 302 (Found), que indica una redirección temporal a otra URL.
        header('Location: ' . $rutaLogin);  //n2: Redirecciona al usuario a la localizacion
        exit;
    }

    return $usuario;                       //n2: Si hay usuario logueado, devuelve el array asociativo con los datos del usuario actual
}

function exigirRoles($usuario, $rolesPermitidos)   //N3: Funcion sobre autorizacion de usuario 
{
    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {       //n2: verifica si el usuario actual tiene un rol permitido, rechaza el acceso pero no cierra la sesion
        http_response_code(403);                                    //n2: Establece el código de respuesta HTTP 403 (Forbidden), que indica que el servidor entiende la solicitud pero se niega a autorizarla.
        exit('No tenés permiso para realizar esta operación.');     //n2: Muestra un mensaje de error al usuario y termina la ejecución del script.  
    }
}