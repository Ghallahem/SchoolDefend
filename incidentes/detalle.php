<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idIncidente = idEntrada($_GET, 'id');
$titulo = 'Detalle del incidente';
$sqlIncidente = "
    SELECT incidentes.*, responsable.nombre AS responsable, creador.nombre AS creador,
           dispositivos.codigo AS equipo, tickets.titulo AS ticket
    FROM incidentes
    INNER JOIN usuarios AS responsable ON incidentes.id_responsable = responsable.id_usuario
    INNER JOIN usuarios AS creador ON incidentes.id_creador = creador.id_usuario
    LEFT JOIN dispositivos ON incidentes.id_dispositivo = dispositivos.id_dispositivo
    LEFT JOIN tickets ON incidentes.id_ticket = tickets.id_ticket
    WHERE incidentes.id_incidente = ?";
$consultaIncidente = $pdo->prepare($sqlIncidente);
$consultaIncidente->execute([$idIncidente]);
$incidente = $consultaIncidente->fetch();
if (!$incidente) {
    http_response_code(404);
    exit('El incidente no existe.');
}
$ficha = [
    'Tipo' => $tiposIncidente[$incidente['tipo']],
    'Severidad' => $severidadesIncidente[$incidente['severidad']],
    'Estado' => $estadosIncidente[$incidente['estado']],
    'Responsable' => $incidente['responsable'],
    'Registrado por' => $incidente['creador'],
    'Equipo' => $incidente['equipo'] ?? 'Sin identificar',
    'Fecha de registro' => $incidente['fecha_creacion'],
    'Fecha de cierre' => $incidente['fecha_cierre'] ?? 'Pendiente'
];
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>#<?= $idIncidente ?> · <?= escapar($incidente['titulo']) ?></h1>
<div class="acciones">
    <?php if ($incidente['estado'] !== 'cerrado'): ?>
        <a class="boton boton-editar" href="formulario.php?id=<?= $idIncidente ?>">Gestionar incidente</a>
    <?php endif; ?>
    <a class="boton boton-secundario" href="index.php">Volver a incidentes</a>
</div>
<dl class="ficha">
    <?php foreach ($ficha as $etiqueta => $valor): ?>
        <div>
            <dt><?= escapar($etiqueta) ?></dt>
            <dd><?= escapar($valor) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
<?php if ($incidente['id_ticket']): ?>
    <p>Ticket relacionado: <a href="../tickets/detalle.php?id=<?= (int) $incidente['id_ticket'] ?>"><?= escapar($incidente['ticket']) ?></a></p>
<?php endif; ?>
<h2>Descripción</h2>
<p class="texto-multilinea"><?= escapar($incidente['descripcion']) ?></p>
<h2>Acciones realizadas</h2>
<p class="texto-multilinea"><?= escapar($incidente['acciones_realizadas'] ?? 'Sin registrar') ?></p>
<h2>Conclusión</h2>
<p class="texto-multilinea"><?= escapar($incidente['conclusion'] ?? 'Sin registrar') ?></p>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
