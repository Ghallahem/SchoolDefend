<?php
require_once __DIR__ . '/includes/sesion.php';

$usuarioConectado = usuarioActual();

if ($usuarioConectado !== null) {
    header('Location: index.php');
    exit;
}

// Si se desactivó la cuenta anterior, comenzar una sesión nueva para el formulario.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$mensaje = '';
$nombreUsuario = '';
$contrasena = '';
$rolesPermitidos = ['administrador', 'tecnico', 'profesor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nombre_usuario']) && is_string($_POST['nombre_usuario'])) {
        $nombreUsuario = trim($_POST['nombre_usuario']);
    }

    if (isset($_POST['password']) && is_string($_POST['password'])) {
        $contrasena = $_POST['password'];
    }

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá ingresar nuevamente.';
    } elseif ($nombreUsuario === '' || $contrasena === '') {
        $mensaje = 'Completá el usuario y la contraseña.';
    } else {
        $pdo = getConexion();
        $sqlUsuario = "
            SELECT id_usuario, password_hash, activo, rol
            FROM usuarios
            WHERE nombre_usuario = ?";

        $consultaUsuario = $pdo->prepare($sqlUsuario);
        $consultaUsuario->execute([$nombreUsuario]);
        $cuenta = $consultaUsuario->fetch();

        $accesoPermitido = false;

        if ($cuenta) {
            $cuentaActiva = (int) $cuenta['activo'] === 1;
            $rolPermitido = in_array($cuenta['rol'], $rolesPermitidos, true);
            $contrasenaCorrecta = password_verify($contrasena, $cuenta['password_hash']);

            $accesoPermitido = $cuentaActiva && $rolPermitido && $contrasenaCorrecta;
        }

        if ($accesoPermitido) {
            session_regenerate_id(true);

            $_SESSION = [];
            $_SESSION['id_usuario'] = (int) $cuenta['id_usuario'];

            header('Location: index.php');
            exit;
        }

        $mensaje = 'Usuario o contraseña incorrectos, o cuenta inactiva.';
    }
}

$titulo = 'Iniciar sesión';
$usuario = null;
$mensajeParaMostrar = escapar($mensaje);
$nombreUsuarioParaMostrar = escapar($nombreUsuario);
$tokenAcceso = escapar(tokenFormulario());

require_once __DIR__ . '/includes/encabezado.php';
?>
<section class="acceso">
    <p class="etiqueta">Acceso al sistema</p>

    <h1>Iniciar sesión</h1>

    <p class="texto-secundario">Ingresá con tu cuenta para acceder a las funciones de tu rol.</p>

    <?php if ($mensajeParaMostrar !== ''): ?>
        <p class="alerta" role="alert"><?= $mensajeParaMostrar ?></p>
    <?php endif; ?>

    <form method="post" action="login.php" class="formulario">
        <input type="hidden" name="token" value="<?= $tokenAcceso ?>">

        <label for="nombre_usuario">Usuario</label>
        <input id="nombre_usuario" name="nombre_usuario" maxlength="50" autocomplete="username"
               value="<?= $nombreUsuarioParaMostrar ?>" required autofocus>

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button class="boton" type="submit">Ingresar</button>
    </form>

    <p class="ayuda">Las cuentas son creadas por el Administrador.</p>
</section>
<?php require_once __DIR__ . '/includes/pie.php'; ?>


