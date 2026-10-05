<?php
// Preparar los datos compartidos antes de comenzar el HTML.
$rutaBase = $rutaBase ?? '';
$tituloPagina = escapar($titulo . ' · SchoolDefend');
$rutaEstilos = escapar($rutaBase . 'css/estilos.css');
$rutaCerrarSesion = escapar($rutaBase . 'logout.php');
$mostrarCuenta = !empty($usuario);
$nombreCuenta = '';
$rolCuenta = '';
$tokenCerrarSesion = '';
$enlacesMenu = [];
$mensajeExito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);

if ($mostrarCuenta) {
    $nombreCuenta = escapar($usuario['nombre']);
    $rolCuenta = escapar(nombreRol($usuario['rol']));
    $tokenCerrarSesion = escapar(tokenFormulario());
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
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $tituloPagina ?></title>
        <link rel="stylesheet" href="<?= $rutaEstilos ?>">
    </head>
    <body>
        <div class="contenedor">
            <header class="encabezado">
                <div class="marca">
                    <span class="insignia" aria-hidden="true">SD</span>
                    <div>
                        <p class="institucion">ENS N.º 10 · Gestión IT escolar</p>
                        <p class="nombre-sistema">SchoolDefend</p>
                    </div>
                </div>

                <?php if ($mostrarCuenta): ?>
                    <div class="cuenta">
                        <div>
                            <strong><?= $nombreCuenta ?></strong>
                            <br>
                            <span class="texto-secundario"><?= $rolCuenta ?></span>
                        </div>
                        <form method="post" action="<?= $rutaCerrarSesion ?>">
                            <input type="hidden" name="token" value="<?= $tokenCerrarSesion ?>">
                            <button class="boton boton-secundario" type="submit">Cerrar sesión</button>
                        </form>
                    </div>
                <?php endif; ?>
            </header>
            <?php if ($mostrarCuenta): ?>
                <nav class="menu" aria-label="Menú principal">
                    <?php foreach ($enlacesMenu as $nombreEnlace => $rutaEnlace): ?>
                        <a class="boton boton-secundario" href="<?= escapar($rutaEnlace) ?>"><?= escapar($nombreEnlace) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <main id="contenido">
                <?php if ($mensajeExito !== ''): ?>
                    <p class="aviso-exito" role="status"><?= escapar($mensajeExito) ?></p>
                <?php endif; ?>

