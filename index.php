<?php
//N: Bloque 1: exigir autenticación y preparar la página principal

require_once __DIR__ . '/includes/sesion.php';

$usuario = exigirSesion();                             //!: Impide mostrar el inicio cuando no existe una sesión válida.
$pdo = getConexion();                                  //*: Obtiene la conexión PDO que utilizarán los indicadores y el listado.
$titulo = 'Inicio';
$esProfesor = $usuario['rol'] === 'profesor';          //?: Permite adaptar las consultas y la información visible según el rol.

//N: Bloque 2: consultar indicadores personales cuando el usuario es Profesor

if ($esProfesor) {
    $descripcionInicio = 'Consultá el resumen de los problemas que reportaste.';
    $tituloListado = 'Mis últimos tickets';

    //N4: El filtro por id_creador evita que el Profesor reciba el resumen de tickets ajenos.
    $sqlResumen = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(estado NOT IN ('resuelto', 'cancelado')), 0) AS pendientes,
            COALESCE(SUM(estado = 'resuelto'), 0) AS resueltos
        FROM tickets
        WHERE id_creador = ?";

    $consultaResumen = $pdo->prepare($sqlResumen);                    //N4: Prepara la consulta con un marcador para el ID del Profesor.
    $consultaResumen->execute([$usuario['id_usuario']]);              //N4: Envía el ID separado de la estructura SQL.
    $resumen = $consultaResumen->fetch();                             //N4: Obtiene los totales como un array asociativo.

    $indicadores = [
        'Mis tickets' => $resumen['total'],
        'Mis pendientes' => $resumen['pendientes'],
        'Mis resueltos' => $resumen['resueltos']
    ];
} else {
    //N: Bloque 3: consultar indicadores generales para Administrador y Técnico

    $descripcionInicio = 'Consultá la situación de los equipos y la atención de problemas de la escuela.';
    $tituloListado = 'Últimos tickets';

    //N4: query() es adecuada aquí porque estas consultas son fijas y no incorporan datos externos.
    $indicadores = [
        'Dispositivos activos' => $pdo->query("SELECT COUNT(*) FROM dispositivos WHERE activo = 1")->fetchColumn(),
        'Tickets pendientes' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('resuelto', 'cancelado')")->fetchColumn(),
        'Incidentes abiertos' => $pdo->query("SELECT COUNT(*) FROM incidentes WHERE estado <> 'cerrado'")->fetchColumn()
    ];
}

//N: Bloque 4: construir el listado de tickets según el alcance permitido para el rol

$sqlTickets = "
    SELECT tickets.id_ticket, tickets.titulo, tickets.estado,
           ubicaciones.nombre AS ubicacion
    FROM tickets
    INNER JOIN ubicaciones ON tickets.id_ubicacion = ubicaciones.id_ubicacion";

$parametrosTickets = [];

if ($esProfesor) {
    //!: La restricción se aplica en SQL; ocultar filas solamente en HTML no protegería los datos ajenos.
    $sqlTickets .= "
    WHERE tickets.id_creador = ?";
    $parametrosTickets[] = $usuario['id_usuario'];
}

$sqlTickets .= "
    ORDER BY tickets.fecha_creacion DESC, tickets.id_ticket DESC
    LIMIT 5";

$consultaTickets = $pdo->prepare($sqlTickets);             //N4: Prepara la consulta final, con o sin marcador según el rol.
$consultaTickets->execute($parametrosTickets);              //N4: Para el Profesor envía su ID; para los demás envía un array vacío.
$tickets = $consultaTickets->fetchAll();                    //N4: Recupera hasta cinco tickets como una lista de arrays asociativos.

//N: Bloque 5: traducir los estados internos a textos visibles

$nombresEstados = [
    'abierto' => 'Abierto',
    'en_proceso' => 'En proceso',
    'en_espera_repuesto' => 'En espera de repuesto',
    'resuelto' => 'Resuelto',
    'cancelado' => 'Cancelado'
];

require_once __DIR__ . '/includes/encabezado.php';          //N1: Genera la estructura HTML compartida y abre el contenido principal.
?>

<?php //N: Bloque 6: presentación y acceso directo para crear un ticket. ?>
<p class="etiqueta">Inicio</p>
<h1>Bienvenido a SchoolDefend</h1>
<?php //N3: La descripción y el título del listado fueron definidos por el servidor según el rol autenticado. ?>
<p class="texto-secundario"><?= $descripcionInicio ?></p>
<a class="boton" href="tickets/insertar.php">+ Nuevo ticket</a>

<?php //N: Bloque 7: mostrar los indicadores calculados para el rol actual. ?>
<section class="indicadores" aria-label="Resumen">
    <?php //?: foreach asigna en cada vuelta el nombre del indicador y su cantidad correspondiente. ?>
    <?php foreach ($indicadores as $nombreIndicador => $cantidadRegistros): ?>
        <article class="tarjeta">
            <?php //*: Codifica el nombre y convierte el total a entero antes de imprimir ambos valores. ?>
            <span><?= escapar($nombreIndicador) ?></span>
            <strong><?= (int) $cantidadRegistros ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<?php //N: Bloque 8: mostrar los últimos tickets o informar que no existen resultados. ?>
<section class="seccion">
    <h2><?= $tituloListado ?></h2>

    <?php if (empty($tickets)): ?>
        <p class="texto-secundario">Todavía no hay tickets para mostrar.</p>
    <?php else: ?>
        <?php //?: tabindex="0" permite recorrer con teclado la tabla cuando necesita desplazamiento horizontal. ?>
        <div class="tabla-contenedor" tabindex="0" role="region" aria-label="Últimos tickets">
            <table>
                <thead>
                    <tr>
                        <th scope="col">N.º</th>
                        <th scope="col">Problema</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php //N3: Cada fila representa un ticket obtenido previamente con fetchAll(). ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><?= (int) $ticket['id_ticket'] ?></td>
                            <?php //!: El ID se fuerza a entero para la URL y los textos recuperados se codifican para prevenir XSS. ?>
                            <td><a href="tickets/detalle.php?id=<?= (int) $ticket['id_ticket'] ?>"><?= escapar($ticket['titulo']) ?></a></td>
                            <td><?= escapar($ticket['ubicacion']) ?></td>
                            <td>
                                <?php //?: ?? usa el estado original como alternativa si no existe una traducción en $nombresEstados. ?>
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

<?php require_once __DIR__ . '/includes/pie.php'; //N1: Cierra el contenido principal y completa el documento HTML. ?>
