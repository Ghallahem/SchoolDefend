<?php
//N: Bloque 1: preparar los datos compartidos antes de generar el HTML

$rutaBase = $rutaBase ?? '';                                  //?: El operador ?? usa el valor de la izquierda si existe; de lo contrario utiliza la cadena vacía.
$tituloPagina = escapar($titulo . ' · SchoolDefend');          //*: Codifica el título antes de insertarlo dentro de la etiqueta <title>.
$rutaEstilos = escapar($rutaBase . 'css/estilos.css');         //N3: Construye la ruta a la hoja de estilos según la ubicación de la página actual.
$rutaLogoEncabezado = escapar($rutaBase . 'Imagenes/logo-escudo.png'); //N3: Construye la ruta al logo según la ubicación de la página actual.
$rutaCerrarSesion = escapar($rutaBase . 'logout.php');         //N3: Construye la ruta al controlador que procesa el cierre de sesión.

$mostrarCuenta = !empty($usuario);                             //?: Convierte la existencia de datos de usuario en un booleano que controla cuenta y menú.
$nombreCuenta = '';
$rolCuenta = '';
$tokenCerrarSesion = '';
$enlacesMenu = [];                                             //?: Array asociativo: el texto visible será la clave y la ruta será su valor.

$mensajeExito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);                             //?: El aviso se consume una sola vez para que no reaparezca al recargar otra página.

//N: Bloque 2: preparar la cuenta y el menú cuando existe un usuario autenticado

if ($mostrarCuenta) {
    $nombreCuenta = escapar($usuario['nombre']);                //*: Codifica los datos de la cuenta antes de mostrarlos en HTML.
    $rolCuenta = escapar(nombreRol($usuario['rol']));
    $tokenCerrarSesion = escapar(tokenFormulario());           //!: Protege con CSRF el formulario POST utilizado para cerrar la sesión.

    $enlacesMenu['Inicio'] = $rutaBase . 'index.php';
    $enlacesMenu['Tickets'] = $rutaBase . 'tickets/index.php';

    if (in_array($usuario['rol'], ['administrador', 'tecnico'], true)) {
        $enlacesMenu['Dispositivos'] = $rutaBase . 'dispositivos/index.php';
        $enlacesMenu['Incidentes'] = $rutaBase . 'incidentes/index.php';
        $enlacesMenu['Informes'] = $rutaBase . 'informes/index.php';
    }

    if ($usuario['rol'] === 'administrador') {
        $enlacesMenu['Ubicaciones'] = $rutaBase . 'ubicaciones/index.php';
        $enlacesMenu['Usuarios'] = $rutaBase . 'usuarios/index.php';
    }
}

//!: El menú oculta opciones según el rol, pero cada página debe aplicar exigirRoles(); ocultar un enlace no autoriza ni protege la operación.
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <?php //N3: Metadatos, título y estilos comunes de todas las páginas. ?>
        <meta charset="UTF-8">
        <?php //?: viewport adapta el ancho de la página al dispositivo y permite un diseño responsive. ?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $tituloPagina ?></title>
        <link rel="stylesheet" href="<?= $rutaEstilos ?>">
    </head>
    <body>
        <div class="contenedor">
            <?php //N: Bloque 3: identidad visual y cuenta autenticada. ?>
            <header class="encabezado">
                <div class="marca">
                    <?php //?: El texto alternativo vacío evita repetir el nombre SchoolDefend que ya aparece al lado. ?>
                    <img class="logo-encabezado" src="<?= $rutaLogoEncabezado ?>" alt="">
                    <div>
                        <p class="institucion">ENS N.º 10 · Gestión IT escolar</p>
                        <p class="nombre-sistema">SchoolDefend</p>
                    </div>
                </div>

                <?php if ($mostrarCuenta): ?>
                    <?php //N3: La sintaxis if (...): endif; facilita mezclar una condición PHP con un bloque HTML. ?>
                    <div class="cuenta">
                        <div>
                            <strong><?= $nombreCuenta ?></strong>
                            <br>
                            <span class="texto-secundario"><?= $rolCuenta ?></span>
                        </div>
                        <form method="post" action="<?= $rutaCerrarSesion ?>">
                            <?php //!: El cierre modifica la sesión, por eso se envía por POST junto con un token CSRF. ?>
                            <input type="hidden" name="token" value="<?= $tokenCerrarSesion ?>">
                            <button class="boton boton-secundario" type="submit">Cerrar sesión</button>
                        </form>
                    </div>
                <?php endif; ?>
            </header>

            <?php //N: Bloque 4: menú construido previamente según el rol. ?>
            <?php if ($mostrarCuenta): ?>
                <nav class="menu" aria-label="Menú principal">
                    <?php //N3: foreach recorre cada par nombre => ruta del array asociativo $enlacesMenu. ?>
                    <?php foreach ($enlacesMenu as $nombreEnlace => $rutaEnlace): ?>
                        <a class="boton boton-secundario" href="<?= escapar($rutaEnlace) ?>"><?= escapar($nombreEnlace) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php //N1: Abre el contenido principal; cada página lo completa y pie.php lo cierra. ?>
            <main id="contenido">
                <?php if ($mensajeExito !== ''): ?>
                    <?php //?: role="status" permite anunciar el resultado sin interrumpir al usuario como lo haría una alerta. ?>
                    <p class="aviso-exito" role="status"><?= escapar($mensajeExito) ?></p>
                <?php endif; ?>
