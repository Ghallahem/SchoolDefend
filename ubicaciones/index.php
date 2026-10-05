<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$titulo = 'Ubicaciones';
$rutaBase = '../';
$filtroActivo = textoEntrada($_GET, 'activo', '1');

if (!in_array($filtroActivo, ['1', '0', 'todos'], true)) {
    $filtroActivo = '1';
}

$sqlUbicaciones = 'SELECT id_ubicacion, nombre, descripcion, activo FROM ubicaciones';
$parametros = [];

if ($filtroActivo !== 'todos') {
    $sqlUbicaciones .= ' WHERE activo = ?';
    $parametros[] = $filtroActivo;
}

$sqlUbicaciones .= ' ORDER BY nombre';
$consultaUbicaciones = $pdo->prepare($sqlUbicaciones);
$consultaUbicaciones->execute($parametros);
$ubicaciones = $consultaUbicaciones->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Ubicaciones</h1>
<p class="texto-secundario">Aulas, laboratorios y sectores donde se encuentran los equipos.</p>
<div class="acciones">
    <a class="boton" href="formulario.php">+ Nueva ubicación</a>
</div>
<form method="get" class="filtros">
    <label for="activo">Mostrar</label>
    <select id="activo" name="activo">
        <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Activas</option>
        <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>Inactivas</option>
        <option value="todos" <?= $filtroActivo === 'todos' ? 'selected' : '' ?>>Todas</option>
    </select>
    <button class="boton boton-secundario" type="submit">Filtrar</button>
</form>
<?php if (!$ubicaciones): ?>
    <p>No hay ubicaciones para este filtro.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ubicaciones as $ubicacion): ?>
                    <tr>
                        <td><?= escapar($ubicacion['nombre']) ?></td>
                        <td><?= escapar($ubicacion['descripcion']) ?></td>
                        <td><?= $ubicacion['activo'] ? 'Activa' : 'Inactiva' ?></td>
                        <td class="acciones-tabla">
                            <a class="boton boton-editar" href="formulario.php?id=<?= (int) $ubicacion['id_ubicacion'] ?>">Editar</a>
                            <a class="boton boton-secundario" href="baja.php?id=<?= (int) $ubicacion['id_ubicacion'] ?>"><?= $ubicacion['activo'] ? 'Dar de baja' : 'Reactivar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
