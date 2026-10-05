<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$rutaBase = '../';
$idUsuario = idEntrada($_GET, 'id');
$consultaUsuario = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ?');
$consultaUsuario->execute([$idUsuario]);
$cuenta = $consultaUsuario->fetch();

if (!$cuenta) {
    http_response_code(404);
    exit('El usuario no existe.');
}

$titulo = $cuenta['activo'] ? 'Dar de baja usuario' : 'Reactivar usuario';
$mensaje = '';
$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = textoEntrada($_POST, 'motivo');
    $accion = textoEntrada($_POST, 'accion');

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif (!in_array($accion, ['baja', 'reactivar'], true)) {
        $mensaje = 'Acción no válida.';
    } elseif ($accion === 'baja' && ($motivo === '' || mb_strlen($motivo) > 255)) {
        $mensaje = 'Indicá el motivo de baja, de hasta 255 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();
            $consultaCuentas = $pdo->query('SELECT id_usuario, rol, activo FROM usuarios ORDER BY id_usuario FOR UPDATE');
            $cuentasActuales = $consultaCuentas->fetchAll();
            $administradoresActivos = 0;

            foreach ($cuentasActuales as $cuentaActual) {
                if ($cuentaActual['rol'] === 'administrador' && $cuentaActual['activo']) {
                    $administradoresActivos++;
                }
                if ((int) $cuentaActual['id_usuario'] === $idUsuario) {
                    $cuenta['activo'] = $cuentaActual['activo'];
                    $cuenta['rol'] = $cuentaActual['rol'];
                }
            }

            if (($accion === 'baja') !== (bool) $cuenta['activo']) {
                $mensaje = 'El estado cambió. Volvé al listado para revisarlo.';
            } elseif ($accion === 'baja') {
                $sqlPendientes = "
                    SELECT
                        (SELECT COUNT(*) FROM dispositivos WHERE id_responsable = ? AND activo = 1) AS equipos,
                        (SELECT COUNT(*) FROM tickets WHERE id_tecnico = ? AND estado NOT IN ('resuelto', 'cancelado')) AS tickets,
                        (SELECT COUNT(*) FROM incidentes WHERE id_responsable = ? AND estado <> 'cerrado') AS incidentes";
                $consultaPendientes = $pdo->prepare($sqlPendientes);
                $consultaPendientes->execute([$idUsuario, $idUsuario, $idUsuario]);
                $pendientes = $consultaPendientes->fetch();

                if ($idUsuario === (int) $usuario['id_usuario']) {
                    $mensaje = 'No podés dar de baja tu propia cuenta.';
                } elseif ($cuenta['rol'] === 'administrador' && $administradoresActivos <= 1) {
                    $mensaje = 'Debe quedar al menos un Administrador activo.';
                } elseif ($pendientes['equipos'] || $pendientes['tickets'] || $pendientes['incidentes']) {
                    $mensaje = 'Reasigná primero los equipos activos y los tickets o incidentes pendientes de este usuario.';
                }
            }

            if ($mensaje === '') {
                if ($accion === 'baja') {
                    $sqlCambio = "UPDATE usuarios
                                  SET activo = 0, fecha_baja = NOW(), id_usuario_baja = ?, motivo_baja = ?
                                  WHERE id_usuario = ?";
                    $parametros = [$usuario['id_usuario'], $motivo, $idUsuario];
                } else {
                    $sqlCambio = 'UPDATE usuarios SET activo = 1 WHERE id_usuario = ?';
                    $parametros = [$idUsuario];
                }

                $consultaCambio = $pdo->prepare($sqlCambio);
                $consultaCambio->execute($parametros);
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Estado del usuario actualizado. Sus registros se conservaron.';
                header('Location: index.php?activo=todos');
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = errorGuardado($error, 'No se pudo actualizar la cuenta.');
        }
    }
}

$accionFormulario = $cuenta['activo'] ? 'baja' : 'reactivar';
$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<p>Usuario: <strong><?= escapar($cuenta['nombre']) ?></strong> (<?= escapar($cuenta['nombre_usuario']) ?>).</p>
<p class="texto-secundario">Esta acción conserva los tickets y registros anteriores.</p>
<?php if ($cuenta['fecha_baja']): ?>
    <p>Última baja: <?= escapar($cuenta['fecha_baja']) ?>. Motivo: <?= escapar($cuenta['motivo_baja']) ?></p>
<?php endif; ?>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <input type="hidden" name="accion" value="<?= escapar($accionFormulario) ?>">
    <?php if ($cuenta['activo']): ?>
        <label for="motivo">Motivo de la baja</label>
        <textarea id="motivo" name="motivo" maxlength="255" rows="3" required><?= escapar($motivo) ?></textarea>
    <?php endif; ?>
    <div class="acciones">
        <button class="boton" type="submit">Confirmar <?= $cuenta['activo'] ? 'baja' : 'reactivación' ?></button>
        <a class="boton boton-secundario" href="index.php?activo=todos">Cancelar</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
