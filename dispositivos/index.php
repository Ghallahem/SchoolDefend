<?php

require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$titulo = 'Dispositivos';
$rutaBase = '../';
$busqueda = textoEntrada($_GET, 'buscar');
$filtroActivo = textoEntrada($_GET, 'activo', '1');
$filtroEstado = textoEntrada($_GET, 'estado');
$idUbicacion = idEntrada($_GET, 'ubicacion');

if (!in_array($filtroActivo, ['1', '0', 'todos'], true)) {
    $filtroActivo = '1';
}

if (!isset($estadosDispositivo[$filtroEstado])) {
    $filtroEstado = '';
}

$sqlDispositivos = "
    SELECT dispositivos.id_dispositivo, dispositivos.codigo, dispositivos.tipo,
           dispositivos.marca, dispositivos.modelo, dispositivos.estado, dispositivos.activo,
           ubicaciones.nombre AS ubicacion, usuarios.nombre AS responsable
    FROM dispositivos
    INNER JOIN ubicaciones ON dispositivos.id_ubicacion = ubicaciones.id_ubicacion
    LEFT JOIN usuarios ON dispositivos.id_responsable = usuarios.id_usuario
    WHERE 1 = 1";
$parametros = [];

if ($filtroActivo !== 'todos') {
    $sqlDispositivos .= ' AND dispositivos.activo = ?';
    $parametros[] = $filtroActivo;
}

if ($busqueda !== '') {
    $sqlDispositivos .= "
        AND (dispositivos.codigo LIKE ?
             OR dispositivos.marca LIKE ?
             OR dispositivos.modelo LIKE ?)";
    $parametros[] = '%' . $busqueda . '%';
    $parametros[] = '%' . $busqueda . '%';
    $parametros[] = '%' . $busqueda . '%';
}

if ($filtroEstado !== '') {
    $sqlDispositivos .= ' AND dispositivos.estado = ?';
    $parametros[] = $filtroEstado;
}

if ($idUbicacion !== null) {
    $sqlDispositivos .= ' AND dispositivos.id_ubicacion = ?';
    $parametros[] = $idUbicacion;
}

$sqlDispositivos .= ' ORDER BY dispositivos.codigo';
$consultaDispositivos = $pdo->prepare($sqlDispositivos);
$consultaDispositivos->execute($parametros);
$dispositivos = $consultaDispositivos->fetchAll();
$ubicaciones = $pdo->query('SELECT id_ubicacion, nombre FROM ubicaciones ORDER BY nombre')->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Dispositivos</h1>
<p class="texto-secundario">Inventario tecnológico de la escuela y estado de cada equipo.</p>
<div class="acciones">
    <a class="boton" href="formulario.php">+ Nuevo dispositivo</a>
</div>
<form method="get" class="filtros">
    <div>
        <label for="buscar">Código, marca o modelo</label>
        <input id="buscar" name="buscar" value="<?= escapar($busqueda) ?>">
    </div>
    <div>
        <label for="ubicacion">Ubicación</label>
        <select id="ubicacion" name="ubicacion">
            <option value="">Todas</option>
            <?php foreach ($ubicaciones as $ubicacion): ?>
                <option value="<?= (int) $ubicacion['id_ubicacion'] ?>" <?= $idUbicacion === (int) $ubicacion['id_ubicacion'] ? 'selected' : '' ?>><?= escapar($ubicacion['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="estado">Estado técnico</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estadosDispositivo as $valorEstado => $nombreEstado): ?>
                <option value="<?= escapar($valorEstado) ?>" <?= $filtroEstado === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="activo">Inventario</label>
        <select id="activo" name="activo">
            <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>Inactivos</option>
            <option value="todos" <?= $filtroActivo === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
    </div>
    <button class="boton boton-secundario" type="submit">Filtrar</button>
    <a href="index.php">Limpiar filtros</a>
</form>
<?php if (!$dispositivos): ?>
    <p>No hay dispositivos que coincidan con los filtros.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Código / equipo</th>
                    <th>Ubicación</th>
                    <th>Responsable</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispositivos as $dispositivo): ?>
                    <tr>
                        <td>
                            <strong><?= escapar($dispositivo['codigo']) ?></strong><br>
                            <?= escapar($tiposDispositivo[$dispositivo['tipo']]) ?> · <?= escapar($dispositivo['marca']) ?> <?= escapar($dispositivo['modelo']) ?>
                        </td>
                        <td><?= escapar($dispositivo['ubicacion']) ?></td>
                        <td><?= escapar($dispositivo['responsable'] ?? 'Sin asignar') ?></td>
                        <td>
                            <?= escapar($estadosDispositivo[$dispositivo['estado']]) ?><br>
                            <span class="texto-secundario"><?= $dispositivo['activo'] ? 'Activo' : 'Dado de baja' ?></span>
                        </td>
                        <td class="acciones-tabla">
                            <a class="boton boton-secundario" href="detalle.php?id=<?= (int) $dispositivo['id_dispositivo'] ?>">Ver</a>
                            <a class="boton boton-editar" href="formulario.php?id=<?= (int) $dispositivo['id_dispositivo'] ?>">Editar</a>
                            <a class="boton boton-secundario" href="baja.php?id=<?= (int) $dispositivo['id_dispositivo'] ?>"><?= $dispositivo['activo'] ? 'Dar de baja' : 'Reactivar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
