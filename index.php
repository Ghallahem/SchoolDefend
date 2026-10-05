<?php

// require_onces carga UNA VEZ si no esta cargado antes __DIR__ devuelve la ruta absoluta del archivo (editado)
require_once __DIR__ . '/includes/sesion.php';

$usuario = exigirSesion();
$pdo = getConexion();
$titulo = 'Inicio';
$esProfesor = $usuario['rol'] === 'profesor';

// Consultar los indicadores según el rol.
if ($esProfesor) {

    $descripcionInicio = 'Consultá el resumen de los problemas que reportaste.';
    $tituloListado = 'Mis últimos tickets';

    //Esto contara, todos los tickes que hizo el usuario
    $sqlResumen = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(estado NOT IN ('resuelto', 'cancelado')), 0) AS pendientes,
            COALESCE(SUM(estado = 'resuelto'), 0) AS resueltos
        FROM tickets
        WHERE id_creador = ?";

    $consultaResumen = $pdo->prepare($sqlResumen);
    $consultaResumen->execute([$usuario['id_usuario']]);
    $resumen = $consultaResumen->fetch();

    $indicadores = [
        'Mis tickets' => $resumen['total'],
        'Mis pendientes' => $resumen['pendientes'],
        'Mis resueltos' => $resumen['resueltos']
    ];
} else {
    $descripcionInicio = 'Consultá la situación de los equipos y la atención de problemas de la escuela.';
    $tituloListado = 'Últimos tickets';
    
    //Hace un conteo de los dispositivos activos, de los tickets pendientes y los incidentes abiertos
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM dispositivos WHERE activo = 1) as dispositivos,
            (SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('resuelto', 'cancelado')) as pendientes,
            (SELECT COUNT(*) FROM incidentes WHERE estado <> 'cerrado') as abiertos
    ")->fetch();
    //Esto funciona de manera similar a un diccionario de python [clave => valor]
    $indicadores = [
            'Dispositivos activos' => $stats['dispositivos'],
            'Tickets pendientes' => $stats['pendientes'],
            'Incidentes abiertos' => $stats['abiertos']
    ];
    /* Este bloque, hace lo mismo que el de arriba, pero en 3 consulta, en vez de una.
    $indicadores = [
        'Dispositivos activos' => $pdo->query("SELECT COUNT(*) FROM dispositivos WHERE activo = 1")->fetchColumn(),
        'Tickets pendientes' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('resuelto', 'cancelado')")->fetchColumn(),
        'Incidentes abiertos' => $pdo->query("SELECT COUNT(*) FROM incidentes WHERE estado <> 'cerrado'")->fetchColumn()
    ];*/
}

// Aplicar el filtro del Profesor antes de ejecutar la consulta del listado.
$sqlTickets = "
    SELECT t.id_ticket, t.titulo, t.estado,
           ub.nombre AS ubicacion
    FROM tickets t
    INNER JOIN ubicaciones ub ON t.id_ubicacion = ub.id_ubicacion";

$parametrosTickets = [];

if ($esProfesor) {
    //Concatena esta condicion WHERE si el if se cumple.
    $sqlTickets .= "
    WHERE t.id_creador = ?";
    $parametrosTickets[] = $usuario['id_usuario'];
}
//Esto se se concatenara si se cumpre el if o no. LIMIT 5 limita a 5 la cantidad de filas que retornara
$sqlTickets .= "
    ORDER BY t.fecha_creacion DESC, t.id_ticket DESC
    LIMIT 5";

$consultaTickets = $pdo->prepare($sqlTickets);
$consultaTickets->execute($parametrosTickets);
$tickets = $consultaTickets->fetchAll();

$nombresEstados = [
    'abierto' => 'Abierto',
    'en_proceso' => 'En proceso',
    'en_espera_repuesto' => 'En espera de repuesto',
    'resuelto' => 'Resuelto',
    'cancelado' => 'Cancelado'
];

require_once __DIR__ . '/includes/encabezado.php';
?>

<p class="etiqueta">Inicio</p>
<h1>Bienvenido a SchoolDefend</h1>
<p class="texto-secundario"><?= $descripcionInicio ?></p>
<a class="boton" href="tickets/insertar.php">+ Nuevo ticket</a>

<!-- Este bloque genera el segmento de Contadores resumen, que se encuentra arriba de la tabla de ultimos tickets-->
<section class="indicadores" aria-label="Resumen">
    <?php foreach ($indicadores as $nombreIndicador => $cantidadRegistros): ?>
        <article class="tarjeta">
            <span><?= escapar($nombreIndicador) ?></span>
            <strong><?= (int) $cantidadRegistros ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="seccion">
    <h2><?= $tituloListado ?></h2>
    <!-- Verifica si $tickets tiene contenido y si se genera la tabla o no -->
    <?php if (empty($tickets)): ?>
        <p class="texto-secundario">Todavía no hay tickets para mostrar.</p>
    <?php else: ?>

        <div class="tabla-contenedor" tabindex="0" role="region" aria-label="Últimos tickets">
            <!-- Tode este segmento genera la tabla de ultimos tickets -->
            <table>
                <thead>
                    <tr>
                        <!-- scope="col" es por accesibilidad de los no videntes 
                        (especifica al lecto de pantalla, que esto es el encabezado de una columna) -->
                        <th scope="col">N.º</th>
                        <th scope="col">Problema</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Aca se genera el body de la tabla -->
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><?= (int) $ticket['id_ticket'] ?></td>
                            <td><a href="tickets/detalle.php?id=<?= (int) $ticket['id_ticket'] ?>"><?= escapar($ticket['titulo']) ?></a></td>
                            <td><?= escapar($ticket['ubicacion']) ?></td>
                            <td>
                                <span class="estado <?= escapar($ticket['estado']) ?>">
                                    <?= escapar($nombresEstados[$ticket['estado']] ?? $ticket['estado']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
