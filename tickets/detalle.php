<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
$pdo = getConexion();
$rutaBase = '../';
$idTicket = idEntrada($_GET, 'id');
$esProfesor = $usuario['rol'] === 'profesor';

$sqlTicket = "
    SELECT tickets.*, ubicaciones.nombre AS ubicacion, dispositivos.codigo AS equipo,
           creador.nombre AS creador, tecnico.nombre AS tecnico
    FROM tickets
    INNER JOIN ubicaciones ON tickets.id_ubicacion = ubicaciones.id_ubicacion
    LEFT JOIN dispositivos ON tickets.id_dispositivo = dispositivos.id_dispositivo
    INNER JOIN usuarios AS creador ON tickets.id_creador = creador.id_usuario
    LEFT JOIN usuarios AS tecnico ON tickets.id_tecnico = tecnico.id_usuario
    WHERE tickets.id_ticket = ?";
$parametros = [$idTicket];

if ($esProfesor) {
    $sqlTicket .= ' AND tickets.id_creador = ?';
    $parametros[] = $usuario['id_usuario'];
}

$consultaTicket = $pdo->prepare($sqlTicket);
$consultaTicket->execute($parametros);
$ticket = $consultaTicket->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('El ticket no está disponible.');
}

$titulo = 'Ticket #' . $idTicket;
$puedeGestionar = !$esProfesor && !in_array($ticket['estado'], ['resuelto', 'cancelado'], true);
$sqlSeguimiento = "
    SELECT seguimiento_tickets.descripcion, seguimiento_tickets.fecha, usuarios.nombre AS autor
    FROM seguimiento_tickets
    INNER JOIN usuarios ON seguimiento_tickets.id_usuario = usuarios.id_usuario
    WHERE seguimiento_tickets.id_ticket = ?
    ORDER BY seguimiento_tickets.fecha, seguimiento_tickets.id_seguimiento";
$consultaSeguimiento = $pdo->prepare($sqlSeguimiento);
$consultaSeguimiento->execute([$idTicket]);
$seguimiento = $consultaSeguimiento->fetchAll();

$ficha = [
    'Estado' => $estadosTicket[$ticket['estado']],
    'Prioridad' => $prioridadesTicket[$ticket['prioridad']],
    'Reportante' => $ticket['creador'],
    'Responsable del seguimiento' => $ticket['tecnico'] ?? 'Sin asignar',
    'Ubicación del reporte' => $ticket['ubicacion'],
    'Equipo' => $ticket['equipo'] ?? 'Sin identificar',
    'Creado' => $ticket['fecha_creacion'],
    'Finalizado' => $ticket['fecha_cierre'] ?? 'Pendiente'
];

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>#<?= $idTicket ?> · <?= escapar($ticket['titulo']) ?></h1>
<div class="acciones">
    <?php if ($puedeGestionar): ?>
        <a class="boton boton-editar" href="editar.php?id=<?= $idTicket ?>">Gestionar ticket</a>
    <?php endif; ?>
    <a class="boton boton-secundario" href="index.php">Volver a tickets</a>
</div>
<dl class="ficha">
    <?php foreach ($ficha as $etiqueta => $valor): ?>
        <div>
            <dt><?= escapar($etiqueta) ?></dt>
            <dd><?= escapar($valor) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
<h2>Reporte original</h2>
<p class="texto-multilinea"><?= escapar($ticket['descripcion']) ?></p>
<h2>Atención técnica</h2>
<p class="texto-multilinea">Diagnóstico: <?= escapar($ticket['diagnostico'] ?? 'Sin registrar') ?></p>
<p class="texto-multilinea">Solución: <?= escapar($ticket['solucion'] ?? 'Sin registrar') ?></p>
<?php if ($ticket['motivo_cancelacion']): ?>
    <p class="texto-multilinea">Motivo de cancelación: <?= escapar($ticket['motivo_cancelacion']) ?></p>
<?php endif; ?>
<section class="historial">
    <h2>Seguimiento</h2>
    <?php if (!$seguimiento): ?>
        <p>No hay novedades registradas.</p>
    <?php endif; ?>
    <?php foreach ($seguimiento as $novedad): ?>
        <article class="registro-historial">
            <strong><?= escapar($novedad['autor']) ?></strong> · <?= escapar($novedad['fecha']) ?>
            <p class="texto-multilinea"><?= escapar($novedad['descripcion']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
