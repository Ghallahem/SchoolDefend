<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$rutaBase = '../';
$idUsuario = idEntrada($_GET, 'id');
$esEdicion = isset($_GET['id']);
$titulo = $esEdicion ? 'Editar usuario' : 'Nuevo usuario';
$mensaje = '';
$roles = ['administrador' => 'Administrador', 'tecnico' => 'Técnico', 'profesor' => 'Profesor'];
$cuenta = ['nombre' => '', 'nombre_usuario' => '', 'rol' => 'profesor', 'activo' => 1];

if ($esEdicion) {
    $consultaUsuario = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ?');
    $consultaUsuario->execute([$idUsuario]);
    $cuenta = $consultaUsuario->fetch();

    if (!$cuenta) {
        http_response_code(404);
        exit('El usuario no existe.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = textoEntrada($_POST, 'nombre');
    $nombreUsuario = textoEntrada($_POST, 'nombre_usuario');
    $rol = textoEntrada($_POST, 'rol');
    $contrasena = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif ($nombre === '' || mb_strlen($nombre) > 100 || $nombreUsuario === '' || mb_strlen($nombreUsuario) > 50) {
        $mensaje = 'Completá nombre y usuario, respetando sus límites de 100 y 50 caracteres.';
    } elseif (!isset($roles[$rol])) {
        $mensaje = 'Seleccioná un rol válido.';
    } elseif ((!$esEdicion || $contrasena !== '') && (mb_strlen($contrasena) < 8 || strlen($contrasena) > 72)) {
        $mensaje = 'Usá una contraseña de al menos 8 caracteres. Si es muy larga, acortala.';
    } else {
        try {
            $pdo->beginTransaction();

            // Bloquear las cuentas durante el cambio evita quitar al último administrador.
            $consultaCuentas = $pdo->query('SELECT id_usuario, rol, activo FROM usuarios ORDER BY id_usuario FOR UPDATE');
            $cuentasActuales = $consultaCuentas->fetchAll();
            $administradoresActivos = 0;

            foreach ($cuentasActuales as $cuentaActual) {
                if ($cuentaActual['rol'] === 'administrador' && $cuentaActual['activo']) {
                    $administradoresActivos++;
                }
                if ($esEdicion && (int) $cuentaActual['id_usuario'] === $idUsuario) {
                    $cuenta['rol'] = $cuentaActual['rol'];
                    $cuenta['activo'] = $cuentaActual['activo'];
                }
            }

            if ($esEdicion && $cuenta['rol'] === 'administrador' && $cuenta['activo'] && $rol !== 'administrador' && $administradoresActivos <= 1) {
                $mensaje = 'Debe quedar al menos un Administrador activo.';
            }

            if ($esEdicion && $rol === 'profesor' && $cuenta['rol'] !== 'profesor') {
                $sqlPendientes = "
                    SELECT
                        (SELECT COUNT(*) FROM tickets WHERE id_tecnico = ? AND estado NOT IN ('resuelto', 'cancelado')) AS tickets,
                        (SELECT COUNT(*) FROM incidentes WHERE id_responsable = ? AND estado <> 'cerrado') AS incidentes";
                $consultaPendientes = $pdo->prepare($sqlPendientes);
                $consultaPendientes->execute([$idUsuario, $idUsuario]);
                $pendientes = $consultaPendientes->fetch();

                if ($pendientes['tickets'] || $pendientes['incidentes']) {
                    $mensaje = 'Reasigná los tickets e incidentes pendientes antes de cambiar a Profesor.';
                }
            }

            if ($mensaje === '') {
                if ($esEdicion) {
                    $sqlUsuario = 'UPDATE usuarios SET nombre = ?, nombre_usuario = ?, rol = ?';
                    $parametros = [$nombre, $nombreUsuario, $rol];

                    if ($contrasena !== '') {
                        $sqlUsuario .= ', password_hash = ?';
                        $parametros[] = password_hash($contrasena, PASSWORD_DEFAULT);
                    }

                    $sqlUsuario .= ' WHERE id_usuario = ?';
                    $parametros[] = $idUsuario;
                } else {
                    $sqlUsuario = "INSERT INTO usuarios (nombre, nombre_usuario, rol, password_hash)
                                   VALUES (?, ?, ?, ?)";
                    $parametros = [$nombre, $nombreUsuario, $rol, password_hash($contrasena, PASSWORD_DEFAULT)];
                }

                $consultaGuardar = $pdo->prepare($sqlUsuario);
                $consultaGuardar->execute($parametros);
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Usuario guardado correctamente.';
                header('Location: index.php');
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = errorGuardado($error, 'Ese nombre de usuario ya existe, incluso si la cuenta está inactiva.');
        }
    }

    $cuenta['nombre'] = $nombre;
    $cuenta['nombre_usuario'] = $nombreUsuario;
    $cuenta['rol'] = $rol;
}

$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="100" value="<?= escapar($cuenta['nombre']) ?>" required>
    <label for="nombre_usuario">Usuario</label>
    <input id="nombre_usuario" name="nombre_usuario" maxlength="50" autocomplete="off" value="<?= escapar($cuenta['nombre_usuario']) ?>" required>
    <label for="rol">Rol</label>
    <select id="rol" name="rol" required>
        <?php foreach ($roles as $valorRol => $nombreRol): ?>
            <option value="<?= escapar($valorRol) ?>" <?= $cuenta['rol'] === $valorRol ? 'selected' : '' ?>><?= escapar($nombreRol) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="password"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
    <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" <?= !$esEdicion ? 'required' : '' ?>>
    <p class="ayuda">Usá al menos 8 caracteres. Al editar, dejá el campo vacío para conservar la contraseña.</p>
    <div class="acciones">
        <button class="boton" type="submit">Guardar usuario</button>
        <a class="boton boton-secundario" href="index.php">Cancelar</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
