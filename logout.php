<?php

//N: Bloque 1: cargar y comprobar la sesión del usuario

require_once __DIR__ . '/includes/sesion.php'; //N2: Inicia la sesión y carga las funciones compartidas de conexión, seguridad y autenticación.

//N: Bloque 2: permitir el cierre de sesión únicamente mediante POST

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');            //?: Informa al cliente que este recurso solamente admite el método POST.
    http_response_code(405);          //!: Rechaza accesos por GET u otros métodos para que un enlace externo no pueda cerrar la sesión.
    exit('Usá el botón Cerrar sesión.');
}

//N: Bloque 3: validar el token CSRF enviado por el formulario

if (!tokenValido()) {
    http_response_code(403);          //!: Rechaza la solicitud si el token del formulario no coincide con el guardado en la sesión.
    exit('El formulario venció. Volvé al inicio e intentá nuevamente.');
}

//N: Bloque 4: destruir la sesión y volver al ingreso

cerrarSesion();                       //*: Vacía los datos de sesión, vence su cookie y destruye la sesión almacenada en el servidor.

header('Location: login.php');        //N2: Redirige al formulario de ingreso después de cerrar correctamente la sesión.
exit;
