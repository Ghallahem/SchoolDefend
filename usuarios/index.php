<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$titulo = 'Usuarios';
$rutaBase = '../';
$filtroActivo = textoEntrada($_GET, 'activo', '1');

if (!in_array($filtroActivo, ['1', '0', 'todos'], true)) {
    $filtroActivo = '1';
}

$sqlUsuarios = 'SELECT id_usuario, nombre, nombre_usuario, rol, activo FROM usuarios';
$parametros = [];

if ($filtroActivo !== 'todos') {
    $sqlUsuarios .= ' WHERE activo = ?';
    $parametros[] = $filtroActivo;
}

$sqlUsuarios .= ' ORDER BY nombre, id_usuario';
$consultaUsuarios = $pdo->prepare($sqlUsuarios);
$consultaUsuarios->execute($parametros);
$usuarios = $consultaUsuarios->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Usuarios</h1>
<p class="texto-secundario">Administrá las cuentas y sus roles. La baja conserva sus registros anteriores.</p>
<div class="acciones">
    <a class="boton" href="formulario.php">+ Nuevo usuario</a>
</div>
<form method="get" class="filtros">
    <label for="activo">Mostrar</label>
    <select id="activo" name="activo">
        <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Activos</option>
        <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>Inactivos</option>
        <option value="todos" <?= $filtroActivo === 'todos' ? 'selected' : '' ?>>Todos</option>
    </select>
    <button class="boton boton-secundario" type="submit">Filtrar</button>
</form>
<?php if (!$usuarios): ?>
    <p>No hay usuarios para este filtro.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Cuenta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $cuenta): ?>
                    <tr>
                        <td><?= escapar($cuenta['nombre']) ?></td>
                        <td><?= escapar($cuenta['nombre_usuario']) ?></td>
                        <td><?= escapar(nombreRol($cuenta['rol'])) ?></td>
                        <td><?= $cuenta['activo'] ? 'Activa' : 'Inactiva' ?></td>
                        <td class="acciones-tabla">
                            <a class="boton boton-editar" href="formulario.php?id=<?= (int) $cuenta['id_usuario'] ?>">Editar</a>
                            <a class="boton boton-secundario" href="baja.php?id=<?= (int) $cuenta['id_usuario'] ?>"><?= $cuenta['activo'] ? 'Dar de baja' : 'Reactivar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
