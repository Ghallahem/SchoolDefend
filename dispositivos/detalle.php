<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idDispositivo = idEntrada($_GET, 'id');

$sqlDispositivo = "
    SELECT dispositivos.*, ubicaciones.nombre AS ubicacion,
           responsable.nombre AS responsable, autor_baja.nombre AS autor_baja
    FROM dispositivos
    INNER JOIN ubicaciones ON dispositivos.id_ubicacion = ubicaciones.id_ubicacion
    LEFT JOIN usuarios AS responsable ON dispositivos.id_responsable = responsable.id_usuario
    LEFT JOIN usuarios AS autor_baja ON dispositivos.id_usuario_baja = autor_baja.id_usuario
    WHERE dispositivos.id_dispositivo = ?";
$consultaDispositivo = $pdo->prepare($sqlDispositivo);
$consultaDispositivo->execute([$idDispositivo]);
$dispositivo = $consultaDispositivo->fetch();

if (!$dispositivo) {
    http_response_code(404);
    exit('El dispositivo no existe.');
}

$titulo = 'Dispositivo ' . $dispositivo['codigo'];
$ficha = [
    'Tipo' => $tiposDispositivo[$dispositivo['tipo']],
    'Marca' => $dispositivo['marca'],
    'Modelo' => $dispositivo['modelo'],
    'Número de serie' => $dispositivo['numero_serie'],
    'Sistema operativo' => $dispositivo['sistema_operativo'],
    'IP' => $dispositivo['ip'],
    'MAC' => $dispositivo['mac'],
    'Ubicación actual' => $dispositivo['ubicacion'],
    'Responsable' => $dispositivo['responsable'],
    'Estado técnico' => $estadosDispositivo[$dispositivo['estado']],
    'Inventario' => $dispositivo['activo'] ? 'Activo' : 'Dado de baja',
    'Fecha de alta' => $dispositivo['fecha_alta']
];

$sqlTickets = "
    SELECT tickets.id_ticket, tickets.titulo, tickets.estado, tickets.fecha_creacion,
           tickets.diagnostico, tickets.solucion, tickets.motivo_cancelacion,
           ubicaciones.nombre AS ubicacion_reporte
    FROM tickets
    INNER JOIN ubicaciones ON tickets.id_ubicacion = ubicaciones.id_ubicacion
    WHERE tickets.id_dispositivo = ?
    ORDER BY tickets.fecha_creacion DESC";
$consultaTickets = $pdo->prepare($sqlTickets);
$consultaTickets->execute([$idDispositivo]);
$tickets = $consultaTickets->fetchAll();

$sqlIncidentes = "
    SELECT id_incidente, titulo, estado, fecha_creacion, acciones_realizadas, conclusion
    FROM incidentes
    WHERE id_dispositivo = ?
    ORDER BY fecha_creacion DESC";
$consultaIncidentes = $pdo->prepare($sqlIncidentes);
$consultaIncidentes->execute([$idDispositivo]);
$incidentes = $consultaIncidentes->fetchAll();

$nombresEstados = [
    'abierto' => 'Abierto', 'en_proceso' => 'En proceso',
    'en_espera_repuesto' => 'En espera de repuesto',
    'resuelto' => 'Resuelto', 'cancelado' => 'Cancelado',
    'reportado' => 'Reportado', 'en_investigacion' => 'En investigación', 'cerrado' => 'Cerrado'
];

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<div class="acciones">
    <a class="boton boton-editar" href="formulario.php?id=<?= $idDispositivo ?>">Editar</a>
    <a class="boton boton-secundario" href="baja.php?id=<?= $idDispositivo ?>"><?= $dispositivo['activo'] ? 'Dar de baja' : 'Reactivar' ?></a>
    <a class="boton boton-secundario" href="index.php">Volver al inventario</a>
</div>
<dl class="ficha">
    <?php foreach ($ficha as $etiqueta => $valor): ?>
        <div>
            <dt><?= escapar($etiqueta) ?></dt>
            <dd><?= escapar($valor ?? 'Sin informar') ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
<p class="texto-multilinea"><?= escapar($dispositivo['observaciones']) ?></p>
<?php if ($dispositivo['fecha_baja']): ?>
    <p>Última baja: <?= escapar($dispositivo['fecha_baja']) ?> por <?= escapar($dispositivo['autor_baja']) ?>.</p>
    <p>Motivo: <?= escapar($dispositivo['motivo_baja']) ?></p>
<?php endif; ?>
<section class="seccion historial">
    <h2>Tickets del equipo</h2>

    <?php if (!$tickets): ?>
        <p>No hay tickets asociados.</p>
    <?php endif; ?>

    <?php foreach ($tickets as $ticket): ?>
        <article class="registro-historial">
            <h3><a href="../tickets/detalle.php?id=<?= (int) $ticket['id_ticket'] ?>">#<?= (int) $ticket['id_ticket'] ?> · <?= escapar($ticket['titulo']) ?></a></h3>
            <p><?= escapar($ticket['fecha_creacion']) ?> · <?= escapar($nombresEstados[$ticket['estado']]) ?> · <?= escapar($ticket['ubicacion_reporte']) ?></p>
            <p class="texto-multilinea">Diagnóstico: <?= escapar($ticket['diagnostico'] ?? 'Sin registrar') ?></p>
            <p class="texto-multilinea">Solución: <?= escapar($ticket['solucion'] ?? 'Sin registrar') ?></p>

            <?php if ($ticket['motivo_cancelacion']): ?>
                <p class="texto-multilinea">Cancelación: <?= escapar($ticket['motivo_cancelacion']) ?></p>
            <?php endif; ?>
            
        </article>
    <?php endforeach; ?>
</section>
<section class="seccion historial">
    <h2>Incidentes del equipo</h2>
    <?php if (!$incidentes): ?>
        <p>No hay incidentes asociados.</p>
    <?php endif; ?>
    <?php foreach ($incidentes as $incidente): ?>
        <article class="registro-historial">
            <h3>#<?= (int) $incidente['id_incidente'] ?> · <?= escapar($incidente['titulo']) ?></h3>
            <p><?= escapar($incidente['fecha_creacion']) ?> · <?= escapar($nombresEstados[$incidente['estado']]) ?></p>
            <p class="texto-multilinea">Acciones: <?= escapar($incidente['acciones_realizadas'] ?? 'Sin registrar') ?></p>
            <p class="texto-multilinea">Conclusión: <?= escapar($incidente['conclusion'] ?? 'Pendiente') ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
