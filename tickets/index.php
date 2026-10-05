<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
$pdo = getConexion();
$rutaBase = '../';
$esProfesor = $usuario['rol'] === 'profesor';
$titulo = $esProfesor ? 'Mis tickets' : 'Tickets';
$busqueda = textoEntrada($_GET, 'buscar');
$filtroEstado = textoEntrada($_GET, 'estado');
$filtroPrioridad = textoEntrada($_GET, 'prioridad');

if (!isset($estadosTicket[$filtroEstado])) {
    $filtroEstado = '';
}
if (!isset($prioridadesTicket[$filtroPrioridad])) {
    $filtroPrioridad = '';
}

$sqlTickets = "
    SELECT tickets.id_ticket, tickets.titulo, tickets.estado, tickets.prioridad,
           tickets.fecha_creacion, ubicaciones.nombre AS ubicacion,
           creador.nombre AS creador, tecnico.nombre AS tecnico
    FROM tickets
    INNER JOIN ubicaciones ON tickets.id_ubicacion = ubicaciones.id_ubicacion
    INNER JOIN usuarios AS creador ON tickets.id_creador = creador.id_usuario
    LEFT JOIN usuarios AS tecnico ON tickets.id_tecnico = tecnico.id_usuario
    WHERE 1 = 1";
$parametros = [];

if ($esProfesor) {
    $sqlTickets .= ' AND tickets.id_creador = ?';
    $parametros[] = $usuario['id_usuario'];
}
if ($busqueda !== '') {
    $sqlTickets .= ' AND (tickets.titulo LIKE ? OR tickets.descripcion LIKE ?)';
    $parametros[] = '%' . $busqueda . '%';
    $parametros[] = '%' . $busqueda . '%';
}
if ($filtroEstado !== '') {
    $sqlTickets .= ' AND tickets.estado = ?';
    $parametros[] = $filtroEstado;
}
if ($filtroPrioridad !== '') {
    $sqlTickets .= ' AND tickets.prioridad = ?';
    $parametros[] = $filtroPrioridad;
}

$sqlTickets .= ' ORDER BY tickets.fecha_creacion DESC, tickets.id_ticket DESC';
$consultaTickets = $pdo->prepare($sqlTickets);
$consultaTickets->execute($parametros);
$tickets = $consultaTickets->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<p class="texto-secundario">Reportes de problemas y seguimiento de su atención.</p>
<a class="boton" href="insertar.php">+ Nuevo ticket</a>
<form method="get" class="filtros">
    <div>
        <label for="buscar">Título o descripción</label>
        <input id="buscar" name="buscar" value="<?= escapar($busqueda) ?>">
    </div>
    <div>
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estadosTicket as $valorEstado => $nombreEstado): ?>
                <option value="<?= escapar($valorEstado) ?>" <?= $filtroEstado === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="prioridad">Prioridad</label>
        <select id="prioridad" name="prioridad">
            <option value="">Todas</option>
            <?php foreach ($prioridadesTicket as $valorPrioridad => $nombrePrioridad): ?>
                <option value="<?= escapar($valorPrioridad) ?>" <?= $filtroPrioridad === $valorPrioridad ? 'selected' : '' ?>><?= escapar($nombrePrioridad) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="boton boton-secundario" type="submit">Filtrar</button>
    <a href="index.php">Limpiar filtros</a>
</form>
<?php if (!$tickets): ?>
    <p>No hay tickets para estos filtros.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>N.º / problema</th>
                    <th>Ubicación</th>
                    <th>Reportante / técnico</th>
                    <th>Estado / prioridad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td>#<?= (int) $ticket['id_ticket'] ?> · <?= escapar($ticket['titulo']) ?><br><?= escapar($ticket['fecha_creacion']) ?></td>
                        <td><?= escapar($ticket['ubicacion']) ?></td>
                        <td><?= escapar($ticket['creador']) ?><br><span class="texto-secundario"><?= escapar($ticket['tecnico'] ?? 'Sin asignar') ?></span></td>
                        <td><?= escapar($estadosTicket[$ticket['estado']]) ?><br><?= escapar($prioridadesTicket[$ticket['prioridad']]) ?></td>
                        <td><a class="boton boton-secundario" href="detalle.php?id=<?= (int) $ticket['id_ticket'] ?>">Ver ticket</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
