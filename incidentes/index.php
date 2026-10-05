<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$titulo = 'Incidentes de seguridad';
$estado = textoEntrada($_GET, 'estado');
$buscar = textoEntrada($_GET, 'buscar');
$sqlIncidentes = "
    SELECT incidentes.id_incidente, incidentes.titulo, incidentes.tipo,
           incidentes.severidad, incidentes.estado, usuarios.nombre AS responsable
    FROM incidentes
    INNER JOIN usuarios ON incidentes.id_responsable = usuarios.id_usuario
    WHERE 1 = 1";
$parametrosIncidentes = [];
if (isset($estadosIncidente[$estado])) {
    $sqlIncidentes .= ' AND incidentes.estado = ?';
    $parametrosIncidentes[] = $estado;
}
if ($buscar !== '') {
    $sqlIncidentes .= ' AND incidentes.titulo LIKE ?';
    $parametrosIncidentes[] = '%' . $buscar . '%';
}
$sqlIncidentes .= ' ORDER BY incidentes.fecha_creacion DESC, incidentes.id_incidente DESC';
$consultaIncidentes = $pdo->prepare($sqlIncidentes);
$consultaIncidentes->execute($parametrosIncidentes);
$incidentes = $consultaIncidentes->fetchAll();
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Incidentes de seguridad</h1>
<p class="texto-secundario">Registro manual de problemas de seguridad y su atención.</p>
<a class="boton" href="formulario.php">Registrar incidente</a>
<form method="get" class="filtros">
    <label for="buscar">Título</label>
    <input id="buscar" name="buscar" value="<?= escapar($buscar) ?>">
    <label for="estado">Estado</label>
    <select id="estado" name="estado">
        <option value="">Todos</option>
        <?php foreach ($estadosIncidente as $valorEstado => $nombreEstado): ?>
            <option value="<?= escapar($valorEstado) ?>" <?= $estado === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="boton" type="submit">Filtrar</button>
</form>
<?php if (!$incidentes): ?>
    <p>No hay incidentes para mostrar.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th scope="col">N.º</th>
                    <th scope="col">Título</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Severidad</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Responsable</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidentes as $incidente): ?>
                    <tr>
                        <td><?= (int) $incidente['id_incidente'] ?></td>
                        <td><?= escapar($incidente['titulo']) ?></td>
                        <td><?= escapar($tiposIncidente[$incidente['tipo']]) ?></td>
                        <td><?= escapar($severidadesIncidente[$incidente['severidad']]) ?></td>
                        <td><?= escapar($estadosIncidente[$incidente['estado']]) ?></td>
                        <td><?= escapar($incidente['responsable']) ?></td>
                        <td><a class="boton boton-secundario" href="detalle.php?id=<?= (int) $incidente['id_incidente'] ?>">Ver detalle</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
