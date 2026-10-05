<?php
require_once __DIR__ . '/includes/sesion.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Usá el botón Cerrar sesión.');
}
if (!tokenValido()) {
    http_response_code(403);
    exit('El formulario venció. Volvé al inicio e intentá nuevamente.');
}
cerrarSesion();
header('Location: login.php');
exit;
